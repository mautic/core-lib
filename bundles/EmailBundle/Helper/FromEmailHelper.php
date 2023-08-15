<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Helper\DTO\AddressDTO;
use Mautic\EmailBundle\Helper\Exception\OwnerNotFoundException;
use Mautic\EmailBundle\Helper\Exception\TokenNotFoundOrEmptyException;
use Mautic\LeadBundle\Entity\LeadRepository;

class FromEmailHelper
{
    /**
     * @var array<int,mixed[]>
     */
    private array $owners = [];

    private ?AddressDTO $defaultFrom = null;

    /**
     * @var mixed[]|null
     */
    private ?array $lastOwner = null;

    public function __construct(private CoreParametersHelper $coreParametersHelper, private LeadRepository $leadRepository)
    {
    }

    /**
     * @param array<string,?string> $from
     */
    public function setDefaultFromArray(array $from): void
    {
        $this->defaultFrom = AddressDTO::fromAddressArray($from);
    }

    /**
     * @param array<string,?string> $from
     * @param mixed[] $contact
     *
     * @return array<string,?string>
     */
    public function getFromAddressArrayConsideringOwner(array $from, array $contact = null, Email $email = null): array
    {
        $address = AddressDTO::fromAddressArray($from);

        // Reset last owner
        $this->lastOwner = null;

        // Check for token
        if ($address->isEmailTokenized() || $address->isNameTokenized()) {
            return $this->getEmailArrayFromToken($address, $contact, true, $email);
        }

        if (!$contact) {
            return $from;
        }

        try {
            return $this->getFromEmailArrayAsOwner($contact, $email);
        } catch (OwnerNotFoundException $exception) {
            return $from;
        }
    }

    /**
     * @param array<string,?string> $from
     * @param mixed[] $contact
     *
     * @return array<string,?string>
     */
    public function getFromAddressArray(array $from, array $contact = null): array
    {
        $address = AddressDTO::fromAddressArray($from);

        // Reset last owner
        $this->lastOwner = null;

        // Check for token
        if ($address->isEmailTokenized() || $address->isNameTokenized()) {
            return $this->getEmailArrayFromToken($address, $contact, false);
        }

        return $from;
    }

    /**
     * @return mixed[]
     *
     * @throws OwnerNotFoundException
     */
    public function getContactOwner(int $userId, Email $email = null): array
    {
        // Reset last owner
        $this->lastOwner = null;

        if ($email) {
            if (!$email->getUseOwnerAsMailer()) {
                throw new OwnerNotFoundException("mailer_is_owner is not enabled for this email ({$email->getId()})");
            }
        } elseif (!$this->coreParametersHelper->get('mailer_is_owner')) {
            throw new OwnerNotFoundException('mailer_is_owner is not enabled in global configuration');
        }

        if (isset($this->owners[$userId])) {
            return $this->lastOwner = $this->owners[$userId];
        }

        if ($owner = $this->leadRepository->getLeadOwner($userId)) {
            $this->owners[$userId] = $this->lastOwner = $owner;

            return $owner;
        }

        throw new OwnerNotFoundException();
    }

    public function getSignature(): string
    {
        if (!$this->lastOwner) {
            return '';
        }

        $owner = $this->lastOwner;

        return $this->replaceSignatureTokens($owner['signature'], $owner);
    }

    /**
     * @param mixed[] $owner
     */
    private function replaceSignatureTokens(string $signature, array $owner): string
    {
        $signature = nl2br($signature);
        $signature = str_replace('|FROM_NAME|', $owner['first_name'].' '.$owner['last_name'], $signature);

        foreach ($owner as $key => $value) {
            $token     = sprintf('|USER_%s|', strtoupper($key));
            $signature = str_replace($token, $value ?? '', $signature);
        }

        return EmojiHelper::toHtml($signature);
    }

    /**
     * @return array<string,?string>
     */
    private function getDefaultFromArray(): array
    {
        if ($this->defaultFrom) {
            return $this->defaultFrom->getAddressArray();
        }

        return $this->getSystemDefaultFrom()->getAddressArray();
    }

    private function getSystemDefaultFrom(): AddressDTO
    {
        $email = $this->coreParametersHelper->get('mailer_from_email');
        $name  = $this->coreParametersHelper->get('mailer_from_name') ?: null;

        return new AddressDTO($email, $name);
    }

    /**
     * @param mixed[] $contact
     *
     * @return array<string,?string>
     */
    private function getEmailArrayFromToken(AddressDTO $address, array $contact = null, bool $asOwner = true, Email $email = null): array
    {
        try {
            if (!$contact) {
                throw new TokenNotFoundOrEmptyException();
            }

            $name = $address->isNameTokenized() ? $address->getNameTokenValue($contact) : $address->getName();
        } catch (TokenNotFoundOrEmptyException $exception) {
            $name = $this->defaultFrom ? $this->defaultFrom->getName() : $this->getSystemDefaultFrom()->getName();
        }

        try {
            if (!$contact) {
                throw new TokenNotFoundOrEmptyException();
            }

            $email = $address->isEmailTokenized() ? $address->getEmailTokenValue($contact) : $address->getEmail();

            return [$email => $name];
        } catch (TokenNotFoundOrEmptyException $exception) {
            if ($contact && $asOwner) {
                try {
                    return $this->getFromEmailArrayAsOwner($contact, $email);
                } catch (OwnerNotFoundException $exception) {
                }
            }

            return $this->getDefaultFromArray();
        }
    }

    /**
     * @param mixed[] $contact
     *
     * @return array<string,string>
     *
     * @throws OwnerNotFoundException
     */
    private function getFromEmailArrayAsOwner(array $contact, Email $email = null): array
    {
        if (empty($contact['owner_id'])) {
            throw new OwnerNotFoundException();
        }

        $owner      = $this->getContactOwner((int) $contact['owner_id'], $email);
        $ownerEmail = $owner['email'];
        $ownerName  = sprintf('%s %s', $owner['first_name'], $owner['last_name']);

        // Decode apostrophes and other special characters
        $ownerName = trim(html_entity_decode($ownerName, ENT_QUOTES));

        return [$ownerEmail => $ownerName];
    }
}
