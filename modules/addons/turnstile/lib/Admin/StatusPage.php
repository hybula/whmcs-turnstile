<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile\Admin;

use WHMCS\Module\Addon\Turnstile\Lang;
use WHMCS\Module\Addon\Turnstile\Location;
use WHMCS\Module\Addon\Turnstile\Settings;
use WHMCS\Module\Addon\Turnstile\VerificationResult;
use WHMCS\Module\Addon\Turnstile\Verifier;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Admin landing page for the module.
 *
 * It reports the saved configuration and performs a read only connectivity
 * check, so a misconfigured secret key is visible before visitors hit it.
 */
final class StatusPage
{
    private function __construct()
    {
    }

    public static function render(): string
    {
        $settings = Settings::load();

        $html = self::configurationPanel($settings);
        $html .= self::formsPanel($settings);

        if ($settings->isConfigured()) {
            $html .= self::connectivityPanel($settings);
        }

        return $html;
    }

    private static function configurationPanel(Settings $settings): string
    {
        if ($settings->isConfigured()) {
            return self::panel(
                Lang::get('statusHeading', 'Status'),
                self::alert('success', Lang::get('keysPresent', 'A site key and secret key are saved.'))
            );
        }

        return self::panel(
            Lang::get('statusHeading', 'Status'),
            self::alert(
                'warning',
                Lang::get('keysMissing', 'No site key or secret key has been saved yet.')
            )
        );
    }

    private static function formsPanel(Settings $settings): string
    {
        $rows = '';
        $protectedCount = 0;

        foreach (Location::all() as $location) {
            $isProtected = $settings->protects($location);
            $protectedCount += $isProtected ? 1 : 0;

            $rows .= sprintf(
                '<tr><td>%s</td><td><span class="label label-%s">%s</span></td></tr>',
                self::escape(Location::label($location)),
                $isProtected ? 'success' : 'default',
                self::escape(
                    $isProtected
                        ? Lang::get('enabled', 'Protected')
                        : Lang::get('disabled', 'Not protected')
                )
            );
        }

        $body = sprintf(
            '<table class="table table-condensed"><thead><tr><th>%s</th><th>%s</th></tr></thead><tbody>%s</tbody></table>',
            self::escape(Lang::get('form', 'Form')),
            self::escape(Lang::get('state', 'State')),
            $rows
        );

        if ($protectedCount === 0) {
            $body = self::alert(
                'warning',
                Lang::get('noFormsSelected', 'No forms are protected yet.')
            ) . $body;
        }

        return self::panel(Lang::get('protectedFormsHeading', 'Protected forms'), $body);
    }

    private static function connectivityPanel(Settings $settings): string
    {
        $result = (new Verifier($settings->secretKey()))->selfTest();

        [$style, $message] = self::describe($result);

        return self::panel(
            Lang::get('connectivityHeading', 'Verification endpoint'),
            self::alert($style, $message) . '<p class="text-muted"><small>' . self::escape($result->summary()) . '</small></p>'
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function describe(VerificationResult $result): array
    {
        if ($result->isTransportFailure()) {
            return ['danger', Lang::get('unreachable', 'The verification endpoint could not be reached.')];
        }

        // The self test submits a deliberately invalid token, so this specific
        // rejection proves the secret key and the connection are both good.
        // Cloudflare's testing keys accept any token, hence the success case.
        if ($result->isSuccess() || $result->hasErrorCode('invalid-input-response')) {
            return ['success', Lang::get('secretValid', 'The secret key was accepted by Cloudflare.')];
        }

        if ($result->hasErrorCode('invalid-input-secret') || $result->hasErrorCode('missing-input-secret')) {
            return ['danger', Lang::get('secretInvalid', 'Cloudflare rejected the secret key.')];
        }

        return ['warning', Lang::get('unexpected', 'The verification endpoint returned an unexpected result.')];
    }

    private static function panel(string $heading, string $body): string
    {
        return '<div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">'
            . self::escape($heading)
            . '</h3></div><div class="panel-body">' . $body . '</div></div>';
    }

    private static function alert(string $style, string $message): string
    {
        return '<div class="alert alert-' . self::escape($style) . '">' . self::escape($message) . '</div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
