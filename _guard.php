<?php
// _guard.php (versión para el frontend)
require_once __DIR__ . '/api/_db.php';

// 1. Obtener la IP real del visitante
function pyp_guard_ip_real(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// 2. Verificar si está en la tabla banned_ips
try {
    $ip = pyp_guard_ip_real();
    $ban = db_fetch("SELECT 1 FROM banned_ips WHERE ip = ? LIMIT 1", [$ip]);
    
    if ($ban) {
        // Si está baneado, mostrar 403
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        if (file_exists(__DIR__ . '/403.html')) {
            readfile(__DIR__ . '/403.html');
            exit;
        }
        echo '403 Forbidden'; exit;
    }
} catch (Throwable $e) {
    // Si la BD falla, dejar pasar (no bloquear usuarios legítimos por un error de conexión)
    // En producción podrías loguear este error.
}
?>
