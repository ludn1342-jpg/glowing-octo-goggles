<?php
// api/telegram.php
// 📨 SOLO ENVÍA "p1" - NADA MÁS

header('Content-Type: application/json');

// Configuración
$token = '8690979729:AAE0Q48i8m_EBxoykj_tS36jSQqtvt6EmIg';
$chat_id = '-1004319232540';

// Leer lo que llega
$input = json_decode(file_get_contents('php://input'), true);

// Si no es "p1", no hacer nada
if (!isset($input['mensaje']) || $input['mensaje'] !== 'p1') {
    http_response_code(400);
    echo json_encode(['error' => 'Solo se acepta "p1"']);
    exit;
}

// SOLO ENVIAR "p1" - Sin fechas, sin nombres, sin nada extra
$mensaje = "p1";

// Enviar a Telegram
$url = "https://api.telegram.org/bot{$token}/sendMessage";
$datos = [
    'chat_id' => $chat_id,
    'text' => $mensaje
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$respuesta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Responder al cliente
if ($httpCode === 200) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
?>