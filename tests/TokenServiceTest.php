<?php
/**
 * Tests for token_service: generate_reset_token.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/jwt.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../services/token_service.php';

final class TokenServiceTest extends TestCase
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
    }

    public function testGenerateResetTokenReturnsTokenAndExpiry(): void
    {
        $result = generate_reset_token($this->pdo, 1);

        $this->assertArrayHasKey('reset_token', $result);
        $this->assertArrayHasKey('reset_expires', $result);
        $this->assertNotEmpty($result['reset_token']);
        $this->assertNotEmpty($result['reset_expires']);
    }

    public function testGenerateResetTokenStoresHashInDatabase(): void
    {
        $result = generate_reset_token($this->pdo, 1);

        $stmt = $this->pdo->query('SELECT reset_token_hash, reset_token_expires, must_set_password, token_version FROM admin_users WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['reset_token_hash']);
        $this->assertNotNull($row['reset_token_expires']);
        $this->assertSame(1, (int) $row['must_set_password']);
        $this->assertSame(2, (int) $row['token_version']); // incremented from 1

        // Verify the stored hash matches the returned token
        $expectedHash = hash('sha256', $result['reset_token']);
        $this->assertSame($expectedHash, $row['reset_token_hash']);
    }

    public function testGenerateResetTokenRespectsCustomExpiry(): void
    {
        $result = generate_reset_token($this->pdo, 1, 1); // 1 hour

        $expires = strtotime($result['reset_expires']);
        $now = time();

        // Should expire within roughly 1 hour (allow 60 seconds tolerance)
        $this->assertGreaterThan($now, $expires);
        $this->assertLessThan($now + 3660, $expires);
    }

    public function testGenerateResetTokenDefaultsTo24Hours(): void
    {
        $result = generate_reset_token($this->pdo, 1);

        $expires = strtotime($result['reset_expires']);
        $now = time();

        // Should expire within roughly 24 hours
        $this->assertGreaterThan($now + 23 * 3600, $expires);
        $this->assertLessThan($now + 25 * 3600, $expires);
    }
}
