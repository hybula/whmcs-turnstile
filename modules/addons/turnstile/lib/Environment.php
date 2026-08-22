<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

use Throwable;
use WHMCS\Config\Setting;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Resolves installation level values that the module needs.
 */
final class Environment
{
    private static ?string $systemUrl = null;

    private function __construct()
    {
    }

    /**
     * The configured system URL without a trailing slash, falling back to the
     * current host when the setting cannot be read.
     */
    public static function systemUrl(): string
    {
        if (self::$systemUrl !== null) {
            return self::$systemUrl;
        }

        $url = '';

        try {
            if (class_exists(Setting::class)) {
                $url = (string) Setting::getValue('SystemURL');
            }
        } catch (Throwable $exception) {
            $url = '';
        }

        if ($url === '') {
            $host = Request::host();
            $url = $host === null ? '' : (Request::isSecure() ? 'https://' : 'http://') . $host;
        }

        return self::$systemUrl = rtrim($url, '/');
    }
}
