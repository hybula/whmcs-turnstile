<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Wrapper around the WHMCS logging helpers.
 *
 * The helpers are global functions that are not guaranteed to exist in every
 * execution context, so every call is guarded.
 */
final class Logger
{
    private const PREFIX = 'Turnstile Captcha: ';

    private function __construct()
    {
    }

    public static function activity(string $message): void
    {
        if (function_exists('logActivity')) {
            logActivity(self::PREFIX . $message);
        }
    }

    /**
     * Records a verification attempt in the module log, masking the secret key.
     */
    public static function verification(
        string $location,
        VerificationResult $result,
        string $secretKey
    ): void {
        if (!function_exists('logModuleCall')) {
            return;
        }

        logModuleCall(
            Settings::MODULE_NAME,
            'siteverify:' . $location,
            ['location' => $location, 'ip' => Request::clientIp()],
            [
                'success' => $result->isSuccess(),
                'transportFailure' => $result->isTransportFailure(),
                'errorCodes' => $result->errorCodes(),
            ],
            $result->summary(),
            $secretKey !== '' ? [$secretKey] : []
        );
    }
}
