<?php

/**
 * Hook registrations for the Turnstile Captcha addon module.
 *
 * WHMCS only loads this file while the module is active, so activation alone
 * controls whether any of the checks run.
 */

declare(strict_types=1);

use WHMCS\Module\Addon\Turnstile\Bootstrap;
use WHMCS\Module\Addon\Turnstile\Challenge;
use WHMCS\Module\Addon\Turnstile\Guard;
use WHMCS\Module\Addon\Turnstile\Location;
use WHMCS\Module\Addon\Turnstile\Logger;
use WHMCS\Module\Addon\Turnstile\Renderer;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/Bootstrap.php';
Bootstrap::register();

if (!function_exists('turnstile_guardedHook')) {
    /**
     * Runs a hook body, converting any unexpected failure into a log entry.
     *
     * A captcha must never be the reason a store stops responding, so internal
     * errors let the request continue and are recorded for the administrator.
     *
     * @template T
     * @param callable():T $callback
     * @param T $fallback
     * @return T
     */
    function turnstile_guardedHook(callable $callback, $fallback)
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            Logger::activity('Unexpected error: ' . $exception->getMessage());

            return $fallback;
        }
    }
}

if (!function_exists('turnstile_errorList')) {
    /**
     * Wraps a rejection message in the array shape WHMCS validation hooks expect.
     *
     * @return list<string>
     */
    function turnstile_errorList(?string $error): array
    {
        return $error === null ? [] : [$error];
    }
}

/**
 * Validates login, password reset and contact form submissions.
 *
 * These pages are handled before any hook point that could reject them, so the
 * check has to happen here, while hooks are being loaded.
 */
turnstile_guardedHook(static function (): void {
    Guard::inspectRequest();
}, null);

add_hook('ClientAreaFooterOutput', 1, static function ($vars): string {
    return turnstile_guardedHook(static function () use ($vars): string {
        return Renderer::clientAreaFooter(is_array($vars) ? $vars : []);
    }, '');
});

/**
 * Registration. ClientDetailsValidation also fires for profile updates and for
 * account creation during checkout, so the location is confirmed first.
 */
add_hook('ClientDetailsValidation', 1, static function ($vars): array {
    return turnstile_guardedHook(static function (): array {
        if (Location::fromRequest() !== Location::REGISTER) {
            return [];
        }

        return turnstile_errorList(Challenge::validate(Location::REGISTER));
    }, []);
});

add_hook('ShoppingCartValidateCheckout', 1, static function ($vars): array {
    return turnstile_guardedHook(static function (): array {
        return turnstile_errorList(Challenge::validate(Location::CHECKOUT));
    }, []);
});

add_hook('TicketOpenValidation', 1, static function ($vars): array {
    return turnstile_guardedHook(static function (): array {
        return turnstile_errorList(Challenge::validate(Location::TICKET));
    }, []);
});
