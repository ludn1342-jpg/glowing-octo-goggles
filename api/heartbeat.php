<?php
// api/heartbeat.php
require_once __DIR__ . '/_bootstrap.php';

$raw = file_get_contents('php://input');
$d   = json_decode($raw, true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = trim($d['solicitudId'] ?? '');
    if (!$sid) json_err('missing_solicitudId');

    $estado = $d['estado'] ?? 'activo';
    $usuario = trim($d['usuario'] ?? '');
    $ip = pyp_ip();
    $pais = $d['pais'] ?? null;
    $ciudad = $d['ciudad'] ?? null;

    db_exec("
        INSERT INTO heartbeats (solicitud_id, usuario, estado, ip, pais, ciudad, ts)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON CONFLICT (solicitud_id) DO UPDATE SET
          usuario = EXCLUDED.usuario,
          estado  = EXCLUDED.estado,
          ip      = EXCLUDED.ip,
          pais    = EXCLUDED.pais,
          ciudad  = EXCLUDED.ciudad,
          ts      = NOW()
    ", [$sid, $usuario, $estado, $ip, $pais, $ciudad]);

    json_ok(['online' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $row = db_fetch("SELECT ts FROM heartbeats WHERE solicitud_id = ?", [$id]);
        $isOnline = false;
        $ultimoPing = 0;
        if ($row) {
            $segundos = time() - strtotime($row['ts']);
            $isOnline = $segundos <= 15;
            $ultimoPing = strtotime($row['ts']);
        }
        json_ok(['online' => $isOnline, 'ultimo_ping' => $ultimoPing]);
    }

    $rows = db_fetchAll("SELECT solicitud_id, ts FROM heartbeats WHERE ts > NOW() - INTERVAL '15 seconds'");
    $resultado = [];
    foreach ($rows as $r) {
        $resultado[$r['solicitud_id']] = [
            'online' => true,
            'segundos_inactivo' => time() - strtotime($r['ts'])
        ];
    }
    json_ok(['online' => $resultado, 'total_online' => count($resultado)]);
}

json_err('method_not_allowed', 405);
