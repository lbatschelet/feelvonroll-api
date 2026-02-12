<?php
/**
 * Questionnaire resolver service.
 * Single responsibility: resolve a questionnaire key + language into a ready-to-use question list.
 * Handles fixed slots and random pool selection (server-side).
 *
 * Exports: resolve_questionnaire.
 */

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/translations.php';

/**
 * Resolves a questionnaire by key into a fully assembled question list.
 *
 * For each slot:
 *   - fixed: returns the single assigned question
 *   - pool: randomly selects pool_count questions from the assigned set
 *
 * @param PDO    $pdo
 * @param string $questionnaireKey
 * @param string $lang
 * @return array  List of question objects with translations and options resolved.
 * @throws ApiError If questionnaire not found or inactive.
 */
function resolve_questionnaire(PDO $pdo, string $questionnaireKey, string $lang): array
{
    $questionnaire = load_questionnaire($pdo, $questionnaireKey);
    $slots = load_slots($pdo, intval($questionnaire['id']));
    $resolvedSlots = resolve_slots($slots);

    $allKeys = collect_resolved_keys($resolvedSlots);
    if (empty($allKeys)) {
        return [];
    }

    $questions = load_questions_by_keys($pdo, $allKeys);
    $optionsByQuestion = load_options_for_questions($pdo, $allKeys);
    $translations = load_question_translations($pdo, $lang, $questions, $optionsByQuestion);

    return assemble_question_list($resolvedSlots, $questions, $optionsByQuestion, $translations);
}

// ── Internal: load questionnaire ────────────────────────────────

/**
 * @param PDO    $pdo
 * @param string $key
 * @return array
 * @throws ApiError
 */
function load_questionnaire(PDO $pdo, string $key): array
{
    $stmt = $pdo->prepare('SELECT * FROM questionnaires WHERE questionnaire_key = :key LIMIT 1');
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new ApiError("Questionnaire not found: $key", 404);
    }
    if (!intval($row['is_active'])) {
        throw new ApiError("Questionnaire is inactive: $key", 404);
    }

    return $row;
}

// ── Internal: load slots with their assigned questions ──────────

/**
 * Returns slots with an additional 'questions' array of question_keys.
 *
 * @param PDO $pdo
 * @param int $questionnaireId
 * @return array
 */
function load_slots(PDO $pdo, int $questionnaireId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, sort, mode, pool_count, required
         FROM questionnaire_slots
         WHERE questionnaire_id = :qid
         ORDER BY sort ASC'
    );
    $stmt->execute(['qid' => $questionnaireId]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($slots)) {
        return [];
    }

    $slotIds = array_map(fn($s) => intval($s['id']), $slots);
    $placeholders = implode(',', array_fill(0, count($slotIds), '?'));
    $sqStmt = $pdo->prepare(
        "SELECT slot_id, question_key
         FROM questionnaire_slot_questions
         WHERE slot_id IN ($placeholders)"
    );
    $sqStmt->execute($slotIds);
    $slotQuestions = $sqStmt->fetchAll(PDO::FETCH_ASSOC);

    $questionsBySlot = [];
    foreach ($slotQuestions as $sq) {
        $questionsBySlot[intval($sq['slot_id'])][] = $sq['question_key'];
    }

    foreach ($slots as &$slot) {
        $slot['questions'] = $questionsBySlot[intval($slot['id'])] ?? [];
    }

    return $slots;
}

// ── Internal: resolve slots (apply pool randomisation) ──────────

/**
 * For each slot, determines which question keys are selected.
 * Returns slots with a 'selected' array containing the resolved keys.
 *
 * @param array $slots
 * @return array  Slots with 'selected' key added.
 */
function resolve_slots(array $slots): array
{
    $resolved = [];

    foreach ($slots as $slot) {
        $slotQuestions = $slot['questions'];
        $selected = [];

        if (!empty($slotQuestions)) {
            if ($slot['mode'] === 'fixed') {
                $selected = [$slotQuestions[0]];
            } elseif ($slot['mode'] === 'pool') {
                $count = max(1, intval($slot['pool_count']));
                $count = min($count, count($slotQuestions));
                $shuffled = $slotQuestions;
                shuffle($shuffled);
                $selected = array_slice($shuffled, 0, $count);
            }
        }

        $slot['selected'] = $selected;
        $resolved[] = $slot;
    }

    return $resolved;
}

