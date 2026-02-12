<?php
/**
 * Stations service.
 * Single responsibility: CRUD for physical QR-code stations + public lookup.
 *
 * Exports: admin_stations_list, admin_stations_upsert,
 *          admin_stations_delete, public_station_get.
 */

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/audit.php';

/**
 * Lists all stations with their assigned questionnaire name.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_stations_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT s.*, q.name AS questionnaire_name, q.questionnaire_key
         FROM stations s
         LEFT JOIN questionnaires q ON q.id = s.questionnaire_id
         ORDER BY s.name ASC'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Creates or updates a station.
 *
 * @param PDO      $pdo
 * @param int|null $adminUserId
 * @param array    $data
 * @return array
 * @throws ApiError
 */
function admin_stations_upsert(PDO $pdo, ?int $adminUserId, array $data): array
{
    $key = trim($data['station_key'] ?? '');
    $name = trim($data['name'] ?? '');

    if (!$key || !$name) {
        throw new ApiError('Station key and name are required', 400);
    }

    $id = isset($data['id']) ? intval($data['id']) : null;
    $description = $data['description'] ?? null;
    $floorIndex = intval($data['floor_index'] ?? 0);
    $cameraX = floatval($data['camera_x'] ?? 0);
    $cameraY = floatval($data['camera_y'] ?? 0);
    $cameraZ = floatval($data['camera_z'] ?? 0);
    $targetX = floatval($data['target_x'] ?? 0);
    $targetY = floatval($data['target_y'] ?? 0);
    $targetZ = floatval($data['target_z'] ?? 0);
    $questionnaireId = isset($data['questionnaire_id']) ? intval($data['questionnaire_id']) : null;
    $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;

    if ($id) {
        return update_station($pdo, $adminUserId, $id, $key, $name, $description, $floorIndex,
            $cameraX, $cameraY, $cameraZ, $targetX, $targetY, $targetZ, $questionnaireId, $isActive);
    }

    return create_station($pdo, $adminUserId, $key, $name, $description, $floorIndex,
        $cameraX, $cameraY, $cameraZ, $targetX, $targetY, $targetZ, $questionnaireId, $isActive);
}

/**
 * Deletes a station.
 *
 * @param PDO      $pdo
 * @param int|null $adminUserId
 * @param int      $id
 * @return array
 * @throws ApiError
 */
function admin_stations_delete(PDO $pdo, ?int $adminUserId, int $id): array
{
    $row = $pdo->prepare('SELECT * FROM stations WHERE id = :id');
    $row->execute(['id' => $id]);
    $station = $row->fetch(PDO::FETCH_ASSOC);

    if (!$station) {
        throw new ApiError('Station not found', 404);
    }

    $pdo->prepare('DELETE FROM stations WHERE id = :id')->execute(['id' => $id]);

    log_admin_action($pdo, $adminUserId, 'delete_station', "station:$id", [
        'key' => $station['station_key'],
    ]);

    return ['ok' => true];
}

/**
 * Public: looks up a station by key and returns camera/target + questionnaire info.
 *
 * @param PDO    $pdo
 * @param string $stationKey
 * @return array
 * @throws ApiError
 */
function public_station_get(PDO $pdo, string $stationKey): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, q.questionnaire_key
         FROM stations s
         LEFT JOIN questionnaires q ON q.id = s.questionnaire_id
         WHERE s.station_key = :key
         LIMIT 1'
    );
    $stmt->execute(['key' => $stationKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new ApiError("Station not found: $stationKey", 404);
    }
    if (!intval($row['is_active'])) {
        throw new ApiError("Station is inactive: $stationKey", 404);
    }

    return [
        'station_key' => $row['station_key'],
        'name' => $row['name'],
        'floor_index' => intval($row['floor_index']),
        'camera' => [
            'x' => floatval($row['camera_x']),
            'y' => floatval($row['camera_y']),
            'z' => floatval($row['camera_z']),
        ],
        'target' => [
            'x' => floatval($row['target_x']),
            'y' => floatval($row['target_y']),
            'z' => floatval($row['target_z']),
        ],
        'questionnaire_key' => $row['questionnaire_key'] ?? 'default',
    ];
}

// ── Internal ────────────────────────────────────────────────────

function create_station(PDO $pdo, ?int $adminUserId, string $key, string $name, ?string $description,
    int $floorIndex, float $cx, float $cy, float $cz, float $tx, float $ty, float $tz,
    ?int $questionnaireId, int $isActive): array
{
    // Check unique key
    $existing = $pdo->prepare('SELECT COUNT(*) FROM stations WHERE station_key = :key');
    $existing->execute(['key' => $key]);
    if (intval($existing->fetchColumn()) > 0) {
        throw new ApiError("Station key already exists: $key", 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stations (station_key, name, description, floor_index,
            camera_x, camera_y, camera_z, target_x, target_y, target_z,
            questionnaire_id, is_active)
         VALUES (:key, :name, :desc, :floor,
            :cx, :cy, :cz, :tx, :ty, :tz,
            :qid, :active)'
    );
    $stmt->execute([
        'key' => $key, 'name' => $name, 'desc' => $description, 'floor' => $floorIndex,
        'cx' => $cx, 'cy' => $cy, 'cz' => $cz,
        'tx' => $tx, 'ty' => $ty, 'tz' => $tz,
        'qid' => $questionnaireId, 'active' => $isActive,
    ]);

    $id = intval($pdo->lastInsertId());

    log_admin_action($pdo, $adminUserId, 'create_station', "station:$id", [
        'key' => $key, 'name' => $name,
    ]);

    return ['ok' => true, 'id' => $id];
}

function update_station(PDO $pdo, ?int $adminUserId, int $id, string $key, string $name, ?string $description,
    int $floorIndex, float $cx, float $cy, float $cz, float $tx, float $ty, float $tz,
    ?int $questionnaireId, int $isActive): array
{
    // Check unique key (exclude self)
    $existing = $pdo->prepare('SELECT COUNT(*) FROM stations WHERE station_key = :key AND id != :id');
    $existing->execute(['key' => $key, 'id' => $id]);
    if (intval($existing->fetchColumn()) > 0) {
        throw new ApiError("Station key already exists: $key", 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE stations SET station_key = :key, name = :name, description = :desc, floor_index = :floor,
            camera_x = :cx, camera_y = :cy, camera_z = :cz,
            target_x = :tx, target_y = :ty, target_z = :tz,
            questionnaire_id = :qid, is_active = :active
         WHERE id = :id'
    );
    $stmt->execute([
        'key' => $key, 'name' => $name, 'desc' => $description, 'floor' => $floorIndex,
        'cx' => $cx, 'cy' => $cy, 'cz' => $cz,
        'tx' => $tx, 'ty' => $ty, 'tz' => $tz,
        'qid' => $questionnaireId, 'active' => $isActive, 'id' => $id,
    ]);

    log_admin_action($pdo, $adminUserId, 'update_station', "station:$id", [
        'key' => $key, 'name' => $name,
    ]);

    return ['ok' => true, 'id' => $id];
}
