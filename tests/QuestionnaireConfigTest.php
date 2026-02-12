<?php
/**
 * Tests for questionnaire_config_service: CRUD for questionnaires and slots.
 *
 * TDD: These tests were written before the implementation.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/audit.php';

final class QuestionnaireConfigTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE questions (
                question_key VARCHAR(64) PRIMARY KEY,
                type VARCHAR(16) NOT NULL,
                required INTEGER NOT NULL DEFAULT 0,
                sort INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                config TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE questionnaires (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_key VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(128) NOT NULL,
                description TEXT,
                is_default INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE questionnaire_slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_id INTEGER NOT NULL,
                sort INTEGER NOT NULL DEFAULT 0,
                mode VARCHAR(8) NOT NULL DEFAULT \'fixed\',
                pool_count INTEGER NOT NULL DEFAULT 1,
                required INTEGER NOT NULL DEFAULT 0
            )
        ');

        $this->pdo->exec('
            CREATE TABLE questionnaire_slot_questions (
                slot_id INTEGER NOT NULL,
                question_key VARCHAR(64) NOT NULL,
                PRIMARY KEY (slot_id, question_key)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE admin_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT,
                target TEXT,
                payload TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->seedTestData();

        require_once __DIR__ . '/../services/questionnaire_config_service.php';
    }

    private function seedTestData(): void
    {
        $this->pdo->exec("
            INSERT INTO questions (question_key, type, required, sort, is_active) VALUES
            ('wellbeing', 'slider', 1, 10, 1),
            ('reasons', 'multi', 0, 20, 1),
            ('note', 'text', 0, 30, 1)
        ");

        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('default', 'Default', 1, 1)
        ");
    }

    // ── List tests ────────────────────────────────────────────────

    public function testListReturnsAllQuestionnaires(): void
    {
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('station_a', 'Station A', 0, 1)
        ");

        $result = admin_questionnaires_list($this->pdo);

        $this->assertCount(2, $result);
    }

    public function testListIncludesSlotCount(): void
    {
        $defaultId = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();
        $this->pdo->exec("INSERT INTO questionnaire_slots (questionnaire_id, sort, mode) VALUES ($defaultId, 10, 'fixed')");
        $this->pdo->exec("INSERT INTO questionnaire_slots (questionnaire_id, sort, mode) VALUES ($defaultId, 20, 'fixed')");

        $result = admin_questionnaires_list($this->pdo);

        $this->assertSame(2, intval($result[0]['slot_count']));
    }

    // ── Upsert tests (create) ─────────────────────────────────────

    public function testCreateQuestionnaire(): void
    {
        $result = admin_questionnaires_upsert($this->pdo, null, [
            'questionnaire_key' => 'new_one',
            'name' => 'New Questionnaire',
            'description' => 'A test questionnaire',
            'is_active' => true,
        ]);

        $this->assertTrue($result['ok']);

        $row = $this->pdo->query("SELECT * FROM questionnaires WHERE questionnaire_key = 'new_one'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('New Questionnaire', $row['name']);
    }

    public function testCreateQuestionnaireDuplicateKeyFails(): void
    {
        $this->expectException(ApiError::class);

        admin_questionnaires_upsert($this->pdo, null, [
            'questionnaire_key' => 'default',
            'name' => 'Another Default',
        ]);
    }

    // ── Upsert tests (update) ─────────────────────────────────────

    public function testUpdateQuestionnaire(): void
    {
        $id = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();

        $result = admin_questionnaires_upsert($this->pdo, null, [
            'id' => intval($id),
            'questionnaire_key' => 'default',
            'name' => 'Default Updated',
            'description' => 'Updated description',
            'is_active' => true,
        ]);

        $this->assertTrue($result['ok']);

        $row = $this->pdo->query("SELECT name FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();
        $this->assertSame('Default Updated', $row);
    }

    // ── Delete tests ──────────────────────────────────────────────

    public function testDeleteQuestionnaire(): void
    {
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('deletable', 'Deletable', 0, 1)
        ");
        $id = $this->pdo->lastInsertId();

        $result = admin_questionnaires_delete($this->pdo, null, intval($id));

        $this->assertTrue($result['ok']);

        $count = $this->pdo->query("SELECT COUNT(*) FROM questionnaires WHERE questionnaire_key = 'deletable'")->fetchColumn();
        $this->assertSame(0, intval($count));
    }

    public function testCannotDeleteDefaultQuestionnaire(): void
    {
        $id = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();

        $this->expectException(ApiError::class);

        admin_questionnaires_delete($this->pdo, null, intval($id));
    }

    // ── Slots save tests ──────────────────────────────────────────

    public function testSaveSlotsCreatesNewSlots(): void
    {
        $id = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();

        $result = admin_questionnaire_slots_save($this->pdo, null, intval($id), [
            ['sort' => 10, 'mode' => 'fixed', 'pool_count' => 1, 'required' => true, 'questions' => ['wellbeing']],
            ['sort' => 20, 'mode' => 'fixed', 'pool_count' => 1, 'required' => false, 'questions' => ['reasons']],
        ]);

        $this->assertTrue($result['ok']);

        $slotCount = $this->pdo->query("SELECT COUNT(*) FROM questionnaire_slots WHERE questionnaire_id = $id")->fetchColumn();
        $this->assertSame(2, intval($slotCount));
    }

    public function testSaveSlotsReplacesExistingSlots(): void
    {
        $id = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();

        // First save
        admin_questionnaire_slots_save($this->pdo, null, intval($id), [
            ['sort' => 10, 'mode' => 'fixed', 'pool_count' => 1, 'required' => true, 'questions' => ['wellbeing']],
            ['sort' => 20, 'mode' => 'fixed', 'pool_count' => 1, 'required' => false, 'questions' => ['reasons']],
        ]);

        // Second save with different slots
        admin_questionnaire_slots_save($this->pdo, null, intval($id), [
            ['sort' => 10, 'mode' => 'fixed', 'pool_count' => 1, 'required' => false, 'questions' => ['note']],
        ]);

        $slotCount = $this->pdo->query("SELECT COUNT(*) FROM questionnaire_slots WHERE questionnaire_id = $id")->fetchColumn();
        $this->assertSame(1, intval($slotCount));

        $slotId = $this->pdo->query("SELECT id FROM questionnaire_slots WHERE questionnaire_id = $id")->fetchColumn();
        $qKey = $this->pdo->query("SELECT question_key FROM questionnaire_slot_questions WHERE slot_id = $slotId")->fetchColumn();
        $this->assertSame('note', $qKey);
    }

    public function testSavePoolSlotWithMultipleQuestions(): void
    {
        $id = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'default'")->fetchColumn();

        admin_questionnaire_slots_save($this->pdo, null, intval($id), [
            ['sort' => 10, 'mode' => 'pool', 'pool_count' => 2, 'required' => false, 'questions' => ['wellbeing', 'reasons', 'note']],
        ]);

        $slotId = $this->pdo->query("SELECT id FROM questionnaire_slots WHERE questionnaire_id = $id")->fetchColumn();
        $mode = $this->pdo->query("SELECT mode FROM questionnaire_slots WHERE id = $slotId")->fetchColumn();
        $this->assertSame('pool', $mode);

        $qCount = $this->pdo->query("SELECT COUNT(*) FROM questionnaire_slot_questions WHERE slot_id = $slotId")->fetchColumn();
        $this->assertSame(3, intval($qCount));
    }

    public function testSaveSlotsForNonExistentQuestionnaireFails(): void
    {
        $this->expectException(ApiError::class);

        admin_questionnaire_slots_save($this->pdo, null, 9999, [
            ['sort' => 10, 'mode' => 'fixed', 'pool_count' => 1, 'required' => false, 'questions' => ['wellbeing']],
        ]);
    }
}
