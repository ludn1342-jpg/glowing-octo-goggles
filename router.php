<?php
// router.php - Para Render
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $request_uri;

// Si el archivo solicitado existe físicamente (ej: api/webhook.php o tarjetas.html), lo servimos
if (file_exists($file) && !is_dir($file)) {
    return false; // Deja que el servidor de PHP lo ejecute o lo sirva
}

// Si no existe, enviamos un 404
http_response_code(404);
echo "404 Not Found";
