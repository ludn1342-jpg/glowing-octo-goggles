<?php
// api/bin_proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$HANDYAPI_KEY = getenv('HANDYAPI_KEY') ?: 'HAS-139kYW5q3NS98PmXt8w67rbDH';

if (!isset($_GET['bin']) || strlen($_GET['bin']) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'BIN inválido']);
    exit;
}

$bin = substr(preg_replace('/[^0-9]/', '', $_GET['bin']), 0, 6);

// ============================================
// CONSULTAR HANDYAPI
// ============================================
$url = "https://data.handyapi.com/bin/" . $bin;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: " . $HANDYAPI_KEY,
    "Accept: application/json",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        http_response_code(404);
        echo json_encode(['error' => $data['error']]);
        exit;
    }
    
    $countryCode = $data['CountryCode'] ?? $data['countryCode'] ?? '';
    $isColombia = $countryCode === 'CO' || $countryCode === '170';
    
    if (!$isColombia && !empty($countryCode)) {
        echo json_encode([
            'error' => 'Tarjeta no emitida en Colombia',
            'country' => $data['CountryName'] ?? $data['country'] ?? '',
            'countryCode' => $countryCode
        ]);
        exit;
    }
    
    $transformed = [
        'bank' => ['name' => $data['IssuerName'] ?? $data['issuer'] ?? 'Desconocido'],
        'country' => [
            'name' => $data['CountryName'] ?? $data['country'] ?? 'Colombia',
            'alpha2' => $countryCode
        ],
        'type' => $data['CardType'] ?? $data['type'] ?? '',
        'level' => $data['CardTier'] ?? $data['cardTier'] ?? '',
        'scheme' => $data['Scheme'] ?? $data['scheme'] ?? '',
        'source' => 'HandyAPI'
    ];
    
    echo json_encode($transformed);
    exit;
}

// ============================================
// RESPALDO: BINCHECK.IO
// ============================================
$rapidapi_key = getenv('RAPIDAPI_KEY') ?: 'Kdak7K89z5TcOxYQOWHfO8PPQlCVVElWxbMxPfuvhb6I97ZN';
$url = "https://bincheck-1.p.rapidapi.com/api/bin/" . $bin;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-RapidAPI-Key: " . $rapidapi_key,
    "X-RapidAPI-Host: bincheck-1.p.rapidapi.com",
    "Accept: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    if ($data && !isset($data['error'])) {
        echo $response;
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'No se pudo obtener información del BIN', 'bin' => $bin]);
