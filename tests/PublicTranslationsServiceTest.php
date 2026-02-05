<?php
/**
 * Tests for public translations service helpers.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/public_translations_service.php';

final class PublicTranslationsServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testPublicTranslationsListFiltersPrefix(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE translations (translation_key TEXT, lang TEXT, text TEXT)');
        $pdo->exec("INSERT INTO translations (translation_key, lang, text) VALUES
            ('questions.a.label', 'de', 'A'),
            ('questions.b.label', 'de', 'B'),
            ('options.a.x', 'de', 'X'),
            ('questions.a.label', 'en', 'A-en')
        ");

        $result = public_translations_list($pdo, 'de', 'questions.');

        $this->assertSame(['questions.a.label' => 'A', 'questions.b.label' => 'B'], $result);
    }
}
