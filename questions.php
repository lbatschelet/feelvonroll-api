<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/helpers.php';

$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'de';
if (!$lang || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $lang)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid lang']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

$stmt = $pdo->query(
    "SELECT question_key, type, required, sort, is_active, config
     FROM questions
     WHERE is_active = 1
     ORDER BY sort ASC"
);
$questions = $stmt->fetchAll();

if (!$questions) {
    echo json_encode([]);
    exit;
}

$questionKeys = array_map(function ($row) {
    return $row['question_key'];
}, $questions);

$optionStmt = $pdo->prepare(
    "SELECT question_key, option_key, sort, is_active
     FROM question_options
     WHERE question_key IN (" . implode(',', array_fill(0, count($questionKeys), '?')) . ")
       AND is_active = 1
     ORDER BY sort ASC"
);
$optionStmt->execute($questionKeys);
$options = $optionStmt->fetchAll();

$optionsByQuestion = [];
foreach ($options as $option) {
    $optionsByQuestion[$option['question_key']][] = $option;
}

$translationKeys = [];
foreach ($questions as $question) {
    $key = $question['question_key'];
    $translationKeys[] = "questions.$key.label";
    if ($question['type'] === 'slider') {
        $translationKeys[] = "questions.$key.legend_low";
        $translationKeys[] = "questions.$key.legend_high";
    }
    if (!empty($optionsByQuestion[$key])) {
        foreach ($optionsByQuestion[$key] as $option) {
            $translationKeys[] = "options.$key." . $option['option_key'];
        }
    }
}

$translations = fetch_translations($pdo, $lang, $translationKeys);

$result = [];
foreach ($questions as $question) {
    $key = $question['question_key'];
    $config = $question['config'] ? json_decode($question['config'], true) : [];
    $entry = [
        'key' => $key,
        'type' => $question['type'],
        'required' => intval($question['required']) === 1,
        'sort' => intval($question['sort']),
        'config' => $config,
        'label' => $translations["questions.$key.label"] ?? $key,
    ];

    if ($question['type'] === 'slider') {
        $entry['legend_low'] = $translations["questions.$key.legend_low"] ?? '';
        $entry['legend_high'] = $translations["questions.$key.legend_high"] ?? '';
    }

    if (!empty($optionsByQuestion[$key])) {
        $entry['options'] = array_map(function ($option) use ($translations, $key) {
            $labelKey = "options.$key." . $option['option_key'];
            return [
                'key' => $option['option_key'],
                'sort' => intval($option['sort']),
                'label' => $translations[$labelKey] ?? $option['option_key'],
            ];
        }, $optionsByQuestion[$key]);
    }

    $result[] = $entry;
}

echo json_encode($result);
