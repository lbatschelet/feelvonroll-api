<?php
/**
 * Admin auth service for login and token operations.
 * Exports: admin_auth_status, admin_auth_bootstrap_login, admin_auth_login,
 *          admin_auth_set_password, admin_auth_request_reset, admin_auth_refresh.
 */
require_once __DIR__ . '/token_service.php';
require_once __DIR__ . '/email_service.php';

/**
 * Returns admin user count and bootstrap status.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_auth_status(PDO $pdo): array
{
    $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
    $count = intval($countStmt->fetchColumn());
    return ['user_count' => $count, 'bootstrap_required' => $count === 0];
}

/**
 * Validates bootstrap token and returns JWT.
 *
 * @param PDO $pdo
 * @param array $config
 * @param string $requestToken
 * @return array
 * @throws ApiError
 */
function admin_auth_bootstrap_login(PDO $pdo, array $config, string $requestToken): array
{
    $secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';
    if (!$secret) {
        json_error('JWT secret missing', 500);
    }
    $adminToken = $config['admin_token'] ?? '';
    $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
    $count = intval($countStmt->fetchColumn());
    if ($count > 0) {
        json_error('Bootstrap disabled', 400);
    }
    if (!$adminToken || !$requestToken || !hash_equals($adminToken, $requestToken)) {
        json_error('Unauthorized', 401);
    }
    $token = jwt_encode(['role' => 'bootstrap', 'user_id' => 0], $secret);
    return ['token' => $token, 'bootstrap' => true];
}

/**
 * Authenticates admin user and returns JWT.
 *
 * @param PDO $pdo
 * @param array $config
 * @param string $email
 * @param string $password
 * @return array
 * @throws ApiError
 */
function admin_auth_login(PDO $pdo, array $config, string $email, string $password): array
{
    $secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';
    if (!$secret) {
        json_error('JWT secret missing', 500);
    }
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$user['password_hash']) {
        json_error('Invalid credentials', 401);
    }
    if (intval($user['must_set_password']) === 1) {
        json_error('Password reset required', 403);
    }
    if (!password_verify($password, $user['password_hash'])) {
        json_error('Invalid credentials', 401);
    }
    $token = jwt_encode(
        [
            'user_id' => intval($user['id']),
            'email' => $user['email'],
            'is_admin' => intval($user['is_admin'] ?? 0),
            'tv' => intval($user['token_version']),
        ],
        $secret
    );
    $stmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => intval($user['id'])]);
    return [
        'token' => $token,
        'user' => [
            'id' => intval($user['id']),
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'is_admin' => intval($user['is_admin'] ?? 0),
        ],
    ];
}

/**
 * Sets a new password based on reset token.
 *
 * @param PDO $pdo
 * @param string $resetToken
 * @param string $password
 * @return array
 * @throws ApiError
 */
/**
 * Validates a reset token and returns the associated email.
 *
 * @param PDO    $pdo
 * @param string $resetToken
 * @return array
 */
function admin_auth_validate_token(PDO $pdo, string $resetToken): array
{
    $resetHash = hash('sha256', $resetToken);
    $stmt = $pdo->prepare(
        'SELECT email, reset_token_expires
         FROM admin_users
         WHERE reset_token_hash = :hash
         LIMIT 1'
    );
    $stmt->execute(['hash' => $resetHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_error('Invalid reset token', 400);
    }
    $expired = $user['reset_token_expires'] && strtotime($user['reset_token_expires']) < time();
    if ($expired) {
        json_error('Reset token expired', 400);
    }
    return ['ok' => true, 'email' => $user['email']];
}

function admin_auth_set_password(PDO $pdo, string $resetToken, string $password): array
{
    $resetHash = hash('sha256', $resetToken);
    $stmt = $pdo->prepare(
        'SELECT id, reset_token_expires
         FROM admin_users
         WHERE reset_token_hash = :hash
         LIMIT 1'
    );
    $stmt->execute(['hash' => $resetHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_error('Invalid reset token', 400);
    }
    if ($user['reset_token_expires'] && strtotime($user['reset_token_expires']) < time()) {
        json_error('Reset token expired', 400);
    }
    if (strlen($password) < 8) {
        json_error('Password too short', 400);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'UPDATE admin_users
         SET password_hash = :hash,
             must_set_password = 0,
             reset_token_hash = NULL,
             reset_token_expires = NULL,
             token_version = token_version + 1
         WHERE id = :id'
    );
    $stmt->execute(['hash' => $hash, 'id' => intval($user['id'])]);
    return ['ok' => true];
}

/**
 * Handles public password reset request (no auth required).
 * Always returns the same response to prevent email enumeration.
 *
 * @param PDO   $pdo
 * @param array $config
 * @param string $email
 * @return array
 */
function admin_auth_request_reset(PDO $pdo, array $config, string $email): array
{
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true];
    }
    $stmt = $pdo->prepare('SELECT id, first_name FROM admin_users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $result = generate_reset_token($pdo, intval($user['id']));
        $resetLink = rtrim($config['app_url'] ?? '', '/') . '/reset?token=' . $result['reset_token'];
        try {
            send_reset_email($config, $email, $user['first_name'] ?? '', $resetLink, 24);
        } catch (\Throwable $e) {
            // Silently fail – never reveal whether email exists
            error_log('Reset email failed: ' . $e->getMessage());
        }
    }
    return ['ok' => true];
}

/**
 * Refreshes JWT for authenticated admin user.
 *
 * @param PDO $pdo
 * @param array $config
 * @param array $payload
 * @return array
 * @throws ApiError
 */
function admin_auth_refresh(PDO $pdo, array $config, array $payload): array
{
    $secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';
    if (!$secret) {
        json_error('JWT secret missing', 500);
    }
    if (!isset($payload['user_id'])) {
        json_error('Invalid user', 401);
    }
    $stmt = $pdo->prepare('SELECT id, email, is_admin, token_version FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => intval($payload['user_id'])]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_error('Invalid user', 401);
    }
    $token = jwt_encode(
        [
            'user_id' => intval($user['id']),
            'email' => $user['email'] ?? '',
            'is_admin' => intval($user['is_admin'] ?? 0),
            'tv' => intval($user['token_version']),
        ],
        $secret
    );
    return ['token' => $token];
}
