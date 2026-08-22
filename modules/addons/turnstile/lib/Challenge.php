<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Decides whether a location must be challenged and validates the token that
 * was submitted with the form.
 */
final class Challenge
{
    private function __construct()
    {
    }

    /**
     * Whether the widget should be shown, and the submission verified, for the
     * given location on this request.
     */
    public static function applies(string $location): bool
    {
        if (Request::isNonClientAreaContext()) {
            return false;
        }

        $settings = Settings::load();

        if (!$settings->protects($location)) {
            return false;
        }

        if ($settings->skipAuthenticated() && Request::isAuthenticated()) {
            return false;
        }

        if (!$settings->isConfigured()) {
            // Only reported while validating, so a misconfiguration is visible
            // without filling the log on every page view.
            if (Request::isPost()) {
                Logger::activity(
                    'Skipped the ' . Location::label($location) . ' challenge because no keys are configured.'
                );
            }

            return false;
        }

        return true;
    }

    /**
     * Validates the submitted token.
     *
     * @return string|null the message to show the visitor, or null when the
     *                     submission may proceed
     */
    public static function validate(string $location): ?string
    {
        if (!self::applies($location)) {
            return null;
        }

        $settings = Settings::load();
        $token = Request::post(Verifier::RESPONSE_FIELD) ?? '';

        if ($token === '') {
            self::report($location, 'no challenge response was submitted', $settings);

            return $settings->errorMessage();
        }

        $result = (new Verifier($settings->secretKey()))->verify(
            $token,
            $settings->sendVisitorIp() ? Request::clientIp() : null
        );

        Logger::verification($location, $result, $settings->secretKey());

        if ($result->isTransportFailure()) {
            Logger::activity(sprintf(
                '%s challenge could not be verified (%s). %s',
                Location::label($location),
                (string) $result->transportError(),
                $settings->failOpen() ? 'Allowing the submission.' : 'Blocking the submission.'
            ));

            return $settings->failOpen() ? null : $settings->errorMessage();
        }

        if (!$result->isSuccess()) {
            self::report($location, $result->summary(), $settings);

            return $settings->errorMessage();
        }

        return null;
    }

    private static function report(string $location, string $reason, Settings $settings): void
    {
        if (!$settings->logFailures()) {
            return;
        }

        Logger::activity(sprintf(
            '%s challenge rejected for %s (%s).',
            Location::label($location),
            Request::clientIp() ?? 'an unknown IP address',
            $reason
        ));
    }
}
