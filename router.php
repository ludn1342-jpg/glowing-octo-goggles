<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si el archivo PHP existe, ejecutarlo
if (preg_match('/\.php$/', $uri)) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        require $file;
        return true;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado: ' . $uri]);
    return true;
}

// Archivos estáticos
return false;
