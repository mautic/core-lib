<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Helper\Filesystem;
use Mautic\EmailBundle\Swiftmailer\Signers\SMimeSigner;
use Swift_Message;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Signs message with S/MIME certificate.
 */
class SMimeHelper
{
    private CoreParametersHelper $coreParametersHelper;
    private Filesystem $filesystem;
    private EncryptionHelper $encryptionHelper;

    /**
     * Caching the certificates to avoid reading them from the filesystem on every message.
     *
     * @var array<string,string[]>
     */
    private array $certCache = [];

    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        Filesystem $filesystem,
        EncryptionHelper $encryptionHelper
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->filesystem           = $filesystem;
        $this->encryptionHelper     = $encryptionHelper;
    }

    public function sMimeSigningEnabled(): bool
    {
        return (bool) $this->coreParametersHelper->get('smime_signing_enabled', false);
    }

    public function getSMimeCertificatePath(): string
    {
        return rtrim((string) $this->coreParametersHelper->get('smime_certificates_path'), '/');
    }

    public function signContent(Swift_Message $message): ?SMimeSigner
    {
        if (!$this->sMimeSigningEnabled()) {
            return null;
        }

        /** @var array<string,string> $fromArray where the key is email address and value is name */
        $fromArray = $message->getFrom();

        if (!is_array($fromArray) || 1 !== count($fromArray)) {
            return null;
        }

        $fromEmail = array_keys($fromArray)[0];

        if (isset($this->certCache[$fromEmail])) {
            [$publicKey, $privateKey] = $this->certCache[$fromEmail];
        } else {
            try {
                [$publicKey, $privateKey] = $this->getCertificatesFromDisk($fromEmail);
            } catch (IOException $e) {
                return null;
            }

            $this->certCache[$fromEmail] = [$publicKey, $privateKey];
        }

        $signer = new SMimeSigner($publicKey, $privateKey);

        $message->attachSigner($signer);

        return $signer;
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
        } catch (IOException $e) {
            // If the private key is not encrypted, just try to read it in an unecrypted form
            $privateKey = $this->filesystem->readFile($privateKeyPath);
        }

        if (!$privateKey) {
            throw new IOException('Could not encrypt the private key');
        }

        return [$publicKey, $privateKey];
    }
}
