<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Reads strings from the addon language file WHMCS loads into $_ADDONLANG.
 */
final class Lang
{
    private function __construct()
    {
    }

    public static function get(string $key, string $default): string
    {
        $strings = $GLOBALS['_ADDONLANG'] ?? null;

        if (is_array($strings) && isset($strings[$key]) && is_string($strings[$key]) && $strings[$key] !== '') {
            return $strings[$key];
        }

        return $default;
    }
}
