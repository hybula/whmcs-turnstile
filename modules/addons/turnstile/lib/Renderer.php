<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

use JsonException;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Builds the client area markup that mounts the widget.
 *
 * No inline script is emitted: the behaviour lives in a static asset and the
 * configuration is handed to it through an escaped data attribute.
 */
final class Renderer
{
    private const SCRIPT_ID = 'turnstile-captcha-loader';
    private const ONLOAD_CALLBACK = 'turnstileCaptchaOnLoad';
    private const API_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $vars client area template variables
     */
    public static function clientAreaFooter(array $vars): string
    {
        if (Request::isNonClientAreaContext()) {
            return '';
        }

        $location = Location::fromClientAreaVars($vars) ?? Location::fromRequest();
        if ($location === null || !Challenge::applies($location)) {
            return '';
        }

        $settings = Settings::load();

        $config = [
            'siteKey' => $settings->siteKey(),
            'theme' => $settings->theme(),
            'size' => $settings->size(),
            'action' => $location,
            'responseField' => Verifier::RESPONSE_FIELD,
            'formSelectors' => Location::formSelectors($location),
            'submitSelectors' => Location::submitSelectors(),
            'alert' => Flash::pull(),
        ];

        try {
            $encodedConfig = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            Logger::activity('Unable to build the widget configuration: ' . $exception->getMessage());

            return '';
        }

        return sprintf(
            '<script id="%s" src="%s" data-config="%s" defer></script>' . "\n"
            . '<script src="%s" defer></script>',
            self::SCRIPT_ID,
            self::escape(self::assetUrl($vars)),
            self::escape($encodedConfig),
            self::escape(self::API_URL . '?render=explicit&onload=' . self::ONLOAD_CALLBACK)
        );
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function assetUrl(array $vars): string
    {
        $webRoot = $vars['WEB_ROOT'] ?? null;
        $base = is_string($webRoot) ? rtrim($webRoot, '/') : '';

        return $base . '/modules/addons/' . Settings::MODULE_NAME . '/assets/turnstile.js?v=' . rawurlencode(Settings::VERSION);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
