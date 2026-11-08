<?php
// ip/index.php
// 🔒 Panel de bloqueo de IPs

session_start();

// ============================================
// === CREDENCIALES ===
// ============================================

const IP_PANEL_USER = 'user';
const IP_PANEL_PASSWORD = 'x';

// ============================================
// === RUTAS ===
// ============================================

const DATA_DIR = __DIR__ . '/../data/';
const SOLICITUDES_FILE = DATA_DIR . 'solicitudes.dat';
const IPS_BLOQUEADAS_FILE = DATA_DIR . 'ips_bloqueadas.dat';

// ============================================
// === CREAR DIRECTORIOS ===
// ============================================

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0700, true);
}

// ============================================
// === FUNCIONES ===
// ============================================

function estaAutenticado() {
    return isset($_SESSION['ip_panel_auth']) && $_SESSION['ip_panel_auth'] === true;
}

function cargarSolicitudes() {
    if (!file_exists(SOLICITUDES_FILE)) {
        return [];
    }
    $data = file_get_contents(SOLICITUDES_FILE);
    return json_decode($data, true) ?: [];
}

function cargarIPsBloqueadas() {
    if (!file_exists(IPS_BLOQUEADAS_FILE)) {
        return [];
    }
    $data = file_get_contents(IPS_BLOQUEADAS_FILE);
    return json_decode($data, true) ?: [];
}

function guardarIPsBloqueadas($ips) {
    file_put_contents(IPS_BLOQUEADAS_FILE, json_encode($ips, JSON_PRETTY_PRINT), LOCK_EX);
    chmod(IPS_BLOQUEADAS_FILE, 0600);
}

function getIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    
    return $ip;
}

// ============================================
// === PROCESAR LOGIN ===
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    
    if ($usuario === IP_PANEL_USER && $contrasena === IP_PANEL_PASSWORD) {
        $_SESSION['ip_panel_auth'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error_login = '❌ Usuario o contraseña incorrectos';
    }
}

// ============================================
// === PROCESAR ACCIONES ===
// ============================================

if (estaAutenticado() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // BLOQUEAR IP
    if ($action === 'bloquear') {
        $ip_a_bloquear = $_POST['ip'] ?? '';
        
        if (filter_var($ip_a_bloquear, FILTER_VALIDATE_IP)) {
            $ips_bloqueadas = cargarIPsBloqueadas();
            
            if (!in_array($ip_a_bloquear, $ips_bloqueadas)) {
                $ips_bloqueadas[] = $ip_a_bloquear;
                guardarIPsBloqueadas($ips_bloqueadas);
                $mensaje_exito = "✅ IP {$ip_a_bloquear} bloqueada correctamente";
            } else {
                $mensaje_error = "⚠️ Esa IP ya estaba bloqueada";
            }
        }
    }
    
    // DESBLOQUEAR IP
    if ($action === 'desbloquear') {
        $ip_a_desbloquear = $_POST['ip'] ?? '';
        
        if (filter_var($ip_a_desbloquear, FILTER_VALIDATE_IP)) {
            $ips_bloqueadas = cargarIPsBloqueadas();
            $ips_bloqueadas = array_filter($ips_bloqueadas, function($ip) use ($ip_a_desbloquear) {
                return $ip !== $ip_a_desbloquear;
            });
            guardarIPsBloqueadas($ips_bloqueadas);
            $mensaje_exito = "✅ IP {$ip_a_desbloquear} desbloqueada correctamente";
        }
    }
}

// ============================================
// === SI NO ESTÁ AUTENTICADO, MOSTRAR LOGIN ===
// ============================================

if (!estaAutenticado()) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🚫 Panel de Bloqueo de IPs</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            
            .login-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                padding: 40px;
                width: 100%;
                max-width: 400px;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header h1 {
                font-size: 28px;
                color: #333;
                margin-bottom: 10px;
            }
            
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 500;
            }
            
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
                transition: all 0.3s;
            }
            
            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            button:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            }
            
            .error {
                background: #fee;
                color: #c33;
                padding: 12px;
                border-radius: 5px;
                margin-bottom: 20px;
                border-left: 4px solid #c33;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>🚫 Panel de IPs</h1>
                <p>Bloquea y desbloquea direcciones IP</p>
            </div>
            
            <?php if (isset($error_login)): ?>
                <div class="error"><?php echo $error_login; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" required>
                </div>
                
                <button type="submit" name="login" value="1">🔓 Entrar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// === CARGAR DATOS ===
// ============================================

$solicitudes = cargarSolicitudes();
$ips_bloqueadas = cargarIPsBloqueadas();

// Enriquecer solicitudes con estado de bloqueo
foreach ($solicitudes as &$sol) {
    $sol['bloqueada'] = in_array($sol['ip'] ?? '', $ips_bloqueadas);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚫 Panel de Bloqueo de IPs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
        }
        
        .header-info {
            text-align: right;
            font-size: 14px;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left-color: #f44336;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .ip-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .status-bloqueado {
            color: #d32f2f;
            font-weight: 600;
        }
        
        .status-activo {
            color: #388e3c;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-bloquear {
            background: #f44336;
            color: white;
        }
        
        .btn-bloquear:hover {
            background: #d32f2f;
        }
        
        .btn-desbloquear {
            background: #4caf50;
            color: white;
        }
        
        .btn-desbloquear:hover {
            background: #388e3c;
        }
        
        .empty-message {
            padding: 40px;
            text-align: center;
            color: #999;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 300px;
        }
        
        .modal-content h2 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .modal-btn-confirm {
            background: #f44336;
            color: white;
        }
        
        .modal-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🚫 Panel de Bloqueo de IPs</h1>
            </div>
            <div class="header-info">
                <p>Tu IP: <span class="ip-badge"><?php echo getIp(); ?></span></p>
                <form method="POST" style="margin-top: 10px;">
                    <button type="submit" name="logout" class="logout-btn">🚪 Salir</button>
                </form>
            </div>
        </div>
        
        <?php if (isset($mensaje_exito)): ?>
            <div class="alert alert-success"><?php echo $mensaje_exito; ?></div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-error"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo count($solicitudes); ?></h3>
                <p>Tarjetas recibidas</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($ips_bloqueadas); ?></h3>
                <p>IPs bloqueadas</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($solicitudes, fn($s) => !$s['bloqueada'])); ?></h3>
                <p>Tarjetas activas</p>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($solicitudes)): ?>
                <div class="empty-message">
                    <p>📭 No hay tarjetas registradas aún</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>IP</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $sol): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sol['id'] ?? 'N/A'); ?></td>
                                <td><span class="ip-badge"><?php echo htmlspecialchars($sol['ip'] ?? 'Desconocida'); ?></span></td>
                                <td>
                                    <?php if ($sol['bloqueada']): ?>
                                        <span class="status-bloqueado">🚫 Bloqueada</span>
                                    <?php else: ?>
                                        <span class="status-activo">✅ Activa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?php if ($sol['bloqueada']): ?>
                                            <input type="hidden" name="action" value="desbloquear">
                                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($sol['ip'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-desbloquear">Desbloquear</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="bloquear">
                                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($sol['ip'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-bloquear">Bloquear</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Logout
        document.querySelector('[name="logout"]')?.addEventListener('click', function(e) {
            if (confirm('¿Seguro que quieres salir?')) {
                window.location.href = '?logout=1';
            } else {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php

// Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>