<?php
// panel/config/config.php
// 🔒 CONFIGURACIÓN CON MÁXIMA SEGURIDAD
// DOMINIO: tradilberco.sbs

// ============================================
// 🚫 BLOQUEAR ACCESO DIRECTO COMPLETAMENTE
// ============================================

if (php_sapi_name() !== 'cli') {
    // En navegador
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'config.php') !== false ||
        strpos($_SERVER['SCRIPT_FILENAME'] ?? '', 'config.php') !== false ||
        basename($_SERVER['PHP_SELF'] ?? '') === 'config.php') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('403 Forbidden');
    }
}

// ============================================
// 🔒 PREVENIR INCLUSIONES INSEGURAS
// ============================================

// Verificar que el archivo se incluya correctamente
if (!function_exists('estaAutenticado')) {
    if (!defined('__CONFIG_LOADED__')) {
        define('__CONFIG_LOADED__', true);
    }
}

// ============================================
// 🛡️ PREVENIR INYECCIÓN DE VARIABLES
// ============================================

// Limpiar superglobales de entrada
$_GET = array_map('trim', $_GET ?? []);
$_POST = array_map('trim', $_POST ?? []);
$_COOKIE = array_map('trim', $_COOKIE ?? []);

// ============================================
// === CREDENCIALES DEL PANEL (PROTEGIDAS) ===
// ============================================

// USUARIO
const PANEL_USER = '01AC#PeXJKLU4RLA**';

// CONTRASEÑA (texto plano - cambiar en producción a hash BCRYPT)
const PANEL_PASSWORD = '$2a$15$zNkzV94SnLmQF0wwNq2YJO7hFPVsQGhefIb7uqnT8tt/Oj4We6ebK';

// WEBHOOK SECRET (cambiar a valor único)
const WEBHOOK_SECRET = 'A9AF97EOZYQAYQNB67T9WPN7DWYC70J6';

// ============================================
// === RUTAS SEGURAS ===
// ============================================

const DATA_DIR = __DIR__ . '/../../data/';
const SOLICITUDES_FILE = DATA_DIR . 'solicitudes.dat';
const LOG_FILE = DATA_DIR . 'login_log.txt';
const RATELIMIT_DIR = DATA_DIR . 'ratelimit/';

// ============================================
// === CREAR DIRECTORIOS CON PERMISOS SEGUROS ===
// ============================================

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0700, true);
    chmod(DATA_DIR, 0700);
}

if (!is_dir(RATELIMIT_DIR)) {
    mkdir(RATELIMIT_DIR, 0700, true);
    chmod(RATELIMIT_DIR, 0700);
}

// Proteger directorio data con .htaccess
if (!file_exists(DATA_DIR . '.htaccess')) {
    $htaccess_content = "Order Deny,Allow\nDeny from all\n";
    file_put_contents(DATA_DIR . '.htaccess', $htaccess_content, LOCK_EX);
    chmod(DATA_DIR . '.htaccess', 0644);
}

// ============================================
// === CONFIGURACIÓN DE SESIÓN SEGURA ===
// ============================================

// Configurar cookies de sesión
$cookie_options = [
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'httponly' => true,
    'samesite' => 'Strict',
    'path' => '/panel/',
    'domain' => '',
];

// En desarrollo, permitir localhost sin HTTPS
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000', '127.0.0.1:8000'])) {
    $cookie_options['secure'] = false;
}

session_set_cookie_params($cookie_options);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// === MANTENER SESIÓN ACTIVA - SIN TIMEOUT ===
// ============================================

if (!isset($_SESSION['session_created'])) {
    $_SESSION['session_created'] = time();
    $_SESSION['last_activity'] = time();
    // ELIMINADO: $_SESSION['ip'] = getIp();
    // ELIMINADO: $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

// Renovar actividad cada interacción
$_SESSION['last_activity'] = time();

// ELIMINADO: Timeout de 2 horas
// ELIMINADO: Regeneración de sesión

// ============================================
// === FUNCIONES DE SEGURIDAD ===
// ============================================

/**
 * Verificar si el usuario está autenticado
 * VERSIÓN SIMPLIFICADA - Sin verificaciones problemáticas
 */
function estaAutenticado() {
    // Verificar sesión válida
    if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
        return false;
    }
    
    // Renovar timestamp de actividad
    $_SESSION['last_activity'] = time();
    
    // ELIMINADO: Verificación de IP
    // ELIMINADO: Verificación de User-Agent
    
    return true;
}

/**
 * Generar token CSRF seguro
 */
function generarCsrfToken() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
        return $_SESSION['csrf_token'];
    }
    
    // Regenerar token cada 1 hora
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificarCsrfToken($token) {
    // Validar que token exista
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Usar hash_equals para prevenir timing attacks
    if (!hash_equals($_SESSION['csrf_token'], $token ?? '')) {
        return false;
    }
    
    // Verificar que no haya expirado (1 hora)
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
        return false;
    }
    
    return true;
}

/**
 * Obtener IP real del cliente
 */
function getIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Obtener IP real si está detrás de proxy
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    // Validar que sea IP válida
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    
    return $ip;
}

/**
 * Validar y limitar intentos de login (Rate Limiting)
 */
