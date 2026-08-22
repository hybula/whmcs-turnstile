<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Registers a PSR-4 autoloader for the module namespace.
 *
 * WHMCS autoloads addon module classes from the lib directory, but the
 * behaviour differs between releases. Registering our own loader keeps the
 * module self contained and is a no-op when WHMCS already resolved the class.
 */
final class Bootstrap
{
    private const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        spl_autoload_register(static function (string $class): void {
            $prefixLength = strlen(self::NAMESPACE_PREFIX);
            if (strncmp($class, self::NAMESPACE_PREFIX, $prefixLength) !== 0) {
                return;
            }

            $relativePath = str_replace('\\', '/', substr($class, $prefixLength));
            $file = __DIR__ . '/' . $relativePath . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
