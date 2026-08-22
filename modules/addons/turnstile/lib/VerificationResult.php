<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Immutable outcome of a siteverify call.
 *
 * A rejected challenge and an unreachable endpoint are deliberately different
 * states, because the caller applies a different policy to each.
 */
final class VerificationResult
{
    /**
     * @param list<string> $errorCodes
     */
    private function __construct(
        private bool $success,
        private array $errorCodes,
        private ?string $transportError,
    ) {
    }

    /**
     * @param list<string> $errorCodes
     */
    public static function fromApi(bool $success, array $errorCodes): self
    {
        return new self($success, $errorCodes, null);
    }

    public static function transportFailure(string $error): self
    {
        return new self(false, [], $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isTransportFailure(): bool
    {
        return $this->transportError !== null;
    }

    public function transportError(): ?string
    {
        return $this->transportError;
    }

    /**
     * @return list<string>
     */
    public function errorCodes(): array
    {
        return $this->errorCodes;
    }

    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->errorCodes, true);
    }

    public function summary(): string
    {
        if ($this->transportError !== null) {
            return $this->transportError;
        }

        if ($this->success) {
            return 'Challenge passed.';
        }

        return $this->errorCodes === []
            ? 'Challenge rejected without an error code.'
            : 'Challenge rejected: ' . implode(', ', $this->errorCodes) . '.';
    }
}
