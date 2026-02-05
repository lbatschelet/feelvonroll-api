<?php
/**
 * Public questions service for questionnaire payload.
 * Exports: public_questions_list.
 */

/**
 * Returns public questionnaire data in selected language.
 *
 * @param PDO $pdo
 * @param string $lang
 * @return array
 */
function public_questions_list(PDO $pdo, string $lang): array
{
    $stmt = $pdo->query(
        "SELECT question_key, type, required, sort, is_active, config
         FROM questions
         WHERE is_active = 1
         ORDER BY sort ASC"
    );
    $questions = $stmt->fetchAll();
    if (!$questions) {
        return [];
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

    return $result;
}
