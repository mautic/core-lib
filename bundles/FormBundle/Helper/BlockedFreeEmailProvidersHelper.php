<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Helper;

final class BlockedFreeEmailProvidersHelper
{
    private const JSON_FILE_PATH = __DIR__.'/../Assets/json/blocked_free_email_providers.json';

    /**
     * Load blocked free email providers from JSON file.
     *
     * @return array<string>
     */
    public static function load(): array
    {
        if (!file_exists(self::JSON_FILE_PATH) || !is_readable(self::JSON_FILE_PATH)) {
            return [];
        }

        $content = file_get_contents(self::JSON_FILE_PATH);
        if (false === $content) {
            return [];
        }

        try {
            $providers = json_decode($content, true, JSON_THROW_ON_ERROR);
            if (!is_array($providers)) {
                return [];
            }

            return array_map('strtolower', $providers);
        } catch (\JsonException) {
            return [];
        }
    }
}
