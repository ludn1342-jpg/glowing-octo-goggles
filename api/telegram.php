<?php
// api/telegram.php
header('Content-Type: application/json; charset=utf-8');

$token   = getenv('TG_TOKEN_P1') ?: '8690979729:AAFsNeHtHIQPeYMKZdOuHbDoThl_hZjvm3c';
$chat_id = getenv('TG_CHAT_P1') ?: '-1004319232540';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['mensaje']) || $input['mensaje'] !== 'p1') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solo se acepta "p1"']);
    exit;
}

$url = "https://api.telegram.org/bot{$token}/sendMessage";

$datos = [
    'chat_id' => $chat_id,
    'text'    => 'p1',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $datos,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$respuesta = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'cURL: ' . $curlError]);
    exit;
}

$data = json_decode($respuesta, true);

if ($httpCode === 200 && !empty($data['ok'])) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'httpCode'   => $httpCode,
        'telegram'   => $data,
        'rawResponse'=> $respuesta,
    ]);
}
