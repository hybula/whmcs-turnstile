<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * The protected form locations and the detection rules for each of them.
 */
final class Location
{
    public const LOGIN = 'login';
    public const REGISTER = 'register';
    public const PASSWORD_RESET = 'reset';
    public const CONTACT = 'contact';
    public const TICKET = 'ticket';
    public const CHECKOUT = 'checkout';

    /**
     * Locations WHMCS exposes no validation hook for. These are verified by an
     * early request interceptor instead.
     */
    public const INTERCEPTED = [
        self::LOGIN,
        self::PASSWORD_RESET,
        self::CONTACT,
    ];

    private const SETTING_KEYS = [
        self::LOGIN => 'protectLogin',
        self::REGISTER => 'protectRegister',
        self::PASSWORD_RESET => 'protectPasswordReset',
        self::CONTACT => 'protectContact',
        self::TICKET => 'protectTicket',
        self::CHECKOUT => 'protectCheckout',
    ];

    private const LABELS = [
        self::LOGIN => 'Login',
        self::REGISTER => 'Registration',
        self::PASSWORD_RESET => 'Password reset',
        self::CONTACT => 'Contact form',
        self::TICKET => 'Ticket submission',
        self::CHECKOUT => 'Cart checkout',
    ];

    /**
     * CSS selectors used client side to locate the form for each location.
     * The first match wins; the script falls back to a heuristic when none
     * of them match, so a customised template does not break the widget.
     *
     * @var array<string, list<string>>
     */
    private const FORM_SELECTORS = [
        self::LOGIN => [
            'form[action*="dologin"]',
            'form[action*="login/validate"]',
            'form#login',
            'form input[name="password"]',
        ],
        self::REGISTER => [
            'form[action*="register"]',
            'form#frmCheckout',
            'form input[name="password2"]',
        ],
        self::PASSWORD_RESET => [
            'form[action*="password/reset"]',
            'form[action*="pwreset"]',
        ],
        self::CONTACT => [
            'form[action*="contact"]',
            'form#frmContact',
        ],
        self::TICKET => [
            'form#frmSubmitTicket',
            'form[action*="submitticket"]',
        ],
        self::CHECKOUT => [
            'form#frmCheckout',
            'form[action*="checkout"]',
        ],
    ];

    /**
     * Selectors for the submit control the widget should be placed above.
     *
     * @var list<string>
     */
    private const SUBMIT_SELECTORS = [
        '#login',
        '#btnCompleteOrder',
        '#openTicketSubmit',
        'button[type="submit"]',
        'input[type="submit"]',
    ];

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::SETTING_KEYS);
    }

    public static function settingKey(string $location): string
    {
        return self::SETTING_KEYS[$location] ?? '';
    }

    public static function label(string $location): string
    {
        return self::LABELS[$location] ?? $location;
    }

    /**
     * @return list<string>
     */
    public static function formSelectors(string $location): array
    {
        return self::FORM_SELECTORS[$location] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function submitSelectors(): array
    {
        return self::SUBMIT_SELECTORS;
    }

    /**
     * Identifies the location from the raw request. Used when validating a
     * submission, where template variables are not available.
     */
    public static function fromRequest(): ?string
    {
        $script = Request::scriptBasename();
        $uri = Request::uri();

        // A username/password pair is the most reliable signal for a login
        // attempt, regardless of how the installation routes the request.
        if ($script === 'dologin' || Request::hasPost('username', 'password')) {
            return self::LOGIN;
        }

        if ($script === 'register' || str_contains($uri, '/register')) {
            return self::REGISTER;
        }

        if ($script === 'contact' || str_contains($uri, '/contact')) {
            return self::CONTACT;
        }

        if ($script === 'submitticket' || str_contains($uri, 'submitticket') || str_contains($uri, '/ticket/submit')) {
            return self::TICKET;
        }

        if ($script === 'cart' && Request::query('a') === 'checkout') {
            return self::CHECKOUT;
        }

        if ($script === 'pwreset' || str_contains($uri, 'password/reset') || str_contains($uri, 'pwreset')) {
            return self::PASSWORD_RESET;
        }

        return null;
    }

    /**
     * Identifies the location from client area template variables. Preferred
     * when rendering, because it is unaffected by friendly URL rewriting.
     *
     * @param array<string, mixed> $vars
     */
    public static function fromClientAreaVars(array $vars): ?string
    {
        $candidates = [];
        foreach (['templatefile', 'filename'] as $key) {
            $value = $vars[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $candidates[] = strtolower($value);
            }
        }

        if (in_array('login', $candidates, true)) {
            return self::LOGIN;
        }

        if (in_array('register', $candidates, true)) {
            return self::REGISTER;
        }

        if (in_array('contact', $candidates, true)) {
            return self::CONTACT;
        }

        if (array_intersect(['submitticket', 'supportticketsubmit'], $candidates) !== []) {
            return self::TICKET;
        }

        if (in_array('cart', $candidates, true) && Request::query('a') === 'checkout') {
            return self::CHECKOUT;
        }

        if (in_array('pwreset', $candidates, true)) {
            return self::PASSWORD_RESET;
        }

        return self::fromPageTitle($vars);
    }

    /**
     * Last resort matching against the translated page title, which is how
     * WHMCS distinguishes some pages that share an entry point.
     *
     * @param array<string, mixed> $vars
     */
    private static function fromPageTitle(array $vars): ?string
    {
        $pageTitle = $vars['pagetitle'] ?? null;
        $language = $vars['LANG'] ?? null;

        if (!is_string($pageTitle) || $pageTitle === '' || !is_array($language)) {
            return null;
        }

        $titles = [
            self::LOGIN => $language['login'] ?? null,
            self::PASSWORD_RESET => $language['pwreset'] ?? null,
        ];

        foreach ($titles as $location => $title) {
            if (is_string($title) && $title !== '' && $title === $pageTitle) {
                return $location;
            }
        }

        return null;
    }
}
