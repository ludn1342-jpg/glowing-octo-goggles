<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si el archivo PHP existe, ejecutarlo
if (preg_match('/\.php$/', $uri)) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        try {
            require $file;
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            error_log("[PHP ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
        return true;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado: ' . $uri]);
    return true;
}

// Archivos estáticos
return false;
