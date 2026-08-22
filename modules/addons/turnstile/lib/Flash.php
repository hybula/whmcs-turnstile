<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Single use session message, used to carry a rejection notice across the
 * redirect that follows a failed challenge.
 */
final class Flash
{
    private const SESSION_KEY = 'turnstileCaptchaFlash';

    /**
     * Messages older than this are discarded, so a notice cannot resurface on
     * an unrelated page view much later in the session.
     */
    private const TTL_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * @return bool false when there is no session to store the message in
     */
    public static function set(string $message): bool
    {
        if (!self::hasSession()) {
            return false;
        }

        $_SESSION[self::SESSION_KEY] = [
            'message' => $message,
            'time' => time(),
        ];

        return true;
    }

    public static function pull(): ?string
    {
        if (!self::hasSession() || !isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $flash = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($flash)) {
            return null;
        }

        $message = $flash['message'] ?? null;
        $time = (int) ($flash['time'] ?? 0);

        if (!is_string($message) || $message === '' || (time() - $time) > self::TTL_SECONDS) {
            return null;
        }

        return $message;
    }

    private static function hasSession(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
