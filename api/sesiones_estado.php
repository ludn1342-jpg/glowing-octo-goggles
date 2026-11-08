<?php
// api/sesiones_estado.php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../config/config.php'; // Ojo: solo si este archivo está en el panel

if (!estaAutenticado()) json_err('No autorizado', 401);

$sesiones = db_fetchAll("SELECT * FROM sesiones_admin ORDER BY ultima_actividad DESC");
$activas = count(array_filter($sesiones, fn($s) => (int)($s['activa'] ?? 1) === 1));

json_ok([
    'total' => count($sesiones),
    'activas' => $activas,
    'timestamp' => time()
]);
