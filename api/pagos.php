<?php
/**
 * API de pagos - CenTI-R
 * Métodos: tarjeta, efectivo, transferencia, paypal
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

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api/pagos?#', '', $path);
$path = trim($path, '/');
$parts = $path ? explode('/', $path) : [];
$action = $parts[0] ?? '';

if ($action === 'comprobante' && isset($parts[1])) {
    // Comprobante puede ser público por folio
    $folio = $parts[1];
    $stmt = $pdo->prepare("
        SELECT p.*, c.fecha, c.hora_inicio, u.nombre as paciente_nombre, u.email
        FROM pagos p
        JOIN citas c ON p.cita_id = c.id
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.comprobante_folio = ?
    ");
    $stmt->execute([$folio]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pago) {
        header('Content-Type: text/html; charset=utf-8');
        echo generarComprobanteHTML($pago);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Comprobante no encontrado']);
    exit;
}

$user = requireAuth();
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $citaId = (int)($input['cita_id'] ?? 0);
    $metodo = $input['metodo'] ?? '';
    $monto = (float)($input['monto'] ?? 0);

    $metodosValidos = ['tarjeta', 'efectivo', 'transferencia', 'paypal'];
    if (!$citaId || !in_array($metodo, $metodosValidos) || $monto <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos de pago inválidos']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.*, u.nombre as paciente_nombre, u.telefono
        FROM citas c
        JOIN usuarios u ON c.paciente_id = u.id
        WHERE c.id = ? AND c.paciente_id = ?
    ");
    $stmt->execute([$citaId, $userId]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cita) {
        http_response_code(404);
        echo json_encode(['error' => 'Cita no encontrada']);
        exit;
    }

    $folio = 'CEN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $referencia = $input['referencia'] ?? null;
    if ($metodo === 'tarjeta') $referencia = $input['token'] ?? 'TARJ-' . substr(md5(uniqid()), 0, 8);
    if ($metodo === 'paypal') $referencia = $input['order_id'] ?? 'PAYPAL-' . uniqid();

    $stmt = $pdo->prepare("
        INSERT INTO pagos (cita_id, usuario_id, metodo, monto, referencia, comprobante_folio)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$citaId, $userId, $metodo, $monto, $referencia, $folio]);

    $pdo->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = ?")->execute([$citaId]);

    $pagoId = $pdo->lastInsertId();
    $pago = [
        'id' => (int)$pagoId,
        'cita_id' => $citaId,
        'metodo' => $metodo,
        'monto' => $monto,
        'folio' => $folio,
        'comprobante_url' => '/api/pagos/comprobante/' . $folio
    ];
    echo json_encode(['success' => true, 'pago' => $pago]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'cita' && isset($parts[1])) {
        $citaId = (int)$parts[1];
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ? AND paciente_id = ?");
        $stmt->execute([$citaId, $userId]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cita) {
            http_response_code(404);
            echo json_encode(['error' => 'Cita no encontrada']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM pagos WHERE cita_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$citaId]);
        $ultimoPago = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['cita' => $cita, 'pago' => $ultimoPago]);
        exit;
    }
    // GET /api/pagos = listar mis pagos (historial)
    if ($action === '' || $action === 'list' || !$action) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.fecha, c.hora_inicio, c.terapeuta_id
            FROM pagos p
            JOIN citas c ON p.cita_id = c.id
            WHERE p.usuario_id = ?
            ORDER BY p.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['pagos' => $pagos]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);

function generarComprobanteHTML($pago) {
    $metodos = ['tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia bancaria', 'paypal' => 'PayPal'];
    $m = $metodos[$pago['metodo']] ?? $pago['metodo'];
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Comprobante CenTI-R</title></head><body style="font-family:sans-serif;max-width:400px;margin:2rem auto;padding:2rem;border:1px solid #ddd;border-radius:8px">
    <h2 style="color:#E91E8C">CenTI-R - Comprobante de pago</h2>
    <p><strong>Folio:</strong> ' . htmlspecialchars($pago['comprobante_folio']) . '</p>
    <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($pago['created_at'])) . '</p>
    <p><strong>Paciente:</strong> ' . htmlspecialchars($pago['paciente_nombre']) . '</p>
    <p><strong>Cita:</strong> ' . htmlspecialchars($pago['fecha'] . ' ' . substr($pago['hora_inicio'], 0, 5)) . '</p>
    <p><strong>Método:</strong> ' . $m . '</p>
    <p><strong>Monto:</strong> $' . number_format($pago['monto'], 2) . ' MXN</p>
    <p><strong>Referencia:</strong> ' . htmlspecialchars($pago['referencia'] ?? '-') . '</p>
    <hr><p style="font-size:0.85rem;color:#666">Gracias por tu pago. CenTI-R</p>
    <script>window.onload=function(){window.print()}</script>
    </body></html>';
}
