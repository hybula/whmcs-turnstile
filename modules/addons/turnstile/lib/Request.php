<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Read-only accessors for the current request.
 *
 * Superglobals are never trusted directly elsewhere in the module; every value
 * is funnelled through this class so that type and shape checks happen once.
 */
final class Request
{
    private function __construct()
    {
    }

    public static function isPost(): bool
    {
        return strtoupper(self::server('REQUEST_METHOD') ?? '') === 'POST';
    }

    /**
     * True when the request is not a regular client area page view.
     */
    public static function isNonClientAreaContext(): bool
    {
        return PHP_SAPI === 'cli'
            || defined('ADMINAREA')
            || defined('APIV1')
            || defined('WHMCS_API');
    }

    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function post(string $key): ?string
    {
        $value = $_POST[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public static function hasPost(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($_POST[$key])) {
                return false;
            }
        }

        return $keys !== [];
    }

    public static function query(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * The current entry point without directory or extension, e.g. "cart".
     */
    public static function scriptBasename(): string
    {
        return strtolower(basename(self::server('SCRIPT_NAME') ?? '', '.php'));
    }

    /**
     * The requested URI, lowercased, for loose route matching.
     */
    public static function uri(): string
    {
        return strtolower(rawurldecode(self::server('REQUEST_URI') ?? ''));
    }

    public static function referer(): ?string
    {
        $referer = self::server('HTTP_REFERER');

        return ($referer === null || $referer === '') ? null : $referer;
    }

    /**
     * The connecting IP address, or null when it cannot be trusted.
     */
    public static function clientIp(): ?string
    {
        $remoteAddress = self::server('REMOTE_ADDR');
        if ($remoteAddress === null) {
            return null;
        }

        return filter_var($remoteAddress, FILTER_VALIDATE_IP) === false ? null : $remoteAddress;
    }

    public static function host(): ?string
    {
        $host = self::server('HTTP_HOST');

        return ($host === null || $host === '') ? null : $host;
    }

    public static function isSecure(): bool
    {
        $https = self::server('HTTPS');
        if ($https !== null && strtolower($https) !== 'off') {
            return true;
        }

        return (int) (self::server('SERVER_PORT') ?? '0') === 443;
    }

    private static function server(string $key): ?string
    {
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
