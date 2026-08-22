<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Verifies submissions for the locations WHMCS offers no validation hook for.
 *
 * Login, password reset and the contact form are processed before any hook
 * point that can reject them, so the check has to run while the hook file is
 * being loaded. Rejected submissions are redirected back to the originating
 * page with a message rather than terminating the response mid request.
 */
final class Guard
{
    private function __construct()
    {
    }

    public static function inspectRequest(): void
    {
        if (!Request::isPost() || Request::isNonClientAreaContext()) {
            return;
        }

        $location = Location::fromRequest();
        if ($location === null || !in_array($location, Location::INTERCEPTED, true)) {
            return;
        }

        $error = Challenge::validate($location);
        if ($error === null) {
            return;
        }

        self::reject($error);
    }

    private static function reject(string $message): void
    {
        if (Flash::set($message) && !headers_sent()) {
            header('Location: ' . self::returnUrl(), true, 303);
            exit;
        }

        self::renderFallbackPage($message);
    }

    /**
     * The page to send the visitor back to. The referer is only trusted when
     * it points at this installation, so the module cannot be abused as an
     * open redirect.
     */
    private static function returnUrl(): string
    {
        $systemUrl = Environment::systemUrl();
        $referer = Request::referer();

        if ($referer !== null && self::isSameOrigin($referer, $systemUrl)) {
            return $referer;
        }

        return $systemUrl === '' ? '/' : $systemUrl . '/';
    }

    private static function isSameOrigin(string $candidate, string $systemUrl): bool
    {
        $candidateHost = parse_url($candidate, PHP_URL_HOST);
        $expectedHost = parse_url($systemUrl, PHP_URL_HOST) ?: Request::host();

        if (!is_string($candidateHost) || !is_string($expectedHost) || $expectedHost === '') {
            return false;
        }

        return strcasecmp($candidateHost, $expectedHost) === 0;
    }

    /**
     * Minimal, self contained page used when there is no session available to
     * carry the message through a redirect.
     */
    private static function renderFallbackPage(string $message): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8', true, 403);
        }

        $escapedMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedUrl = htmlspecialchars(self::returnUrl(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Verification required</title></head>'
            . '<body><h1>Verification required</h1><p>' . $escapedMessage . '</p>'
            . '<p><a href="' . $escapedUrl . '">Go back</a></p></body></html>';

        exit;
    }
}
