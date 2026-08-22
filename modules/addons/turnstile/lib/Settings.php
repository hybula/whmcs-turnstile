<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

use Throwable;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Typed access to the addon module configuration.
 *
 * Settings live in the WHMCS admin interface rather than in configuration.php,
 * so they can be changed without editing files and without a deployment.
 */
final class Settings
{
    public const MODULE_NAME = 'turnstile';
    public const VERSION = '1.0.0';

    public const DEFAULT_ERROR_MESSAGE =
        'We could not verify that you are human. Please complete the challenge and try again.';

    private const THEMES = ['auto', 'light', 'dark'];
    private const SIZES = ['normal', 'flexible', 'compact'];

    private const TRUTHY = ['on', '1', 'yes', 'true'];

    private static ?self $instance = null;

    /**
     * @param array<string, string> $values
     */
    private function __construct(private array $values)
    {
    }

    public static function load(): self
    {
        return self::$instance ??= new self(self::fetch());
    }

    /**
     * Discards the cached instance. Only needed by long running processes.
     */
    public static function flush(): void
    {
        self::$instance = null;
    }

    public function siteKey(): string
    {
        return $this->string('siteKey');
    }

    public function secretKey(): string
    {
        return $this->string('secretKey');
    }

    public function isConfigured(): bool
    {
        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    public function theme(): string
    {
        return $this->enum('theme', self::THEMES, 'auto');
    }

    public function size(): string
    {
        return $this->enum('size', self::SIZES, 'normal');
    }

    public function errorMessage(): string
    {
        $message = $this->string('errorMessage');

        return $message !== '' ? $message : self::DEFAULT_ERROR_MESSAGE;
    }

    public function protects(string $location): bool
    {
        $key = Location::settingKey($location);

        return $key !== '' && $this->bool($key);
    }

    public function skipAuthenticated(): bool
    {
        return $this->bool('skipAuthenticated');
    }

    /**
     * Whether a submission is accepted when Cloudflare cannot be reached.
     */
    public function failOpen(): bool
    {
        return $this->bool('failOpen');
    }

    public function sendVisitorIp(): bool
    {
        return $this->bool('sendVisitorIp');
    }

    public function logFailures(): bool
    {
        return $this->bool('logFailures');
    }

    /**
     * @return array<string, string>
     */
    private static function fetch(): array
    {
        try {
            $rows = Capsule::table('tbladdonmodules')
                ->where('module', self::MODULE_NAME)
                ->get(['setting', 'value']);
        } catch (Throwable $exception) {
            Logger::activity('Unable to read module settings: ' . $exception->getMessage());

            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $setting = $row['setting'] ?? null;
            if (is_string($setting) && $setting !== '') {
                $settings[$setting] = (string) ($row['value'] ?? '');
            }
        }

        return $settings;
    }

    private function string(string $key): string
    {
        return trim($this->values[$key] ?? '');
    }

    private function bool(string $key): bool
    {
        return in_array(strtolower($this->string($key)), self::TRUTHY, true);
    }

    /**
     * @param list<string> $allowed
     */
    private function enum(string $key, array $allowed, string $default): string
    {
        $value = strtolower($this->string($key));

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
