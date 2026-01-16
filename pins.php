<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $floor = isset($_GET['floor']) ? intval($_GET['floor']) : null;
    $params = [];
    $where = 'WHERE pins.approved = 1';
    if ($floor !== null) {
        $where .= ' AND pins.floor_index = :floor';
        $params['floor'] = $floor;
    }

    $stmt = $pdo->prepare(
        "SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id
         $where
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $result = array_map(function ($row) {
        $row['reasons'] = $row['reason_keys'] ? explode(',', $row['reason_keys']) : [];
        unset($row['reason_keys']);
        $row['id'] = intval($row['id']);
        $row['floor_index'] = intval($row['floor_index']);
        $row['position_x'] = floatval($row['position_x']);
        $row['position_y'] = floatval($row['position_y']);
        $row['position_z'] = floatval($row['position_z']);
        $row['wellbeing'] = intval($row['wellbeing']);
        $row['approved'] = intval($row['approved']);
        return $row;
    }, $rows);
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $floorIndex = isset($data['floor_index']) ? intval($data['floor_index']) : null;
    $x = isset($data['x']) ? floatval($data['x']) : null;
    $y = isset($data['y']) ? floatval($data['y']) : null;
    $z = isset($data['z']) ? floatval($data['z']) : null;
    $wellbeing = isset($data['wellbeing']) ? intval($data['wellbeing']) : null;
    $reasons = isset($data['reasons']) && is_array($data['reasons']) ? $data['reasons'] : [];
    $note = isset($data['note']) ? trim($data['note']) : '';

    if ($floorIndex === null || $x === null || $y === null || $z === null || $wellbeing === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    if (!empty($reasons)) {
        $placeholders = implode(',', array_fill(0, count($reasons), '?'));
        $check = $pdo->prepare("SELECT reason_key FROM reasons WHERE reason_key IN ($placeholders)");
        $check->execute($reasons);
        $existing = $check->fetchAll(PDO::FETCH_COLUMN);
        if (count($existing) !== count(array_unique($reasons))) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid reasons']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pins (floor_index, position_x, position_y, position_z, wellbeing, note)
         VALUES (:floor_index, :position_x, :position_y, :position_z, :wellbeing, :note)'
    );

    $stmt->execute([
        'floor_index' => $floorIndex,
        'position_x' => $x,
        'position_y' => $y,
        'position_z' => $z,
        'wellbeing' => $wellbeing,
        'note' => $note,
    ]);

    $id = $pdo->lastInsertId();
    if (!empty($reasons)) {
        $insert = $pdo->prepare('INSERT INTO pin_reasons (pin_id, reason_key) VALUES (:pin_id, :reason_key)');
        foreach ($reasons as $reasonKey) {
            $insert->execute(['pin_id' => $id, 'reason_key' => $reasonKey]);
        }
    }

    $stmt = $pdo->prepare(
        'SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id
         WHERE pins.id = :id
         GROUP BY pins.id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    $row['reasons'] = $row['reason_keys'] ? explode(',', $row['reason_keys']) : [];
    unset($row['reason_keys']);
    $row['id'] = intval($row['id']);
    $row['floor_index'] = intval($row['floor_index']);
    $row['position_x'] = floatval($row['position_x']);
    $row['position_y'] = floatval($row['position_y']);
    $row['position_z'] = floatval($row['position_z']);
    $row['wellbeing'] = intval($row['wellbeing']);
    $row['approved'] = intval($row['approved']);
    echo json_encode($row);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
