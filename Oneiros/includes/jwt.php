<?php
/**
 * Oneiros — JWT (JSON Web Tokens) implementation
 * Pure PHP, no external libraries needed.
 * Implements HS256 signing.
 */

class JWT
{
    public static function encode(array $payload, string $secret, int $ttlSeconds): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttlSeconds;
        $payload['jti'] = bin2hex(random_bytes(16));

        $headerEnc  = self::base64UrlEncode(json_encode($header));
        $payloadEnc = self::base64UrlEncode(json_encode($payload));
        $signature  = self::sign("$headerEnc.$payloadEnc", $secret);

        return "$headerEnc.$payloadEnc.$signature";
    }

    public static function decode(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;

        [$headerEnc, $payloadEnc, $signature] = $parts;
        $header = json_decode(self::base64UrlDecode($headerEnc), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') return null;

        $expected = self::sign("$headerEnc.$payloadEnc", $secret);
        if (!hash_equals($expected, $signature)) return null;

        $payload = json_decode(self::base64UrlDecode($payloadEnc), true);
        if (!is_array($payload)) return null;

        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || time() >= $payload['exp']) return null;
        if (isset($payload['nbf']) && $payload['nbf'] > time()) return null;

        return $payload;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function sign(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) $data .= str_repeat('=', 4 - $remainder);
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