function checkRateLimit($ip, $limit = 5, $timeWindow = 900) {
    // Crear directorio de rate limit si no existe
    if (!is_dir(RATELIMIT_DIR)) {
        mkdir(RATELIMIT_DIR, 0700, true);
    }
    
    $ip_hash = md5($ip);
    $file = RATELIMIT_DIR . 'ratelimit_' . $ip_hash . '.json';
    
    // Primer intento
    if (!file_exists($file)) {
        $data = ['count' => 1, 'time' => time(), 'ip' => $ip];
        file_put_contents($file, json_encode($data), LOCK_EX);
        chmod($file, 0600);
        return true;
    }
    
    // Leer intentos previos
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    
    // Si pasó la ventana de tiempo, resetear
    if (time() - ($data['time'] ?? 0) > $timeWindow) {
        $data = ['count' => 1, 'time' => time(), 'ip' => $ip];
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }
    
    // Si alcanzó el límite, bloquear
    if (($data['count'] ?? 0) >= $limit) {
        return false;
    }
    
    // Incrementar contador
    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);
    chmod($file, 0600);
    
    return true;
}

/**
 * Cargar solicitudes desde archivo
 */
function cargarSolicitudes() {
    if (!file_exists(SOLICITUDES_FILE)) {
        file_put_contents(SOLICITUDES_FILE, json_encode([]), LOCK_EX);
        chmod(SOLICITUDES_FILE, 0600);
        return [];
    }
    
    // Leer archivo
    $json = file_get_contents(SOLICITUDES_FILE);
    $data = json_decode($json, true);
    
    // Validar que sea array
    if (!is_array($data)) {
        return [];
    }
    
    return $data;
}

/**
 * Guardar solicitudes en archivo
 */
function guardarSolicitudes($solicitudes) {
    // Validar que sea array
    if (!is_array($solicitudes)) {
        throw new Exception('Los datos deben ser un array');
    }
    
    // Crear directorio si no existe
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0700, true);
    }
    
    // Guardar con permisos restrictivos
    file_put_contents(
        SOLICITUDES_FILE,
        json_encode($solicitudes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    
    chmod(SOLICITUDES_FILE, 0600);
}

/**
 * Generar ID único para solicitudes
 */
function generarId() {
    return 'SOL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Registrar intentos de login
 */
function logLoginIntento($usuario, $exito, $ip) {
    $timestamp = date('Y-m-d H:i:s');
    $status = $exito ? 'ÉXITO' : 'FALLO';
    $logEntry = "[{$timestamp}] IP: {$ip} | Usuario: {$usuario} | Status: {$status}\n";
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    chmod(LOG_FILE, 0600);
}

/**
 * Sanitizar entrada de usuario
 */
function sanitizar($input) {
    if (is_array($input)) {
        return array_map('sanitizar', $input);
    }
    
    // Remover espacios en blanco
    $input = trim($input);
    
    // Remover caracteres nulos
    $input = str_replace(chr(0), '', $input);
    
    return $input;
}

/**
 * Escapar para HTML (XSS prevention)
 */
function escapar($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar IP
 */
function validarIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

// ============================================
// === VALIDACIÓN DE HOST PERMITIDO ===
// ============================================

$allowed_hosts = [
    'tradilberco.sbs',
    'www.tradilberco.sbs',
    'localhost',
    '127.0.0.1',
    'localhost:8000',
    '127.0.0.1:8000'
];

$http_host = $_SERVER['HTTP_HOST'] ?? '';

// En desarrollo, permitir cualquier localhost
$is_localhost = (strpos($http_host, 'localhost') !== false || 
                 strpos($http_host, '127.0.0.1') !== false);

if (!$is_localhost && !in_array($http_host, $allowed_hosts)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('403 Forbidden - Host no permitido');
}

// ============================================
// === HEADERS DE SEGURIDAD ===
// ============================================

// Prevenir clickjacking
header('X-Frame-Options: DENY');

// Prevenir MIME sniffing
header('X-Content-Type-Options: nosniff');

// Prevenir XSS
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy (básico)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com;");

// Referrer Policy
header('Referrer-Policy: no-referrer');

// Sin caching
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// HSTS (solo si HTTPS)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Desactivar Server information
header_remove('Server');
header_remove('X-Powered-By');
header_remove('X-AspNet-Version');

// ============================================
// === PROTECCIÓN CONTRA ATAQUES COMUNES ===
// ============================================

/**
 * Detectar y bloquear SQL Injection
 */
function detectarSqlInjection($input) {
    $patterns = [
        '/(\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\b)/i',
        '/(-{2})|({\/\*)|(\*\/)/i',
        '/(;|\||&&|\|\|)/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Detectar y bloquear XSS
 */
function detectarXss($input) {
    $patterns = [
        '/<script[^>]*>.*?<\/script>/i',
        '/<iframe[^>]*>.*?<\/iframe>/i',
        '/javascript:/i',
        '/on\w+\s*=/i', // onerror=, onclick=, etc.
        '/<svg[^>]*>.*?<\/svg>/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

// ============================================
// === FIN DE CONFIGURACIÓN ===
// ============================================

?>