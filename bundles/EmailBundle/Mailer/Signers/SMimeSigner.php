<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Mailer\Signers;

use Symfony\Component\Mime\Crypto\SMimeSigner as SymfonySMimeSigner;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;

/**
 * Custom SMimeSigner that wraps Symfony's SMimeSigner to support certificate/key content as strings.
 * If the certificate or private key is provided as a string (not a file path), it will be written to a temporary file.
 */
final class SMimeSigner
{
    private ?string $tempCertFile = null;
    private ?string $tempKeyFile  = null;
    private SymfonySMimeSigner $signer;

    /**
     * @param string      $certificate          The path to the certificate file OR the certificate content in PEM format
     * @param string      $privateKey           The path to the private key file OR the private key content in PEM format
     * @param string|null $privateKeyPassphrase A passphrase for the private key (if any)
     * @param string|null $extraCerts           The path to intermediate certificates file (if any)
     * @param int|null    $signOptions          Bitwise operator options for openssl_pkcs7_sign()
     */
    public function __construct(string $certificate, string $privateKey, ?string $privateKeyPassphrase = null, ?string $extraCerts = null, ?int $signOptions = null)
    {
        // Check if certificate is a file path or content
        if (!file_exists($certificate)) {
            // It's content, write to temp file
            $this->tempCertFile = $this->writeTempFile($certificate, 'cert');
            $certificate        = $this->tempCertFile;
        }

        // Check if private key is a file path or content
        if (!file_exists($privateKey)) {
            // It's content, write to temp file
            $this->tempKeyFile = $this->writeTempFile($privateKey, 'key');
            $privateKey        = $this->tempKeyFile;
        }

        $this->signer = new SymfonySMimeSigner($certificate, $privateKey, $privateKeyPassphrase, $extraCerts, $signOptions);
    }

    /**
     * Sign the given message.
     */
    public function sign(Message $message): Message
    {
        return $this->signer->sign($message);
    }

    /**
     * Write content to a temporary file.
     */
    private function writeTempFile(string $content, string $prefix): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'mautic_smime_'.$prefix.'_');
        if (false === $tempFile) {
            throw new RuntimeException('Failed to create temporary file for S/MIME certificate/key.');
        }

        if (false === file_put_contents($tempFile, $content)) {
            throw new RuntimeException('Failed to write S/MIME certificate/key to temporary file.');
        }

        return $tempFile;
    }

    /**
     * Clean up temporary files when the object is destroyed.
     */
    public function __destruct()
    {
        if (null !== $this->tempCertFile && file_exists($this->tempCertFile)) {
            @unlink($this->tempCertFile);
        }

        if (null !== $this->tempKeyFile && file_exists($this->tempKeyFile)) {
            @unlink($this->tempKeyFile);
        }
    }
}
