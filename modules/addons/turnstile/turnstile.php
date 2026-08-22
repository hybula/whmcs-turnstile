<?php

/**
 * Turnstile Captcha addon module for WHMCS.
 *
 * Protects client area forms with a Cloudflare Turnstile challenge.
 */

declare(strict_types=1);

use WHMCS\Module\Addon\Turnstile\Admin\StatusPage;
use WHMCS\Module\Addon\Turnstile\Bootstrap;
use WHMCS\Module\Addon\Turnstile\Location;
use WHMCS\Module\Addon\Turnstile\Settings;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/Bootstrap.php';
Bootstrap::register();

/**
 * @return array<string, mixed>
 */
function turnstile_config(): array
{
    $fields = [
        'siteKey' => [
            'FriendlyName' => 'Site Key',
            'Type' => 'text',
            'Size' => '60',
            'Description' => 'The Turnstile site key for this domain.',
        ],
        'secretKey' => [
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '60',
            'Description' => 'The Turnstile secret key. Used server side only.',
        ],
        'theme' => [
            'FriendlyName' => 'Widget Theme',
            'Type' => 'dropdown',
            'Options' => 'auto,light,dark',
            'Default' => 'auto',
            'Description' => 'Appearance of the challenge widget.',
        ],
        'size' => [
            'FriendlyName' => 'Widget Size',
            'Type' => 'dropdown',
            'Options' => 'normal,flexible,compact',
            'Default' => 'normal',
            'Description' => 'Layout of the challenge widget.',
        ],
        'errorMessage' => [
            'FriendlyName' => 'Rejection Message',
            'Type' => 'text',
            'Size' => '80',
            'Default' => Settings::DEFAULT_ERROR_MESSAGE,
            'Description' => 'Shown to the visitor when a challenge is not passed.',
        ],
    ];

    foreach (Location::all() as $location) {
        $fields[Location::settingKey($location)] = [
            'FriendlyName' => 'Protect: ' . Location::label($location),
            'Type' => 'yesno',
            'Description' => 'Require a challenge on the ' . strtolower(Location::label($location)) . ' form.',
        ];
    }

    $fields['skipAuthenticated'] = [
        'FriendlyName' => 'Skip Signed In Clients',
        'Type' => 'yesno',
        'Default' => 'on',
        'Description' => 'Do not challenge visitors who are already signed in.',
    ];

    $fields['sendVisitorIp'] = [
        'FriendlyName' => 'Send Visitor IP',
        'Type' => 'yesno',
        'Default' => 'on',
        'Description' => 'Include the connecting IP address in the verification request. '
            . 'Disable this when the web server does not see the real client address.',
    ];

    $fields['failOpen'] = [
        'FriendlyName' => 'Allow On Outage',
        'Type' => 'yesno',
        'Description' => 'Accept submissions when the verification endpoint cannot be reached. '
            . 'Leave disabled to reject them instead.',
    ];

    $fields['logFailures'] = [
        'FriendlyName' => 'Log Rejections',
        'Type' => 'yesno',
        'Default' => 'on',
        'Description' => 'Record rejected challenges in the activity log.',
    ];

    return [
        'name' => 'Turnstile Captcha',
        'description' => 'Protects the login, registration, password reset, contact, ticket and checkout '
            . 'forms with a Cloudflare Turnstile challenge.',
        'version' => Settings::VERSION,
        'author' => '',
        'language' => 'english',
        'fields' => $fields,
    ];
}

/**
 * @return array{status: string, description: string}
 */
function turnstile_activate(): array
{
    $missing = [];

    if (PHP_VERSION_ID < 80000) {
        $missing[] = 'PHP 8.0 or newer';
    }

    if (!function_exists('curl_init')) {
        $missing[] = 'the cURL extension';
    }

    if (!function_exists('json_decode')) {
        $missing[] = 'the JSON extension';
    }

    if ($missing !== []) {
        return [
            'status' => 'error',
            'description' => 'This module requires ' . implode(', ', $missing) . '.',
        ];
    }

    return [
        'status' => 'success',
        'description' => 'Add your Turnstile site and secret keys, then choose which forms to protect.',
    ];
}

/**
 * @return array{status: string, description: string}
 */
function turnstile_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'Challenges are no longer applied to any form.',
    ];
}

/**
 * @param array<string, mixed> $vars supplied by WHMCS, not needed by this page
 */
function turnstile_output(array $vars): void
{
    echo StatusPage::render();
}
