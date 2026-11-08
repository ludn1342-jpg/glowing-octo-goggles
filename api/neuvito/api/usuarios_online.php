<?php
// api/usuarios_online.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data/';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

$file = $dataDir . 'usuarios_online.json';

// ============================================
// POST: Registrar usuario en línea
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $usuarioId = $input['usuarioId'] ?? session_id();
    $pagina = $input['pagina'] ?? 'desconocida';
    
    $usuarios = [];
    if (file_exists($file)) {
        $usuarios = json_decode(file_get_contents($file), true) ?: [];
    }
    
    // Limpiar usuarios inactivos (más de 10 segundos sin actividad)
    $ahora = time();
    $usuariosActivos = [];
    foreach ($usuarios as $id => $info) {
        if ($ahora - $info['ultimo_heartbeat'] < 10) {
            $usuariosActivos[$id] = $info;
        }
    }
    
    // Actualizar o agregar usuario
    $usuariosActivos[$usuarioId] = [
        'usuarioId' => $usuarioId,
        'pagina' => $pagina,
        'ultimo_heartbeat' => $ahora,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconocida',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 150)
    ];
    
    file_put_contents($file, json_encode($usuariosActivos, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'usuarios_online' => count($usuariosActivos)
    ]);
    exit;
}

// ============================================
// GET: Obtener número de usuarios en línea
// ============================================
$usuarios = [];
if (file_exists($file)) {
    $usuarios = json_decode(file_get_contents($file), true) ?: [];
}

// Limpiar usuarios inactivos
$ahora = time();
$usuariosActivos = [];
foreach ($usuarios as $id => $info) {
    if ($ahora - $info['ultimo_heartbeat'] < 10) {
        $usuariosActivos[$id] = $info;
    }
}

// Guardar lista limpia
if (count($usuariosActivos) !== count($usuarios)) {
    file_put_contents($file, json_encode($usuariosActivos, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'usuarios_online' => count($usuariosActivos),
    'usuarios' => array_values($usuariosActivos)
]);
exit;