<?php
/**
 * Admin users service for CRUD and reset flows.
 * Exports: admin_users_list, admin_users_create, admin_users_reset, admin_users_update,
 *          admin_users_update_self, admin_users_delete.
 */

/**
 * Returns admin users list.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_users_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, email, first_name, last_name, is_admin, must_set_password, last_login_at, created_at
         FROM admin_users
         ORDER BY created_at ASC'
    );
    return $stmt->fetchAll();
}

/**
 * Creates an admin user or reset token.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $role
 * @param array $data
 * @return array
 * @throws ApiError
 */
function admin_users_create(PDO $pdo, ?int $userId, string $role, array $data): array
{
    $email = isset($data['email']) ? trim($data['email']) : '';
    $firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
    $lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $isAdmin = isset($data['is_admin']) ? intval((bool)$data['is_admin']) : 0;
    if (!$firstName && $name) {
        [$firstName, $lastName] = admin_users_split_name($name);
    }
    if (!$email || !$firstName) {
        json_error('Missing first name or email', 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email', 400);
    }
    if ($role === 'bootstrap') {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        if (intval($countStmt->fetchColumn()) > 0) {
            json_error('Bootstrap disabled', 400);
        }
        $isAdmin = 1;
    }
    $resetToken = null;
    $expires = null;
    $hash = null;
    $mustSet = 1;
    if ($password) {
        if (strlen($password) < 8) {
            json_error('Password too short', 400);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $mustSet = 0;
    } else {
        $resetToken = base64url_encode(random_bytes(32));
        $resetHash = hash('sha256', $resetToken);
        $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (email, name, first_name, last_name, is_admin, password_hash, must_set_password, reset_token_hash, reset_token_expires)
         VALUES (:email, :name, :first_name, :last_name, :is_admin, :password_hash, :must_set, :hash, :expires)'
    );
    $stmt->execute([
        'email' => $email,
        'name' => trim($firstName . ' ' . $lastName),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'is_admin' => $isAdmin,
        'password_hash' => $hash,
        'must_set' => $mustSet,
        'hash' => $resetToken ? $resetHash : null,
        'expires' => $expires,
    ]);
    $newId = intval($pdo->lastInsertId());
    log_admin_action($pdo, $userId, 'admin_user_create', 'admin_users', [
        'id' => $newId,
        'email' => $email,
    ]);
    return [
        'id' => $newId,
        'reset_token' => $resetToken,
        'reset_expires' => $expires,
    ];
}

/**
 * Generates a reset token and invalidates tokens for a user.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @return array
 */
function admin_users_reset(PDO $pdo, ?int $userId, int $targetId): array
{
    $resetToken = base64url_encode(random_bytes(32));
    $resetHash = hash('sha256', $resetToken);
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $stmt = $pdo->prepare(
        'UPDATE admin_users
         SET must_set_password = 1,
             reset_token_hash = :hash,
             reset_token_expires = :expires,
             token_version = token_version + 1
         WHERE id = :id'
    );
    $stmt->execute([
        'hash' => $resetHash,
        'expires' => $expires,
        'id' => $targetId,
    ]);
    log_admin_action($pdo, $userId, 'admin_user_reset', 'admin_users', ['id' => $targetId]);
    return ['id' => $targetId, 'reset_token' => $resetToken, 'reset_expires' => $expires];
}

/**
 * Updates user profile and role.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @param string $email
 * @param string $firstName
 * @param string $lastName
 * @param int $isAdmin
 * @return array
 * @throws ApiError
 */
function admin_users_update(
    PDO $pdo,
    ?int $userId,
    int $targetId,
    string $email,
    string $firstName,
    string $lastName,
    int $isAdmin
): array
{
    if (!$firstName) {
        json_error('Missing first name', 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email', 400);
    }
    $check = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email AND id != :id');
    $check->execute(['email' => $email, 'id' => $targetId]);
    if ($check->fetch()) {
        json_error('Email already exists', 400);
    }
    $stmt = $pdo->prepare(
        'UPDATE admin_users
         SET email = :email,
             name = :name,
             first_name = :first_name,
             last_name = :last_name,
             is_admin = :is_admin
         WHERE id = :id'
    );
    $stmt->execute([
        'email' => $email,
        'name' => trim($firstName . ' ' . $lastName),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'is_admin' => $isAdmin,
        'id' => $targetId,
    ]);
    log_admin_action($pdo, $userId, 'admin_user_update', 'admin_users', ['id' => $targetId]);
    return ['ok' => true];
}

/**
 * Deletes a user.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @return array
 */
function admin_users_delete(PDO $pdo, ?int $userId, int $targetId): array
{
    $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $targetId]);
    log_admin_action($pdo, $userId, 'admin_user_delete', 'admin_users', ['id' => $targetId]);
    return ['ok' => true];
}

/**
 * Updates own profile and optionally password.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param array $data
 * @return array
 * @throws ApiError
 */
function admin_users_update_self(PDO $pdo, int $userId, array $data): array
{
    $email = isset($data['email']) ? trim($data['email']) : '';
    $firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
    $lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $newPasswordConfirm = $data['new_password_confirm'] ?? '';

    if (!$email || !$firstName) {
        json_error('Missing first name or email', 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email', 400);
    }
    $check = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email AND id != :id');
    $check->execute(['email' => $email, 'id' => $userId]);
    if ($check->fetch()) {
        json_error('Email already exists', 400);
    }

    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_error('Invalid user', 401);
    }
    if (!$currentPassword || !password_verify($currentPassword, $user['password_hash'])) {
        json_error('Current password invalid', 403);
    }

    $passwordSql = '';
    $params = [
        'email' => $email,
        'name' => trim($firstName . ' ' . $lastName),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'id' => $userId,
    ];
    if ($newPassword) {
        if ($newPassword !== $newPasswordConfirm) {
            json_error('New passwords do not match', 400);
        }
        if (strlen($newPassword) < 8) {
            json_error('Password too short', 400);
        }
        $passwordSql = ', password_hash = :password_hash, token_version = token_version + 1';
        $params['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $update = $pdo->prepare(
        "UPDATE admin_users
         SET email = :email,
             name = :name,
             first_name = :first_name,
             last_name = :last_name
             $passwordSql
         WHERE id = :id"
    );
    $update->execute($params);
    log_admin_action($pdo, $userId, 'admin_user_self_update', 'admin_users', ['id' => $userId]);
    return ['ok' => true];
}

/**
 * Splits a full name into first/last.
 *
 * @param string $name
 * @return array
 */
function admin_users_split_name(string $name): array
{
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0] ?? '';
    $last = '';
    if (count($parts) > 1) {
        array_shift($parts);
        $last = trim(implode(' ', $parts));
    }
    return [$first, $last];
}
