<?php
/**
 * Tests for admin_users_service self update.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../services/admin_users_service.php';

final class AdminUsersServiceTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                name TEXT,
                first_name TEXT,
                last_name TEXT,
                is_admin INTEGER DEFAULT 0,
                password_hash TEXT,
                token_version INTEGER DEFAULT 1
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT,
                target TEXT,
                payload TEXT,
                created_at TEXT
            )'
        );
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (email, name, first_name, last_name, is_admin, password_hash, token_version)
             VALUES (:email, :name, :first_name, :last_name, :is_admin, :password_hash, :token_version)'
        );
        $stmt->execute([
            'email' => 'a@b.ch',
            'name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_admin' => 1,
            'password_hash' => $hash,
            'token_version' => 1,
        ]);
    }

    public function testUpdateSelfUpdatesProfileAndPassword(): void
    {
        $result = admin_users_update_self($this->pdo, 1, [
            'email' => 'ada@b.ch',
            'first_name' => 'Ada',
            'last_name' => 'L',
            'current_password' => 'secret123',
            'new_password' => 'newsecret123',
            'new_password_confirm' => 'newsecret123',
        ]);

        $this->assertSame(['ok' => true], $result);
        $stmt = $this->pdo->query('SELECT email, first_name, last_name, token_version FROM admin_users WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ada@b.ch', $row['email']);
        $this->assertSame('Ada', $row['first_name']);
        $this->assertSame('L', $row['last_name']);
        $this->assertSame(2, (int)$row['token_version']);
    }

    public function testUpdateSelfRejectsBadPassword(): void
    {
        $this->expectException(ApiError::class);
        admin_users_update_self($this->pdo, 1, [
            'email' => 'ada@b.ch',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'current_password' => 'wrong',
        ]);
    }
}
