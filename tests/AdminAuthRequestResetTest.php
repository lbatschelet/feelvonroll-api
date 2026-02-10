<?php
/**
 * Tests for admin_auth_request_reset function.
 * Note: We don't test actual email sending here; we test the token generation
 * and the "never reveal if email exists" behavior.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/jwt.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../services/admin_auth_service.php';

final class AdminAuthRequestResetTest extends TestCase
{
    private $pdo;
    private $config;

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
                must_set_password INTEGER DEFAULT 0,
                reset_token_hash TEXT,
                reset_token_expires TEXT,
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
        $this->pdo->exec(
            "INSERT INTO admin_users (email, name, first_name, last_name, is_admin, token_version)
             VALUES ('test@example.com', 'Test User', 'Test', 'User', 0, 1)"
        );

        // Config without real SMTP - email will fail silently which is expected
        $this->config = [
            'app_url' => 'https://admin.test.ch',
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'smtp_user' => '',
            'smtp_pass' => '',
        ];
    }

    public function testReturnsOkForExistingEmail(): void
    {
        $result = admin_auth_request_reset($this->pdo, $this->config, 'test@example.com');
        $this->assertSame(['ok' => true], $result);
    }

    public function testReturnsOkForNonExistingEmail(): void
    {
        $result = admin_auth_request_reset($this->pdo, $this->config, 'nonexistent@example.com');
        $this->assertSame(['ok' => true], $result);
    }

    public function testReturnsOkForEmptyEmail(): void
    {
        $result = admin_auth_request_reset($this->pdo, $this->config, '');
        $this->assertSame(['ok' => true], $result);
    }

    public function testReturnsOkForInvalidEmail(): void
    {
        $result = admin_auth_request_reset($this->pdo, $this->config, 'not-an-email');
        $this->assertSame(['ok' => true], $result);
    }

    public function testGeneratesTokenForExistingUser(): void
    {
        admin_auth_request_reset($this->pdo, $this->config, 'test@example.com');

        $stmt = $this->pdo->query('SELECT reset_token_hash, reset_token_expires, must_set_password FROM admin_users WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['reset_token_hash']);
        $this->assertNotNull($row['reset_token_expires']);
        $this->assertSame(1, (int) $row['must_set_password']);
    }

    public function testDoesNotGenerateTokenForNonExistingUser(): void
    {
        admin_auth_request_reset($this->pdo, $this->config, 'nonexistent@example.com');

        $stmt = $this->pdo->query('SELECT reset_token_hash FROM admin_users WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($row['reset_token_hash']);
    }
}
