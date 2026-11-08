<?php
// api/usuarios_online.php
require_once __DIR__ . '/_bootstrap.php';

$raw = file_get_contents('php://input');
$d   = json_decode($raw, true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($d['usuarioId'] ?? '');
    $pagina = trim($d['pagina'] ?? 'desconocida');
    if (!$uid) json_err('missing_usuarioId');

    $ip = pyp_ip();

    db_exec("
        INSERT INTO usuarios_online (usuario_id, pagina, ultimo_ping, ip)
        VALUES (?, ?, NOW(), ?)
        ON CONFLICT (usuario_id) DO UPDATE SET
          pagina = EXCLUDED.pagina,
          ultimo_ping = NOW(),
          ip = EXCLUDED.ip
    ", [$uid, $pagina, $ip]);

    db_exec("DELETE FROM usuarios_online WHERE ultimo_ping < NOW() - INTERVAL '10 seconds'");

    $total = db_fetch("SELECT COUNT(*) as c FROM usuarios_online")['c'];
    json_ok(['usuarios_online' => (int)$total]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    db_exec("DELETE FROM usuarios_online WHERE ultimo_ping < NOW() - INTERVAL '10 seconds'");
    $rows = db_fetchAll("SELECT usuario_id, pagina, ultimo_ping, ip FROM usuarios_online");
    json_ok([
        'usuarios_online' => count($rows),
        'usuarios' => $rows
    ]);
}

json_err('method_not_allowed', 405);
