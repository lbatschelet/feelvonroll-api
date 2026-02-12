<?php
/**
 * Tests for stations_service: CRUD for stations.
 *
 * TDD: Tests written before implementation.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/audit.php';

final class StationsServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
            CREATE TABLE stations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                station_key VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(128) NOT NULL,
                description TEXT,
                floor_index INTEGER NOT NULL,
                camera_x REAL NOT NULL,
                camera_y REAL NOT NULL,
                camera_z REAL NOT NULL,
                target_x REAL NOT NULL,
                target_y REAL NOT NULL,
                target_z REAL NOT NULL,
                questionnaire_id INTEGER DEFAULT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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

        require_once __DIR__ . '/../services/stations_service.php';
    }

    private function seedTestData(): void
    {
        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('default', 'Default', 1, 1)
        ");

        $this->pdo->exec("
            INSERT INTO questionnaires (questionnaire_key, name, is_default, is_active)
            VALUES ('station_q', 'Station Q', 0, 1)
        ");
    }

    // ── List tests ────────────────────────────────────────────────

    public function testListReturnsEmptyWhenNoStations(): void
    {
        $result = admin_stations_list($this->pdo);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testListReturnsAllStations(): void
    {
        $this->createStation('foyer', 'Foyer');
        $this->createStation('cafe', 'Café');

        $result = admin_stations_list($this->pdo);
        $this->assertCount(2, $result);
    }

    public function testListIncludesQuestionnaireName(): void
    {
        $qId = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'station_q'")->fetchColumn();
        $this->createStation('foyer', 'Foyer', $qId);

        $result = admin_stations_list($this->pdo);

        $this->assertSame('Station Q', $result[0]['questionnaire_name']);
    }

    // ── Create tests ──────────────────────────────────────────────

    public function testCreateStation(): void
    {
        $result = admin_stations_upsert($this->pdo, null, [
            'station_key' => 'entrance',
            'name' => 'Main Entrance',
            'floor_index' => 0,
            'camera_x' => 10.5, 'camera_y' => 5.0, 'camera_z' => 3.2,
            'target_x' => 11.0, 'target_y' => 5.0, 'target_z' => 3.0,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('id', $result);

        $row = $this->pdo->query("SELECT * FROM stations WHERE station_key = 'entrance'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Main Entrance', $row['name']);
        $this->assertEquals(10.5, floatval($row['camera_x']));
    }

    public function testCreateStationRequiresKey(): void
    {
        $this->expectException(ApiError::class);

        admin_stations_upsert($this->pdo, null, [
            'name' => 'No Key',
            'floor_index' => 0,
            'camera_x' => 0, 'camera_y' => 0, 'camera_z' => 0,
            'target_x' => 0, 'target_y' => 0, 'target_z' => 0,
        ]);
    }

    public function testCreateStationDuplicateKeyFails(): void
    {
        $this->createStation('foyer', 'Foyer');

        $this->expectException(ApiError::class);

        admin_stations_upsert($this->pdo, null, [
            'station_key' => 'foyer',
            'name' => 'Another Foyer',
            'floor_index' => 0,
            'camera_x' => 0, 'camera_y' => 0, 'camera_z' => 0,
            'target_x' => 0, 'target_y' => 0, 'target_z' => 0,
        ]);
    }

    public function testCreateStationWithQuestionnaire(): void
    {
        $qId = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'station_q'")->fetchColumn();

        $result = admin_stations_upsert($this->pdo, null, [
            'station_key' => 'lab',
            'name' => 'Lab',
            'floor_index' => 1,
            'camera_x' => 0, 'camera_y' => 0, 'camera_z' => 0,
            'target_x' => 0, 'target_y' => 0, 'target_z' => 0,
            'questionnaire_id' => intval($qId),
        ]);

        $row = $this->pdo->query("SELECT questionnaire_id FROM stations WHERE station_key = 'lab'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($qId, $row['questionnaire_id']);
    }

    // ── Update tests ──────────────────────────────────────────────

    public function testUpdateStation(): void
    {
        $this->createStation('foyer', 'Foyer');
        $id = $this->pdo->lastInsertId();

        $result = admin_stations_upsert($this->pdo, null, [
            'id' => intval($id),
            'station_key' => 'foyer',
            'name' => 'Grand Foyer',
            'floor_index' => 0,
            'camera_x' => 1, 'camera_y' => 2, 'camera_z' => 3,
            'target_x' => 4, 'target_y' => 5, 'target_z' => 6,
        ]);

        $this->assertTrue($result['ok']);
        $row = $this->pdo->query("SELECT name FROM stations WHERE station_key = 'foyer'")->fetchColumn();
        $this->assertSame('Grand Foyer', $row);
    }

    // ── Delete tests ──────────────────────────────────────────────

    public function testDeleteStation(): void
    {
        $this->createStation('foyer', 'Foyer');
        $id = $this->pdo->lastInsertId();

        $result = admin_stations_delete($this->pdo, null, intval($id));

        $this->assertTrue($result['ok']);
        $count = $this->pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn();
        $this->assertSame(0, intval($count));
    }

    public function testDeleteNonExistentStationFails(): void
    {
        $this->expectException(ApiError::class);

        admin_stations_delete($this->pdo, null, 9999);
    }

    // ── Public lookup test ────────────────────────────────────────

    public function testPublicGetStationByKey(): void
    {
        $qId = $this->pdo->query("SELECT id FROM questionnaires WHERE questionnaire_key = 'station_q'")->fetchColumn();
        $this->createStation('foyer', 'Foyer', $qId);

        $result = public_station_get($this->pdo, 'foyer');

        $this->assertSame('foyer', $result['station_key']);
        $this->assertSame('station_q', $result['questionnaire_key']);
        $this->assertArrayHasKey('camera', $result);
        $this->assertArrayHasKey('target', $result);
    }

    public function testPublicGetStationNotFoundThrows(): void
    {
        $this->expectException(ApiError::class);

        public_station_get($this->pdo, 'nonexistent');
    }

    public function testPublicGetInactiveStationThrows(): void
    {
        $this->createStation('foyer', 'Foyer', null, 0);

        $this->expectException(ApiError::class);

        public_station_get($this->pdo, 'foyer');
    }

    // ── Helper ────────────────────────────────────────────────────

    private function createStation(string $key, string $name, ?int $questionnaireId = null, int $isActive = 1): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stations (station_key, name, floor_index, camera_x, camera_y, camera_z, target_x, target_y, target_z, questionnaire_id, is_active)
             VALUES (:key, :name, 0, 0, 0, 0, 0, 0, 0, :qid, :active)'
        );
        $stmt->execute([
            'key' => $key,
            'name' => $name,
            'qid' => $questionnaireId,
            'active' => $isActive,
        ]);
    }
}
