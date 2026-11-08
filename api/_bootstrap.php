<?php
// api/_bootstrap.php
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_cors.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Constantes de entorno
define('PYP_API_KEY', getenv('PYP_API_KEY') ?: 'A9AF97EOZYQAYQNB67T9WPN7DWYC70J6');

function json_ok(array $data = []): void {
    echo json_encode(['success' => true] + $data); exit;
}
function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]); exit;
}

function pyp_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function pyp_require_api_key(): void {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals(PYP_API_KEY, $key)) {
        json_err('unauthorized', 401);
    }
}
