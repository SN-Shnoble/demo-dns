<?php

namespace App\Domain\Profile;

final class DomainNormalizer
{
    public static function normalize(string $domain): string
    {
        $trimmed = trim($domain);
        $trimmed = rtrim($trimmed, '.');
        $lower = strtolower($trimmed);

        if ($lower === '') {
            throw new \InvalidArgumentException('Domain must not be empty.');
        }

        if (strlen($lower) > 253) {
            throw new \InvalidArgumentException('Domain exceeds maximum length.');
        }

        $ascii = idn_to_ascii($lower, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false) {
            throw new \InvalidArgumentException('Domain is not valid IDN input.');
        }

        foreach (explode('.', $ascii) as $label) {
            if ($label === '' || strlen($label) > 63) {
                throw new \InvalidArgumentException('Domain label length is invalid.');
            }

            if (! preg_match('/^[a-z0-9*][a-z0-9*-]*[a-z0-9*]$|^[a-z0-9*]$/', $label)) {
                throw new \InvalidArgumentException('Domain contains invalid characters.');
            }
        }

        return $ascii;
    }
}
