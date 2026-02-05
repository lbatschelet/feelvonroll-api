<?php

function normalize_pin_row(array $row): array
{
    $row['reasons'] = $row['reason_keys'] ? explode(',', $row['reason_keys']) : [];
    unset($row['reason_keys']);
    $row['id'] = intval($row['id']);
    $row['floor_index'] = intval($row['floor_index']);
    $row['position_x'] = floatval($row['position_x']);
    $row['position_y'] = floatval($row['position_y']);
    $row['position_z'] = floatval($row['position_z']);
    $row['wellbeing'] = floatval($row['wellbeing']);
    $row['approved'] = intval($row['approved']);
    $row['group_key'] = $row['group_key'] ?? null;
    return $row;
}

function fetch_translations(PDO $pdo, string $lang, array $keys, string $fallback = 'de'): array
{
    if (empty($keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT translation_key, lang, text
         FROM translations
         WHERE translation_key IN ($placeholders)
           AND (lang = ? OR lang = ?)"
    );
    $params = array_merge($keys, [$lang, $fallback]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $key = $row['translation_key'];
        if (!isset($result[$key]) || $row['lang'] === $lang) {
            $result[$key] = $row['text'];
        }
    }

    return $result;
}
