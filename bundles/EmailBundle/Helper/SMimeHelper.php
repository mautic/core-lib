<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Helper\Filesystem;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Swiftmailer\Signers\SMimeSigner;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Signs message with S/MIME certificate.
 */
class SMimeHelper
{
    /**
     * Caching the certificates to avoid reading them from the filesystem on every message.
     *
     * @var array<string,string[]>
     */
    private array $certCache = [];

    public function __construct(private CoreParametersHelper $coreParametersHelper, private Filesystem $filesystem, private EncryptionHelper $encryptionHelper)
    {
    }

    public function sMimeSigningEnabled(): bool
    {
        return (bool) $this->coreParametersHelper->get('smime_signing_enabled', false);
    }

    public function getSMimeCertificatePath(): string
    {
        return rtrim((string) $this->coreParametersHelper->get('smime_certificates_path'), '/');
    }

    /**
     * Signs the message with S/MIME if enabled and certificates are available.
     * Returns the signed message, or the original message if signing is not applicable.
     */
    public function signContent(MauticMessage $message): RawMessage
    {
        if (!$this->sMimeSigningEnabled()) {
            return $message;
        }

        /** @var Address[] $fromArray */
        $fromArray = $message->getFrom();

        if (!is_array($fromArray) || 1 !== count($fromArray)) {
            return $message;
        }

        $fromEmail = $fromArray[0]->getAddress();

        if (isset($this->certCache[$fromEmail])) {
            [$publicKey, $privateKey] = $this->certCache[$fromEmail];
        } else {
            try {
                [$publicKey, $privateKey] = $this->getCertificatesFromDisk($fromEmail);
            } catch (IOException) {
                return $message;
            }

            $this->certCache[$fromEmail] = [$publicKey, $privateKey];
        }

        // Create Symfony's SMimeSigner with the certificate and private key
        $signer = new SMimeSigner($publicKey, $privateKey);

        // Sign and return the signed message
        return $signer->sign($message);
    }

    /**
     * @throws IOException if one of the cetificates is not found
     *
     * @return string[]
     */
    private function getCertificatesFromDisk(string $fromEmail): array
    {
        $certPath                = $this->getSMimeCertificatePath();
        $publicCertPath          = "{$certPath}/{$fromEmail}.crt";
        $privateKeyPath          = "{$certPath}/{$fromEmail}.pem";
        $privateKeyEncryptedPath = "{$certPath}/{$fromEmail}.pem.enc";
        $publicKey               = $this->filesystem->readFile($publicCertPath);

        try {
            // Try to decrypt the private key first
            $privateKey = $this->encryptionHelper->decrypt($this->filesystem->readFile($privateKeyEncryptedPath));
        } catch (IOException) {
            // If the private key is not encrypted, just try to read it in an unecrypted form
            $privateKey = $this->filesystem->readFile($privateKeyPath);
        }

        if (!$privateKey) {
            throw new IOException('Could not encrypt the private key');
        }

        return [$publicKey, $privateKey];
    }
}
