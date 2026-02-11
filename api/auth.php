<?php
/**
 * API de autenticación - CenTI-R
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $action = $input['action'] ?? '';
        
        if ($action === 'login') {
            // Iniciar sesión
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email y contraseña requeridos']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas']);
            }
        } elseif ($action === 'register') {
            // Registro de nuevo usuario
            $nombre = trim($input['nombre'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $confirmar = $input['confirmar_password'] ?? '';
            
            $errors = [];
            if (empty($nombre)) $errors[] = 'El nombre es requerido';
            if (empty($email)) $errors[] = 'El email es requerido';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
            if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
            if ($password !== $confirmar) $errors[] = 'Las contraseñas no coinciden';
            
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => ['El email ya está registrado']]);
                exit;
            }
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'paciente')");
            $stmt->execute([$nombre, $email, $hash]);
            
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['user'] = [
                'id' => (int)$userId,
                'nombre' => $nombre,
                'email' => $email,
                'rol' => 'paciente'
            ];
            
            echo json_encode([
                'success' => true,
                'user' => $_SESSION['user']
            ]);
        } elseif ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
        break;
        
    case 'GET':
        if (isset($_SESSION['user'])) {
            echo json_encode(['logged' => true, 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['logged' => false]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
