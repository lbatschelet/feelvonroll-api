<?php
/**
 * LV95 calibration service.
 * Exports:
 * - admin_lv95_calibration_get_active
 * - admin_lv95_calibration_preview
 * - admin_lv95_calibration_save
 */

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/audit.php';

function admin_lv95_calibration_get_active(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM lv95_calibrations WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['active' => null];
    }
    return [
        'active' => [
            'id' => intval($row['id']),
            'label' => $row['label'],
            'points' => json_decode($row['points'], true),
            'params' => json_decode($row['params'], true),
            'diagnostics' => $row['diagnostics'] ? json_decode($row['diagnostics'], true) : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ],
    ];
}

function admin_lv95_calibration_preview(PDO $pdo, ?int $adminUserId, array $data): array
{
    $points = $data['points'] ?? null;
    $computed = lv95_compute_transform($points);

    log_admin_action($pdo, $adminUserId, 'lv95_calibration_preview', 'lv95_calibration', [
        'point_count' => is_array($points) ? count($points) : null,
    ]);

    return [
        'ok' => true,
        'params' => $computed['params'],
        'diagnostics' => $computed['diagnostics'],
    ];
}

function admin_lv95_calibration_save(PDO $pdo, ?int $adminUserId, array $data): array
{
    $label = isset($data['label']) ? trim(strval($data['label'])) : null;
    $points = $data['points'] ?? null;
    $applyToStations = !empty($data['apply_to_stations']);

    $computed = lv95_compute_transform($points);
    $params = $computed['params'];
    $diagnostics = $computed['diagnostics'];

    $pdo->beginTransaction();
    try {
        // Deactivate previous.
        $pdo->exec('UPDATE lv95_calibrations SET is_active = 0 WHERE is_active = 1');

        $stmt = $pdo->prepare(
            'INSERT INTO lv95_calibrations (is_active, created_by_admin_user_id, label, points, params, diagnostics)
             VALUES (1, :uid, :label, :points, :params, :diag)'
        );
        $stmt->execute([
            'uid' => $adminUserId,
            'label' => $label,
            'points' => json_encode($points),
            'params' => json_encode($params),
            'diag' => json_encode($diagnostics),
        ]);
        $id = intval($pdo->lastInsertId());

        if ($applyToStations) {
            // Update stations based on target_x/target_z.
            $rows = $pdo->query('SELECT id, target_x, target_z FROM stations')->fetchAll(PDO::FETCH_ASSOC);
            $u = floatval($params['scale']);
            $c = floatval($params['cos']);
            $s = floatval($params['sin']);
            $tx = floatval($params['tx']);
            $ty = floatval($params['ty']);

            $upd = $pdo->prepare('UPDATE stations SET lv95_e = :e, lv95_n = :n WHERE id = :id');
            foreach ($rows as $r) {
                $x = floatval($r['target_x']);
                $z = floatval($r['target_z']);
                $e = $u * ($c * $x - $s * $z) + $tx;
                $n = $u * ($s * $x + $c * $z) + $ty;
                $upd->execute(['e' => $e, 'n' => $n, 'id' => intval($r['id'])]);
            }
        }

        log_admin_action($pdo, $adminUserId, 'lv95_calibration_save', "lv95_calibration:$id", [
            'label' => $label,
            'apply_to_stations' => $applyToStations,
            'diagnostics' => $diagnostics,
        ]);

        $pdo->commit();
        return ['ok' => true, 'id' => $id, 'params' => $params, 'diagnostics' => $diagnostics];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Computes a 2D similarity transform from world (x,z) to LV95 (e,n).
 *
 * Input points:
 * [
 *   { world: { x: number, z: number }, lv95: { e: number, n: number } },
 *   ...
 * ]
 *
 * Output params:
 * - scale: float
 * - rotation_rad: float
 * - cos: float
 * - sin: float
 * - tx: float
 * - ty: float
 */
function lv95_compute_transform($points): array
{
    if (!is_array($points) || count($points) < 3) {
        throw new ApiError('Need at least 3 calibration points', 400);
    }

    $pairs = [];
    foreach ($points as $p) {
        $wx = $p['world']['x'] ?? null;
        $wz = $p['world']['z'] ?? null;
        $e = $p['lv95']['e'] ?? null;
        $n = $p['lv95']['n'] ?? null;
        if (!is_numeric($wx) || !is_numeric($wz) || !is_numeric($e) || !is_numeric($n)) {
            throw new ApiError('Invalid point format', 400);
        }
        $pairs[] = [floatval($wx), floatval($wz), floatval($e), floatval($n)];
    }

    // Centroids.
    $mx = 0; $mz = 0; $me = 0; $mn = 0;
    foreach ($pairs as $r) { $mx += $r[0]; $mz += $r[1]; $me += $r[2]; $mn += $r[3]; }
    $k = count($pairs);
    $mx /= $k; $mz /= $k; $me /= $k; $mn /= $k;

    // Complex least squares: a = sum((q-mean_q) * conj(p-mean_p)) / sum(|p-mean_p|^2)
    // p = x + i z, q = e + i n
    $numRe = 0; $numIm = 0; $den = 0;
    foreach ($pairs as $r) {
        $px = $r[0] - $mx;
        $pz = $r[1] - $mz;
        $qx = $r[2] - $me;
        $qz = $r[3] - $mn;
        // (qx + i qz) * conj(px + i pz) = (qx + i qz) * (px - i pz)
        $numRe += $qx * $px + $qz * $pz;
        $numIm += $qz * $px - $qx * $pz;
        $den += $px * $px + $pz * $pz;
    }
    if ($den <= 0) {
        throw new ApiError('Calibration points are degenerate', 400);
    }
    $aRe = $numRe / $den;
    $aIm = $numIm / $den;
    $scale = sqrt($aRe * $aRe + $aIm * $aIm);
    if ($scale <= 0) {
        throw new ApiError('Invalid calibration scale', 400);
    }
    $cos = $aRe / $scale;
    $sin = $aIm / $scale;
    $rot = atan2($aIm, $aRe);

    // Translation: t = mean_q - a*mean_p
    // a*mean_p: (aRe + i aIm) * (mx + i mz)
    $ax = $aRe * $mx - $aIm * $mz;
    $az = $aIm * $mx + $aRe * $mz;
    $tx = $me - $ax;
    $ty = $mn - $az;

    // Diagnostics.
    $residuals = [];
    $rms = 0;
    foreach ($pairs as $r) {
        $x = $r[0]; $z = $r[1];
        $predE = $aRe * $x - $aIm * $z + $tx;
        $predN = $aIm * $x + $aRe * $z + $ty;
        $de = $predE - $r[2];
        $dn = $predN - $r[3];
        $err = sqrt($de * $de + $dn * $dn);
        $rms += $err * $err;
        $residuals[] = ['de' => $de, 'dn' => $dn, 'm' => $err];
    }
    $rms = sqrt($rms / $k);

    return [
        'params' => [
            'scale' => $scale,
            'rotation_rad' => $rot,
            'cos' => $cos,
            'sin' => $sin,
            'tx' => $tx,
            'ty' => $ty,
        ],
        'diagnostics' => [
            'point_count' => $k,
            'rms_m' => $rms,
            'residuals' => $residuals,
        ],
    ];
}

