<?php
/**
 * Tests for questionnaire_resolver_service: resolve_questionnaire.
 *
 * TDD: These tests were written before the implementation.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/translations.php';

final class QuestionnaireResolverTest extends TestCase
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
            CREATE TABLE question_options (
                question_key VARCHAR(64) NOT NULL,
                option_key VARCHAR(64) NOT NULL,
                sort INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (question_key, option_key)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE translations (
                translation_key VARCHAR(128) NOT NULL,
                lang VARCHAR(8) NOT NULL,
                text VARCHAR(255) NOT NULL,
                PRIMARY KEY (translation_key, lang)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE questionnaires (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_key VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(128) NOT NULL,
                description TEXT,
                is_default INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1
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

        $this->seedTestData();

        require_once __DIR__ . '/../services/questionnaire_resolver_service.php';
    }

    private function seedTestData(): void
    {
        // Questions library
        $this->pdo->exec("
            INSERT INTO questions (question_key, type, required, sort, is_active, config) VALUES
            ('wellbeing', 'slider', 1, 10, 1, '{\"min\":0,\"max\":1,\"step\":0.01,\"default\":0.5}'),
            ('reasons', 'multi', 0, 20, 1, '{\"allow_multiple\":true}'),
            ('note', 'text', 0, 30, 1, '{\"rows\":3}'),
            ('prompt_a', 'text', 0, 40, 1, '{\"rows\":3}'),
            ('prompt_b', 'text', 0, 50, 1, '{\"rows\":3}'),
            ('prompt_c', 'text', 0, 60, 1, '{\"rows\":3}'),
            ('inactive_q', 'text', 0, 70, 0, '{\"rows\":3}')
        ");

        // Options for 'reasons'
        $this->pdo->exec("
            INSERT INTO question_options (question_key, option_key, sort, is_active) VALUES
            ('reasons', 'licht', 10, 1),
            ('reasons', 'ruhe', 20, 1),
            ('reasons', 'inactive_opt', 30, 0)
        ");

        // Translations
        $this->pdo->exec("
            INSERT INTO translations (translation_key, lang, text) VALUES
            ('questions.wellbeing.label', 'en', 'How do you feel here?'),
            ('questions.wellbeing.legend_low', 'en', 'Not good'),
            ('questions.wellbeing.legend_high', 'en', 'Very good'),
            ('questions.reasons.label', 'en', 'What contributes?'),
            ('questions.note.label', 'en', 'Any notes?'),
            ('questions.prompt_a.label', 'en', 'Prompt A'),
            ('questions.prompt_b.label', 'en', 'Prompt B'),
            ('questions.prompt_c.label', 'en', 'Prompt C'),
            ('options.reasons.licht', 'en', 'Light'),
            ('options.reasons.ruhe', 'en', 'Quiet')
        ");

        // Questionnaire with fixed slots
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('default', 'Default', 1, 1)
        ");
        $defaultId = $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO questionnaire_slots (questionnaire_id, sort, mode, pool_count, required) VALUES
            ($defaultId, 10, 'fixed', 1, 1),
            ($defaultId, 20, 'fixed', 1, 0),
            ($defaultId, 30, 'fixed', 1, 0)
        ");

        $slots = $this->pdo->query('SELECT id FROM questionnaire_slots ORDER BY sort')->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$slots[0]}, 'wellbeing')");
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$slots[1]}, 'reasons')");
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$slots[2]}, 'note')");

        // Questionnaire with a pool slot
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('with_pool', 'With Pool', 0, 1)
        ");
        $poolId = $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO questionnaire_slots (questionnaire_id, sort, mode, pool_count, required) VALUES
            ($poolId, 10, 'fixed', 1, 1),
            ($poolId, 20, 'pool', 1, 0)
        ");

        $poolSlots = $this->pdo->query("SELECT id FROM questionnaire_slots WHERE questionnaire_id = $poolId ORDER BY sort")->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$poolSlots[0]}, 'wellbeing')");
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$poolSlots[1]}, 'prompt_a')");
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$poolSlots[1]}, 'prompt_b')");
        $this->pdo->exec("INSERT INTO questionnaire_slot_questions (slot_id, question_key) VALUES ({$poolSlots[1]}, 'prompt_c')");

        // Inactive questionnaire
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('inactive', 'Inactive', 0, 0)
        ");
    }

    // ── Fixed slot tests ──────────────────────────────────────────

    public function testFixedSlotAlwaysReturnsSameQuestion(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertCount(3, $result);
        $this->assertSame('wellbeing', $result[0]['key']);
        $this->assertSame('reasons', $result[1]['key']);
        $this->assertSame('note', $result[2]['key']);
    }

    public function testFixedSlotPreservesQuestionType(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertSame('slider', $result[0]['type']);
        $this->assertSame('multi', $result[1]['type']);
        $this->assertSame('text', $result[2]['type']);
    }

    public function testFixedSlotPreservesRequiredFlag(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertTrue($result[0]['required']);
        $this->assertFalse($result[1]['required']);
        $this->assertFalse($result[2]['required']);
    }

    public function testFixedSlotIncludesConfig(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertArrayHasKey('config', $result[0]);
        $this->assertSame(0.5, $result[0]['config']['default']);
    }

    // ── Translation tests ─────────────────────────────────────────

    public function testTranslationsAreResolved(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertSame('How do you feel here?', $result[0]['label']);
        $this->assertSame('What contributes?', $result[1]['label']);
        $this->assertSame('Any notes?', $result[2]['label']);
    }

    public function testSliderLegendTranslations(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertSame('Not good', $result[0]['legend_low']);
        $this->assertSame('Very good', $result[0]['legend_high']);
    }

    public function testMissingTranslationFallsBackToKey(): void
    {
        // Delete a translation to test fallback
        $this->pdo->exec("DELETE FROM translations WHERE translation_key = 'questions.note.label' AND lang = 'en'");

        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $this->assertSame('note', $result[2]['label']);
    }

    // ── Options tests ─────────────────────────────────────────────

    public function testMultiChoiceOptionsIncluded(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $reasons = $result[1];
        $this->assertArrayHasKey('options', $reasons);
        $this->assertCount(2, $reasons['options']); // inactive_opt excluded
    }

    public function testOptionLabelsTranslated(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $options = $result[1]['options'];
        $this->assertSame('Light', $options[0]['label']);
        $this->assertSame('Quiet', $options[1]['label']);
    }

    public function testOptionsSortedCorrectly(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $options = $result[1]['options'];
        $this->assertSame('licht', $options[0]['key']);
        $this->assertSame('ruhe', $options[1]['key']);
    }

    // ── Pool slot tests ───────────────────────────────────────────

    public function testPoolSlotReturnsExactPoolCount(): void
    {
        $result = resolve_questionnaire($this->pdo, 'with_pool', 'en');

        // Slot 1: fixed (wellbeing), Slot 2: pool pick 1 from 3
        $this->assertCount(2, $result);
    }

    public function testPoolSlotSelectsFromAssignedQuestions(): void
    {
        $validKeys = ['prompt_a', 'prompt_b', 'prompt_c'];

        // Run multiple times to account for randomness
        for ($i = 0; $i < 10; $i++) {
            $result = resolve_questionnaire($this->pdo, 'with_pool', 'en');
            $poolQuestion = $result[1];
            $this->assertContains($poolQuestion['key'], $validKeys, "Pool selected unexpected question: {$poolQuestion['key']}");
        }
    }

    public function testPoolSlotDoesNotDuplicate(): void
    {
        // Update pool_count to 2 (pick 2 from 3)
        $this->pdo->exec("
            UPDATE questionnaire_slots SET pool_count = 2
            WHERE mode = 'pool'
        ");

        $result = resolve_questionnaire($this->pdo, 'with_pool', 'en');

        $this->assertCount(3, $result); // 1 fixed + 2 pool
        $poolKeys = [$result[1]['key'], $result[2]['key']];
        $this->assertCount(2, array_unique($poolKeys), 'Pool should not return duplicate questions');
    }

    public function testPoolSlotCapsAtAvailableQuestions(): void
    {
        // Set pool_count higher than available questions
        $this->pdo->exec("
            UPDATE questionnaire_slots SET pool_count = 10
            WHERE mode = 'pool'
        ");

        $result = resolve_questionnaire($this->pdo, 'with_pool', 'en');

        // Should return all 3 pool questions + 1 fixed = 4
        $this->assertCount(4, $result);
    }

    // ── Error handling tests ──────────────────────────────────────

    public function testInactiveQuestionnaireThrowsError(): void
    {
        $this->expectException(ApiError::class);

        resolve_questionnaire($this->pdo, 'inactive', 'en');
    }

    public function testNonExistentQuestionnaireThrowsError(): void
    {
        $this->expectException(ApiError::class);

        resolve_questionnaire($this->pdo, 'does_not_exist', 'en');
    }

    // ── Sort order tests ──────────────────────────────────────────

    public function testQuestionsReturnedInSlotSortOrder(): void
    {
        $result = resolve_questionnaire($this->pdo, 'default', 'en');

        $sorts = [];
        foreach ($result as $i => $q) {
            $sorts[] = $q['sort'];
        }

        $this->assertSame($sorts, array_values(array_unique($sorts)));
        $sortedSorts = $sorts;
        sort($sortedSorts);
        $this->assertSame($sortedSorts, $sorts, 'Questions should be sorted by slot sort order');
    }
}