/**
 * Collects all selected question keys from resolved slots.
 *
 * @param array $resolvedSlots
 * @return array
 */
function collect_resolved_keys(array $resolvedSlots): array
{
    $keys = [];
    foreach ($resolvedSlots as $slot) {
        foreach ($slot['selected'] as $key) {
            $keys[] = $key;
        }
    }
    return array_unique($keys);
}

// ── Internal: load question definitions by keys ─────────────────

/**
 * @param PDO   $pdo
 * @param array $keys
 * @return array  Keyed by question_key.
 */
function load_questions_by_keys(PDO $pdo, array $keys): array
{
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT question_key, type, required, sort, config
         FROM questions
         WHERE question_key IN ($placeholders)"
    );
    $stmt->execute(array_values($keys));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[$row['question_key']] = $row;
    }

    return $result;
}

// ── Internal: load active options ───────────────────────────────

/**
 * @param PDO   $pdo
 * @param array $questionKeys
 * @return array  Keyed by question_key, each containing an array of options.
 */
function load_options_for_questions(PDO $pdo, array $questionKeys): array
{
    $placeholders = implode(',', array_fill(0, count($questionKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT question_key, option_key, sort
         FROM question_options
         WHERE question_key IN ($placeholders)
           AND is_active = 1
         ORDER BY sort ASC"
    );
    $stmt->execute(array_values($questionKeys));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[$row['question_key']][] = $row;
    }

    return $result;
}

// ── Internal: collect and fetch translations ────────────────────

/**
 * @param PDO    $pdo
 * @param string $lang
 * @param array  $questions  Keyed by question_key.
 * @param array  $optionsByQuestion
 * @return array  Translation map.
 */
function load_question_translations(PDO $pdo, string $lang, array $questions, array $optionsByQuestion): array
{
    $translationKeys = [];

    foreach ($questions as $key => $q) {
        $translationKeys[] = "questions.$key.label";
        if ($q['type'] === 'slider') {
            $translationKeys[] = "questions.$key.legend_low";
            $translationKeys[] = "questions.$key.legend_high";
        }
        if (!empty($optionsByQuestion[$key])) {
            foreach ($optionsByQuestion[$key] as $opt) {
                $translationKeys[] = "options.$key.{$opt['option_key']}";
            }
        }
    }

    if (empty($translationKeys)) {
        return [];
    }

    return fetch_translations($pdo, $lang, $translationKeys);
}

// ── Internal: assemble final question list ──────────────────────

/**
 * Builds the final ordered question list from resolved slots.
 *
 * @param array $resolvedSlots       Slots with 'selected' keys.
 * @param array $questions           Keyed by question_key.
 * @param array $optionsByQuestion
 * @param array $translations
 * @return array
 */
function assemble_question_list(array $resolvedSlots, array $questions, array $optionsByQuestion, array $translations): array
{
    $result = [];
    $sortCounter = 0;

    foreach ($resolvedSlots as $slot) {
        foreach ($slot['selected'] as $key) {
            if (!isset($questions[$key])) {
                continue;
            }

            $q = $questions[$key];
            $config = $q['config'] ? json_decode($q['config'], true) : [];

            $entry = [
                'key' => $key,
                'type' => $q['type'],
                'required' => intval($slot['required']) === 1,
                'sort' => (intval($slot['sort']) * 100) + $sortCounter,
                'config' => $config,
                'label' => $translations["questions.$key.label"] ?? $key,
            ];

            if ($q['type'] === 'slider') {
                $entry['legend_low'] = $translations["questions.$key.legend_low"] ?? '';
                $entry['legend_high'] = $translations["questions.$key.legend_high"] ?? '';
            }

            if (!empty($optionsByQuestion[$key])) {
                $entry['options'] = array_map(function ($opt) use ($translations, $key) {
                    $labelKey = "options.$key.{$opt['option_key']}";
                    return [
                        'key' => $opt['option_key'],
                        'sort' => intval($opt['sort']),
                        'label' => $translations[$labelKey] ?? $opt['option_key'],
                    ];
                }, $optionsByQuestion[$key]);
            }

            $result[] = $entry;
            $sortCounter++;
        }
    }

    return $result;
}
