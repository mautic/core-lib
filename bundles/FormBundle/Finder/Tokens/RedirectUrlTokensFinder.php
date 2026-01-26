<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Finder\Tokens;

use Mautic\FormBundle\DTO\TokenDto;
use Mautic\FormBundle\Enum\Token\RedirectUrlToken;

final readonly class RedirectUrlTokensFinder
{
    public function hasTokens(string $url): bool
    {
        $tokensRegex = RedirectUrlToken::createAllTokensRegex();

        return \preg_match_all($tokensRegex, $url) > 0;
    }

    public function replaceTokensWithDummyData(string $url): string
    {
        $tokensRegex = RedirectUrlToken::createAllTokensRegex();
        \preg_match_all($tokensRegex, $url, $matches);

        $names  = $matches['name'];
        $values = $matches['value'];
        $result = $url;

        foreach ($names as $index => $name) {
            $value = $values[$index];

            $token           = new TokenDto($name, $value);
            $nameAndPosition = \sprintf('%s-%d', $name, $index + 1);

            $result = \str_replace($token->toString(), $nameAndPosition, $result);
        }

        return $result;
    }
}
