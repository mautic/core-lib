<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Swiftmailer\Signers;

class SMimeSigner extends \Swift_Signers_SMimeSigner
{
    /**
     * Set the certificate location to use for signing. Enhanced over the original method to support string content. Not just file paths.
     *
     * @see https://secure.php.net/manual/en/openssl.pkcs7.flags.php
     *
     * @param string         $certificate path to the certificate file or the certificate content
     * @param string|mixed[] $privateKey  If the key needs an passphrase use array('file-location', 'passphrase') instead
     * @param int            $signOptions Bitwise operator options for openssl_pkcs7_sign()
     * @param string         $extraCerts  A file containing intermediate certificates needed by the signing certificate
     *
     * @return $this
     */
    public function setSignCertificate($certificate, $privateKey = null, $signOptions = PKCS7_DETACHED, $extraCerts = null): self
    {
        if ($path = realpath($certificate)) {
            // Cam be a path to a file.
            $this->signCertificate = 'file://'.str_replace('\\', '/', $path);
        } else {
            // Or a string content of the certificate.
            $this->signCertificate = $certificate;
        }

        if (null !== $privateKey) {
            if (\is_array($privateKey)) {
                $this->signPrivateKey = $privateKey;
                if ($path = realpath($privateKey[0])) {
                    $this->signPrivateKey[0] = 'file://'.str_replace('\\', '/', $path);
                }
            } elseif ($path = realpath($privateKey)) {
                $this->signPrivateKey = 'file://'.str_replace('\\', '/', $path);
            } else {
                $this->signPrivateKey = $privateKey;
            }
        }

        $this->signOptions = $signOptions;
        $this->extraCerts  = $extraCerts ? realpath($extraCerts) : null;

        return $this;
    }
}
