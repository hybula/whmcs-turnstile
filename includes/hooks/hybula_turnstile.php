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

if ((($_SERVER['SCRIPT_NAME'] == '/index.php' && $_GET['rp'] == '/login' && in_array('login', hybulaTurnstileLocations)) ||
        ($_SERVER['SCRIPT_NAME'] == '/register.php' && in_array('register', hybulaTurnstileLocations)) ||
        ($_SERVER['SCRIPT_NAME'] == '/contact.php' && in_array('contact', hybulaTurnstileLocations)) ||
        ($_SERVER['SCRIPT_NAME'] == '/submitticket.php' && in_array('ticket', hybulaTurnstileLocations)) ||
        ($_SERVER['SCRIPT_NAME'] == '/cart.php' && $_GET['a'] == 'checkout' && in_array('checkout', hybulaTurnstileLocations))) && hybulaTurnstileEnabled) {

    if (!empty($_POST)) {
        if (!isset($_POST['cf-turnstile-response'])) {
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
                die(hybulaTurnstileError);
            }
        }
    }

    add_hook('ClientAreaFooterOutput', 1, function ($vars) {
        return '<script>
	var turnstileDiv = document.createElement("div");
	turnstileDiv.innerHTML = \'<div class="cf-turnstile" data-sitekey="'.hybulaTurnstileSite.'" data-callback="javascriptCallback" data-theme="'.hybulaTurnstileTheme.'"></div>'.(hybulaTurnstileCredits ? '<a href="https://github.com/hybula/whmcs-turnstile" target="_blank"><small class="text-muted text-uppercase">Captcha integration by Hybula</small></a>' : '<!-- Captcha integration by Hybula (https://github.com/hybula/whmcs-turnstile) -->').'<br><br>\';
	var form = document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\').parentNode;
	form.insertBefore(turnstileDiv, document.querySelector(\'input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit\'));
	</script>
	<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    });
}
