<?php
/**
 * Punto de entrada API - CenTI-R
 * Ruteo de peticiones a los módulos correspondientes
 */

// CORS: permitir credenciales desde localhost (preview en puerto distinto)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https?://localhost(:\d+)?$#', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$parts = $path ? explode('/', $path) : [];

$module = $parts[0] ?? 'auth';

$allowedModules = ['auth', 'citas', 'terapeutas', 'usuarios', 'costo', 'pagos', 'recordatorios', 'disponibilidad'];
if (!in_array($module, $allowedModules)) {
    $module = 'auth';
}

$file = __DIR__ . '/' . $module . '.php';
if (file_exists($file)) {
    require $file;
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint no encontrado']);
}
