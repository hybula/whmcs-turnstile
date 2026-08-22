<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\Turnstile;

use JsonException;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly.');
}

/**
 * Thin client for the Cloudflare Turnstile siteverify endpoint.
 */
final class Verifier
{
    public const RESPONSE_FIELD = 'cf-turnstile-response';

    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Cloudflare documents tokens of up to 2048 characters. Anything longer is
     * rejected locally instead of being forwarded.
     */
    private const MAX_TOKEN_LENGTH = 2048;

    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private string $secretKey)
    {
    }

    public function verify(string $token, ?string $remoteIp = null): VerificationResult
    {
        if ($this->secretKey === '') {
            return VerificationResult::transportFailure('No secret key is configured.');
        }

        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return VerificationResult::fromApi(false, ['invalid-input-response']);
        }

        if (!function_exists('curl_init')) {
            return VerificationResult::transportFailure('The cURL PHP extension is not available.');
        }

        $payload = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];

        if ($remoteIp !== null) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return VerificationResult::transportFailure('Unable to encode the request: ' . $exception->getMessage());
        }

        return $this->parse($this->send($body));
    }

    /**
     * Confirms that the configured secret key and outbound connectivity work,
     * by submitting a token that is guaranteed to be invalid.
     */
    public function selfTest(): VerificationResult
    {
        return $this->verify('turnstile-connectivity-self-test');
    }

    /**
     * @return array{body: ?string, status: int, error: ?string}
     */
    private function send(string $body): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => self::ENDPOINT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $errorMessage = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'body' => is_string($response) ? $response : null,
            'status' => $status,
            'error' => $errorMessage !== '' ? $errorMessage : null,
        ];
    }

    /**
     * @param array{body: ?string, status: int, error: ?string} $response
     */
    private function parse(array $response): VerificationResult
    {
        if ($response['error'] !== null) {
            return VerificationResult::transportFailure('Request failed: ' . $response['error']);
        }

        $decoded = null;
        try {
            $decoded = json_decode((string) $response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $decoded = null;
        }

        // Cloudflare answers some rejections, such as a malformed secret key,
        // with a 4xx status and a normal result body. The body is therefore
        // authoritative whenever it is well formed.
        if (is_array($decoded) && array_key_exists('success', $decoded)) {
            return VerificationResult::fromApi($decoded['success'] === true, self::errorCodes($decoded));
        }

        if ($response['status'] !== 200) {
            return VerificationResult::transportFailure(
                'Unexpected HTTP status ' . $response['status'] . ' from the verification endpoint.'
            );
        }

        return VerificationResult::transportFailure('Unreadable response from the verification endpoint.');
    }

    /**
     * @param array<mixed> $decoded
     * @return list<string>
     */
    private static function errorCodes(array $decoded): array
    {
        $errorCodes = [];

        foreach ((array) ($decoded['error-codes'] ?? []) as $code) {
            if (is_string($code) && $code !== '') {
                $errorCodes[] = $code;
            }
        }

        return $errorCodes;
    }
}
