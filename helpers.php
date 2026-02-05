<?php

function normalize_pin_row(array $row): array
{
    $row['reasons'] = $row['reason_keys'] ? explode(',', $row['reason_keys']) : [];
    unset($row['reason_keys']);
    $row['id'] = intval($row['id']);
    $row['floor_index'] = intval($row['floor_index']);
    $row['position_x'] = floatval($row['position_x']);
    $row['position_y'] = floatval($row['position_y']);
    $row['position_z'] = floatval($row['position_z']);
    $row['wellbeing'] = floatval($row['wellbeing']);
    $row['approved'] = intval($row['approved']);
    $row['group_key'] = $row['group_key'] ?? null;
    return $row;
}

function fetch_translations(PDO $pdo, string $lang, array $keys, string $fallback = 'de'): array
{
    if (empty($keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT translation_key, lang, text
         FROM translations
         WHERE translation_key IN ($placeholders)
           AND (lang = ? OR lang = ?)"
    );
    $params = array_merge($keys, [$lang, $fallback]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $key = $row['translation_key'];
        if (!isset($result[$key]) || $row['lang'] === $lang) {
            $result[$key] = $row['text'];
        }
    }

    return $result;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

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

function require_admin_auth(array $config): array
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';
    if (!$secret) {
        http_response_code(500);
        echo json_encode(['error' => 'JWT secret missing']);
        exit;
    }
    $payload = jwt_decode($matches[1], $secret);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    return $payload;
}

function log_admin_action(PDO $pdo, ?int $userId, string $action, string $target, array $payload = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO admin_audit_logs (user_id, action, target, payload) VALUES (:user_id, :action, :target, :payload)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'target' => $target,
        'payload' => $payload ? json_encode($payload) : null,
    ]);
}
