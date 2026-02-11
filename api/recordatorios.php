<?php
/**
 * API Recordatorios WhatsApp - CenTI-R
 * Envía recordatorios para confirmar citas
 * Requiere: Twilio (configurar en .env o variables de entorno)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

function cargarEnv() {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $_ENV[trim($k)] = trim($v, '"\'');
            }
        }
    }
}

cargarEnv();

$twilioSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? getenv('TWILIO_ACCOUNT_SID');
$twilioToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? getenv('TWILIO_AUTH_TOKEN');
$twilioFrom = $_ENV['TWILIO_WHATSAPP_FROM'] ?? getenv('TWILIO_WHATSAPP_FROM') ?? 'whatsapp:+14155238886';

function enviarWhatsApp($to, $mensaje, $sid, $token, $from) {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';
    $to = preg_replace('/[^0-9]/', '', $to);
    if (strlen($to) === 10) $to = '52' . $to;
    $body = [
        'To' => 'whatsapp:+' . $to,
        'From' => $from,
        'Body' => $mensaje
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'response' => json_decode($res, true)];
}

$db = new Database();
$pdo = $db->getConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$citaId = (int)($input['cita_id'] ?? 0);
$accion = $input['accion'] ?? 'recordatorio'; // recordatorio | confirmar

if (!$citaId) {
    http_response_code(400);
    echo json_encode(['error' => 'cita_id requerido']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, u.nombre as paciente_nombre, u.telefono
    FROM citas c
    JOIN usuarios u ON c.paciente_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$citaId]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Cita no encontrada']);
    exit;
}

if (empty($cita['telefono'])) {
    http_response_code(400);
    echo json_encode(['error' => 'El paciente no tiene teléfono registrado']);
    exit;
}

$fecha = date('d/m/Y', strtotime($cita['fecha']));
$hora = substr($cita['hora_inicio'], 0, 5);

if ($accion === 'confirmar') {
    $pdo->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = ?")->execute([$citaId]);
}

$mensaje = "¡Hola {$cita['paciente_nombre']}! CenTI-R te recuerda tu cita de terapia el {$fecha} a las {$hora}. ";
$mensaje .= $accion === 'confirmar' ? "Tu cita ha sido confirmada. ¡Te esperamos!" : "Confirma tu asistencia respondiendo a este mensaje.";

if ($twilioSid && $twilioToken) {
    $result = enviarWhatsApp($cita['telefono'], $mensaje, $twilioSid, $twilioToken, $twilioFrom);
    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo json_encode(['success' => true, 'message' => 'Recordatorio enviado por WhatsApp', 'sid' => $result['response']['sid'] ?? null]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al enviar WhatsApp', 'detail' => $result['response']['message'] ?? '']);
    }
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Recordatorio simulado (configura Twilio para envío real)',
        'demo' => true,
        'mensaje_preview' => $mensaje,
        'telefono' => $cita['telefono']
    ]);
}
