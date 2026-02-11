<?php
/**
 * API de citas - CenTI-R
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    return $_SESSION['user'];
}

$db = new Database();
$pdo = $db->getConnection();

$user = requireAuth();
$userId = $user['id'];
$userRol = $user['rol'];

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api/citas?#', '', $path);
$path = trim($path, '/');
$id = $path ? (int)$path : null;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($id) {
            // Obtener una cita específica
            $stmt = $pdo->prepare("
                SELECT c.*, 
                    p.nombre as paciente_nombre, p.email as paciente_email,
                    u.nombre as terapeuta_nombre, t.especialidad
                FROM citas c
                JOIN usuarios p ON c.paciente_id = p.id
                JOIN terapeutas t ON c.terapeuta_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cita) {
                http_response_code(404);
                echo json_encode(['error' => 'Cita no encontrada']);
                exit;
            }
            
            // Verificar permisos
            if ($userRol === 'paciente' && $cita['paciente_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'Acceso denegado']);
                exit;
            }
            if ($userRol === 'terapeuta') {
                $terapeutaStmt = $pdo->prepare("SELECT id FROM terapeutas WHERE usuario_id = ?");
                $terapeutaStmt->execute([$userId]);
                $terapeuta = $terapeutaStmt->fetch();
                if ($terapeuta && $cita['terapeuta_id'] != $terapeuta['id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Acceso denegado']);
                    exit;
                }
            }
            
            echo json_encode($cita);
        } else {
            // Listar citas
            $filtros = [];
            $params = [];
            
            if ($userRol === 'paciente') {
                $filtros[] = "c.paciente_id = ?";
                $params[] = $userId;
            } elseif ($userRol === 'terapeuta') {
                $terapeutaStmt = $pdo->prepare("SELECT id FROM terapeutas WHERE usuario_id = ?");
                $terapeutaStmt->execute([$userId]);
                $terapeuta = $terapeutaStmt->fetch();
                if ($terapeuta) {
                    $filtros[] = "c.terapeuta_id = ?";
                    $params[] = $terapeuta['id'];
                }
            }
            
            $fecha = $_GET['fecha'] ?? null;
            if ($fecha) {
                $filtros[] = "c.fecha = ?";
                $params[] = $fecha;
            }
            
            $estado = $_GET['estado'] ?? null;
            if ($estado) {
                $filtros[] = "c.estado = ?";
                $params[] = $estado;
            }
            
            $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
            
            $sql = "
                SELECT c.*, 
                    p.nombre as paciente_nombre,
                    u.nombre as terapeuta_nombre, t.especialidad
                FROM citas c
                JOIN usuarios p ON c.paciente_id = p.id
                JOIN terapeutas t ON c.terapeuta_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                $where
                ORDER BY c.fecha DESC, c.hora_inicio DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($citas);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if ($userRol === 'paciente') {
            $terapeutaId = (int)($input['terapeuta_id'] ?? 0);
            $fecha = $input['fecha'] ?? '';
            $horaInicio = $input['hora_inicio'] ?? '';
            $modalidad = $input['modalidad'] ?? 'presencial';
            $tipoConsulta = $input['tipo_consulta'] ?? 'individual';
            $costo = isset($input['costo']) ? (float)$input['costo'] : null;
            
            if (!$terapeutaId || !$fecha || !$horaInicio) {
                http_response_code(400);
                echo json_encode(['error' => 'Terapeuta, fecha y hora son requeridos']);
                exit;
            }
            
            // Validar que el terapeuta existe
            $stmt = $pdo->prepare("SELECT id FROM terapeutas WHERE id = ?");
            $stmt->execute([$terapeutaId]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Terapeuta no válido']);
                exit;
            }
            
            $horaFin = date('H:i:s', strtotime($horaInicio) + 3600); // 1 hora
            
            // Verificar disponibilidad
            $stmt = $pdo->prepare("
                SELECT id FROM citas 
                WHERE terapeuta_id = ? AND fecha = ? 
                AND estado NOT IN ('cancelada')
                AND ((hora_inicio <= ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin >= ?))
            ");
            $stmt->execute([$terapeutaId, $fecha, $horaInicio, $horaInicio, $horaFin, $horaFin]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Este horario ya está ocupado. Por favor elige otra fecha u hora.']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO citas (paciente_id, terapeuta_id, fecha, hora_inicio, hora_fin, modalidad, notas, tipo_consulta, costo, genero_especialista)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $terapeutaId,
                $fecha,
                $horaInicio,
                $horaFin,
                $modalidad,
                $input['notas'] ?? null,
                $tipoConsulta,
                $costo,
                $input['genero_especialista'] ?? null
            ]);
            
            $citaId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
            $stmt->execute([$citaId]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Solo los pacientes pueden agendar citas']);
        }
        break;
        
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de cita requerido']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
        $stmt->execute([$id]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cita) {
            http_response_code(404);
            echo json_encode(['error' => 'Cita no encontrada']);
            exit;
        }
        
        $allowedUpdates = ['estado', 'notas', 'fecha', 'hora_inicio', 'hora_fin', 'modalidad', 'tipo_consulta', 'costo', 'genero_especialista'];
        $updates = [];
        $params = [];
        
        foreach ($allowedUpdates as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $field === 'fecha' || strpos($field, 'hora') !== false 
                    ? $input[$field] 
                    : $input[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay campos para actualizar']);
            exit;
        }
        
        if (isset($input['hora_inicio']) && !isset($input['hora_fin'])) {
            $input['hora_fin'] = date('H:i:s', strtotime($input['hora_inicio']) + 3600);
            $updates[] = "hora_fin = ?";
            $params[] = $input['hora_fin'];
        }
        
        $params[] = $id;
        $sql = "UPDATE citas SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
        
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;
        
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de cita requerido']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
        $stmt->execute([$id]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cita) {
            http_response_code(404);
            echo json_encode(['error' => 'Cita no encontrada']);
            exit;
        }
        
        if ($userRol === 'paciente' && $cita['paciente_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes cancelar esta cita']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
