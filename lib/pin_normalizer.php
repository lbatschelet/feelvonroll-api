<?php
/**
 * Pin normalization helpers for API responses.
 * Exports: normalize_pin_row.
 */

/**
 * Normalizes pin row output types and shape.
 *
 * @param array $row
 * @return array
 */
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
