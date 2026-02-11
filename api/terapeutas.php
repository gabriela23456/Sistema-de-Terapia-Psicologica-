<?php
/**
 * API de terapeutas - CenTI-R
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api/terapeutas?#', '', $path);
$path = trim($path, '/');
$id = $path ? (int)$path : null;

if ($id) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre, u.email, u.telefono
        FROM terapeutas t
        JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.id = ? AND u.activo = 1
    ");
    $stmt->execute([$id]);
    $terapeuta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$terapeuta) {
        http_response_code(404);
        echo json_encode(['error' => 'Terapeuta no encontrado']);
        exit;
    }
    
    echo json_encode($terapeuta);
} else {
    $genero = $_GET['genero'] ?? null;
    $where = "u.activo = 1";
    $params = [];
    if ($genero && in_array($genero, ['hombre', 'mujer'])) {
        $where .= " AND (t.genero = ? OR t.genero IS NULL OR t.genero = '')";
        $params[] = $genero;
    }
    $stmt = $pdo->prepare("
        SELECT t.id, t.especialidad, t.descripcion, t.genero, u.nombre
        FROM terapeutas t
        JOIN usuarios u ON t.usuario_id = u.id
        WHERE $where
        ORDER BY u.nombre
    ");
    $stmt->execute($params);
    $terapeutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($terapeutas);
}
