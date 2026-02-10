<?php
/**
 * Token service for generating and storing password reset tokens.
 * Single responsibility: token generation and database persistence.
 * Exports: generate_reset_token.
 */

/**
 * Generates a reset token, stores the hash, and returns the plain token.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int $expiryHours
 * @return array ['reset_token' => string, 'reset_expires' => string]
 */
function generate_reset_token(PDO $pdo, int $userId, int $expiryHours = 24): array
{
    $resetToken = base64url_encode(random_bytes(32));
    $resetHash = hash('sha256', $resetToken);
    $expires = date('Y-m-d H:i:s', time() + $expiryHours * 3600);

    $stmt = $pdo->prepare(
        'UPDATE admin_users
         SET must_set_password = 1,
             reset_token_hash = :hash,
             reset_token_expires = :expires,
             token_version = token_version + 1
         WHERE id = :id'
    );
    $stmt->execute(['hash' => $resetHash, 'expires' => $expires, 'id' => $userId]);

    return ['reset_token' => $resetToken, 'reset_expires' => $expires];
}
