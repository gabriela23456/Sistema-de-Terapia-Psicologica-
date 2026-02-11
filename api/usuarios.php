<?php
/**
 * API de usuarios/perfil - CenTI-R
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, nombre, apellido_paterno, apellido_materno, email, telefono, fecha_nacimiento FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }
    echo json_encode($user);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $allowed = ['nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'fecha_nacimiento'];
    $updates = [];
    $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $updates[] = "$f = ?";
            $params[] = $input[$f];
        }
    }
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Sin campos para actualizar']);
        exit;
    }
    $params[] = $userId;
    $pdo->prepare("UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
    $stmt = $pdo->prepare("SELECT id, nombre, apellido_paterno, apellido_materno, email, telefono, fecha_nacimiento FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
