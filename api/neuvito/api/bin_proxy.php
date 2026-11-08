<?php
// api/bin_proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================
// CONFIGURACIÓN - HANDYAPI
// ============================================
$HANDYAPI_KEY = 'HAS-139kYW5q3NS98PmXt8w67rbDH'; // Backend/Secret Key

if (!isset($_GET['bin']) || strlen($_GET['bin']) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'BIN inválido']);
    exit;
}

$bin = substr(preg_replace('/[^0-9]/', '', $_GET['bin']), 0, 6);

// Cache en archivo (24 horas)
$cacheDir = __DIR__ . '/../data/cache/';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . 'bin_' . $bin . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    echo file_get_contents($cacheFile);
    exit;
}

// ============================================
// CONSULTAR HANDYAPI
// ============================================
$url = "https://data.handyapi.com/bin/" . $bin;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
    
    // Verificar si hay error
    if (isset($data['error'])) {
        http_response_code(404);
        echo json_encode(['error' => $data['error']]);
        exit;
    }
    
    // Verificar que sea Colombia
    $countryCode = $data['CountryCode'] ?? $data['countryCode'] ?? '';
    $isColombia = $countryCode === 'CO' || $countryCode === '170'; // 170 es código numérico de Colombia
    
    if (!$isColombia && !empty($countryCode)) {
        $error = [
            'error' => 'Tarjeta no emitida en Colombia',
            'country' => $data['CountryName'] ?? $data['country'] ?? '',
            'countryCode' => $countryCode
        ];
        file_put_contents($cacheFile, json_encode($error));
        echo json_encode($error);
        exit;
    }
    
    // Transformar al formato que espera el frontend
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
    
    file_put_contents($cacheFile, json_encode($transformed));
    echo json_encode($transformed);
    exit;
}

// Si HandyAPI falla, intentar con BinCheck.io como respaldo
$rapidapi_key = 'Kdak7K89z5TcOxYQOWHfO8PPQlCVVElWxbMxPfuvhb6I97ZN';
$url = "https://bincheck-1.p.rapidapi.com/api/bin/" . $bin;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
        file_put_contents($cacheFile, $response);
        echo $response;
        exit;
    }
}

// Si todo falla
http_response_code(404);
echo json_encode(['error' => 'No se pudo obtener información del BIN', 'bin' => $bin]);