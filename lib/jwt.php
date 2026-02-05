<?php
/**
 * JWT helpers for encoding and decoding tokens.
 * Exports: base64url_encode, base64url_decode, jwt_encode, jwt_decode.
 */

/**
 * Encodes data using base64url.
 *
 * @param string $data
 * @return string
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decodes base64url data.
 *
 * @param string $data
 * @return string
 */
function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Encodes payload into a JWT string.
 *
 * @param array $payload
 * @param string $secret
 * @param int $expiresIn
 * @return string
 */
function jwt_encode(array $payload, string $secret, int $expiresIn = 28800): string
{
    $now = time();
    $payload['iat'] = $payload['iat'] ?? $now;
    $payload['exp'] = $payload['exp'] ?? ($now + $expiresIn);
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

/**
 * Decodes a JWT token and validates signature/exp.
 *
 * @param string $token
 * @param string $secret
 * @return array|null
 */
function jwt_decode(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$headB64, $payloadB64, $sigB64] = $parts;
    $signature = base64url_decode($sigB64);
    $expected = hash_hmac('sha256', $headB64 . '.' . $payloadB64, $secret, true);
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $payload = json_decode(base64url_decode($payloadB64), true);
    if (!$payload || !is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && time() >= intval($payload['exp'])) {
        return null;
    }
    return $payload;
}
