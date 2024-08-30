<?php
/**
 * Hybula WHMCS Turnstile Hook
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License")
 * and the Commons Clause Restriction; you may not use this file except in
 * compliance with the License.
 *
 * @category   WHMCS
 * @package    whmcs-turnstile
 * @author     Hybula Development <development@hybula.com>
 * @copyright  2023 Hybula B.V.
 * @license    https://github.com/hybula/whmcs-turnstile/blob/main/LICENSE.md
 * @link       https://github.com/hybula/whmcs-turnstile
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly!');
}

// Configuration Constants
const HYBULA_TURNSTILE_ENABLED = true;
const HYBULA_TURNSTILE_CREDITS = true;
const HYBULA_TURNSTILE_EXCLUDE_LOGIN = true;
const HYBULA_TURNSTILE_SITE = '';
const HYBULA_TURNSTILE_SECRET = '';
const HYBULA_TURNSTILE_THEME = 'dark';
const HYBULA_TURNSTILE_SIZE = 'normal';
const HYBULA_TURNSTILE_LOCATIONS = ['login', 'register', 'checkout', 'ticket', 'contact', 'passwordreset'];
const HYBULA_TURNSTILE_ERROR = '<div class="turnstile-error" style="color: red; font-size: 14px; margin-top: 5px;">Captcha verification failed. Please try again.</div>';

/**
 * Sanitize input data
 *
 * @param string $data
 * @return string
 */
function sanitize_input(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Verify Turnstile Captcha
 *
 * @param string $response
 * @return bool
 */
function verify_turnstile(string $response): bool {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'secret' => HYBULA_TURNSTILE_SECRET,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]),
    ]);

    $result = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("Turnstile verification error: $err");
        return false;
    }

    $json = json_decode($result);
    return $json && $json->success;
}

// Process Turnstile validation for specific pages
if (!empty($_POST) && (!isset($_SESSION['uid']) && HYBULA_TURNSTILE_EXCLUDE_LOGIN)) {
    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    $requestUri = $_SERVER['REQUEST_URI'];

    $isLoginPage = ($pageFile == 'index' && isset($_POST['username']) && isset($_POST['password']) && in_array('login', HYBULA_TURNSTILE_LOCATIONS));
    $isRegisterPage = ($pageFile == 'register' && in_array('register', HYBULA_TURNSTILE_LOCATIONS));
    $isContactPage = ($pageFile == 'contact' && in_array('contact', HYBULA_TURNSTILE_LOCATIONS));
    $isTicketPage = ($pageFile == 'submitticket' && in_array('ticket', HYBULA_TURNSTILE_LOCATIONS));
    $isCheckoutPage = ($pageFile == 'cart' && $_GET['a'] == 'checkout' && in_array('checkout', HYBULA_TURNSTILE_LOCATIONS));
    $isPasswordResetPage = (strpos($requestUri, '/password/reset') !== false && in_array('passwordreset', HYBULA_TURNSTILE_LOCATIONS));

    if (($isLoginPage || $isRegisterPage || $isContactPage || $isTicketPage || $isCheckoutPage || $isPasswordResetPage) && HYBULA_TURNSTILE_ENABLED) {
        $turnstileResponse = sanitize_input($_POST['cf-turnstile-response'] ?? '');
        if (!$turnstileResponse || !verify_turnstile($turnstileResponse)) {
            $_SESSION['turnstile_error'] = true;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        }
    }
}

/**
 * Add Turnstile Captcha to specified pages
 *
 * @param array $vars
 * @return string
 */
function add_turnstile_captcha(array $vars): string {
    if (!HYBULA_TURNSTILE_ENABLED || (isset($_SESSION['uid']) && HYBULA_TURNSTILE_EXCLUDE_LOGIN)) {
        return '';
    }

    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    $requestUri = $_SERVER['REQUEST_URI'];

    $shouldInsertCaptcha = (
        (in_array('login', HYBULA_TURNSTILE_LOCATIONS) && $vars['pagetitle'] == $vars['LANG']['login']) ||
        (in_array('register', HYBULA_TURNSTILE_LOCATIONS) && $pageFile == 'register') ||
        (in_array('contact', HYBULA_TURNSTILE_LOCATIONS) && $pageFile == 'contact') ||
        (in_array('ticket', HYBULA_TURNSTILE_LOCATIONS) && $pageFile == 'submitticket') ||
        (in_array('checkout', HYBULA_TURNSTILE_LOCATIONS) && $pageFile == 'cart' && $_GET['a'] == 'checkout') ||
        (strpos($requestUri, '/password/reset') !== false && in_array('passwordreset', HYBULA_TURNSTILE_LOCATIONS))
    );

    if ($shouldInsertCaptcha) {
        $errorMessage = isset($_SESSION['turnstile_error']) ? HYBULA_TURNSTILE_ERROR : '';
        unset($_SESSION['turnstile_error']);

        return '<script>
            var turnstileDiv = document.createElement("div");
            turnstileDiv.innerHTML = \'<div class="cf-turnstile" data-sitekey="'.HYBULA_TURNSTILE_SITE.'" data-callback="javascriptCallback" data-theme="'.HYBULA_TURNSTILE_THEME.'" data-size="'.HYBULA_TURNSTILE_SIZE.'"></div>'.(HYBULA_TURNSTILE_CREDITS ? '' : '<!-- Captcha integration by Hybula (https://github.com/hybula/whmcs-turnstile) -->').'<div id="turnstile-error-message">'.$errorMessage.'</div><br>\';
            var formElement = document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\');
            if (formElement) {
                var form = formElement.parentNode;
                form.insertBefore(turnstileDiv, formElement);
            }
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    return '';
}

add_hook('ClientAreaFooterOutput', 1, 'add_turnstile_captcha');
