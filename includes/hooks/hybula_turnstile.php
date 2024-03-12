<?php
/**
 * Hybula WHMCS Turnstile Hook
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License")
 * and the Commons Clause Restriction; you may not use this file except in
 * compliance with the License.
 *
 * @category   whmcs
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

if (!empty($_POST) && (!isset($_SESSION['uid']) && hybulaTurnstileExcludeLogin)) {
    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    if ((($pageFile == 'index' && isset($_POST['username']) && isset($_POST['password']) && in_array('login', hybulaTurnstileLocations)) ||
            ($pageFile == 'register' && in_array('register', hybulaTurnstileLocations)) ||
            ($pageFile == 'contact' && in_array('contact', hybulaTurnstileLocations)) ||
            ($pageFile == 'submitticket' && in_array('ticket', hybulaTurnstileLocations)) ||
            ($pageFile == 'cart' && $_GET['a'] == 'checkout' && in_array('checkout', hybulaTurnstileLocations))) && hybulaTurnstileEnabled) {
        if (!isset($_POST['cf-turnstile-response'])) {
            unset($_SESSION['uid']);
            die('Missing captcha response in POST data!');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'secret' => hybulaTurnstileSecret,
                'response' => $_POST['cf-turnstile-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($json = json_decode($result)) {
            if (!$json->success) {
                unset($_SESSION['uid']);
                die(hybulaTurnstileError);
            }
        }
    }
}

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    if (!hybulaTurnstileEnabled || (isset($_SESSION['uid']) && hybulaTurnstileExcludeLogin)) {
        return '';
    }
    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    if ((in_array('login', hybulaTurnstileLocations) && $vars['pagetitle'] == $vars['LANG']['login']) ||
        (in_array('register', hybulaTurnstileLocations) && $pageFile == 'register') ||
        (in_array('contact', hybulaTurnstileLocations) && $pageFile == 'contact') ||
        (in_array('ticket', hybulaTurnstileLocations) && $pageFile == 'submitticket') ||
        (in_array('checkout', hybulaTurnstileLocations) && $pageFile == 'cart' && $_GET['a'] == 'checkout')) {
        return '<script>
        var turnstileDiv = document.createElement("div");
        turnstileDiv.innerHTML = \'<div class="cf-turnstile" data-sitekey="'.hybulaTurnstileSite.'" data-callback="javascriptCallback" data-theme="'.hybulaTurnstileTheme.'"></div>'.(hybulaTurnstileCredits ? '<a href="https://github.com/hybula/whmcs-turnstile" target="_blank"><small class="text-muted text-uppercase">Captcha integration by Hybula</small></a>' : '<!-- Captcha integration by Hybula (https://github.com/hybula/whmcs-turnstile) -->').'<br><br>\';
        if (document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\')) {
            var form = document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\').parentNode;
            form.insertBefore(turnstileDiv, document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\'));
        }
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
});
