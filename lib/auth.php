<?php
/**
 * Auth helpers for verifying admin JWT tokens.
 * Exports: require_admin_auth.
 */

/**
 * Validates admin JWT and optionally checks token version.
 *
 * @param array $config
 * @param PDO|null $pdo
 * @return array
 * @throws ApiError
 */
function require_admin_auth(array $config, ?PDO $pdo = null): array
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        throw new ApiError('Unauthorized', 401);
    }
    $secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';
    if (!$secret) {
        throw new ApiError('JWT secret missing', 500);
    }
    $payload = jwt_decode($matches[1], $secret);
    if (!$payload) {
        throw new ApiError('Invalid token', 401);
    }
    if (isset($payload['role']) && $payload['role'] === 'bootstrap') {
        return $payload;
    }
    if ($pdo && isset($payload['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, token_version FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => intval($payload['user_id'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ApiError('Invalid user', 401);
        }
        $tokenVersion = intval($payload['tv'] ?? 0);
        if ($tokenVersion !== intval($row['token_version'])) {
            throw new ApiError('Token revoked', 401);
        }
    }
    return $payload;
}
