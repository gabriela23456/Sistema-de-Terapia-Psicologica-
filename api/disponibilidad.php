<?php
/**
 * API de disponibilidad - CenTI-R
 * Devuelve fechas disponibles para un terapeuta en un mes (estilo SAT/IMSS)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$terapeutaId = (int)($_GET['terapeuta_id'] ?? 0);
$anio = (int)($_GET['anio'] ?? date('Y'));
$mes = (int)($_GET['mes'] ?? date('n'));

if (!$terapeutaId) {
    http_response_code(400);
    echo json_encode(['error' => 'terapeuta_id requerido']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// Horario laboral: Lunes a Sábado 8:00 - 18:00
$horaInicio = '08:00:00';
$horaFin = '18:00:00';
$diasLaborables = [1, 2, 3, 4, 5, 6]; // L-Sáb

$primerDia = sprintf('%04d-%02d-01', $anio, $mes);
$ultimoDia = date('Y-m-t', strtotime($primerDia));
$diasEnMes = (int)date('t', strtotime($primerDia));

// Obtener citas ocupadas del terapeuta en el mes
$stmt = $pdo->prepare("
    SELECT fecha, hora_inicio, hora_fin 
    FROM citas 
    WHERE terapeuta_id = ? 
    AND fecha >= ? AND fecha <= ?
    AND estado NOT IN ('cancelada')
    ORDER BY fecha, hora_inicio
");
$stmt->execute([$terapeutaId, $primerDia, $ultimoDia]);
$citasOcupadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar citas por fecha
$ocupadoPorFecha = [];
foreach ($citasOcupadas as $c) {
    $f = $c['fecha'];
    if (!isset($ocupadoPorFecha[$f])) $ocupadoPorFecha[$f] = [];
    $ocupadoPorFecha[$f][] = ['inicio' => $c['hora_inicio'], 'fin' => $c['hora_fin']];
}

// Para cada día del mes, verificar si hay al menos un slot de 1h libre
$fechasDisponibles = [];
$hoy = date('Y-m-d');

for ($d = 1; $d <= $diasEnMes; $d++) {
    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
    if ($fecha < $hoy) continue; // No mostrar días pasados
    
    $diaSemana = (int)date('N', strtotime($fecha)); // 1=Lun, 7=Dom
    if (!in_array($diaSemana, $diasLaborables)) continue;
    
    $bloquesOcupados = $ocupadoPorFecha[$fecha] ?? [];
    
    // Probar slots cada hora de 8 a 17
    $tieneDisponibilidad = false;
    for ($h = 8; $h < 18; $h++) {
        $slotInicio = sprintf('%02d:00:00', $h);
        $slotFin = sprintf('%02d:00:00', $h + 1);
        
        $libre = true;
        foreach ($bloquesOcupados as $bloque) {
            if (!($slotFin <= $bloque['inicio'] || $slotInicio >= $bloque['fin'])) {
                $libre = false;
                break;
            }
        }
        if ($libre) {
            $tieneDisponibilidad = true;
            break;
        }
    }
    
    if ($tieneDisponibilidad) {
        $fechasDisponibles[] = $fecha;
    }
}

// Si se pide una fecha específica, devolver horarios disponibles
$fechaEspecifica = $_GET['fecha'] ?? null;
if ($fechaEspecifica && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEspecifica)) {
    $stmtH = $pdo->prepare("
        SELECT hora_inicio, hora_fin FROM citas 
        WHERE terapeuta_id = ? AND fecha = ? AND estado NOT IN ('cancelada')
    ");
    $stmtH->execute([$terapeutaId, $fechaEspecifica]);
    $bloquesOcupados = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    $horas = [];
    for ($h = 8; $h < 18; $h++) {
        $slotInicio = sprintf('%02d:00', $h);
        $slotFin = sprintf('%02d:00', $h + 1);
        $libre = true;
        foreach ($bloquesOcupados as $bloque) {
            $bi = substr($bloque['inicio'], 0, 5);
            $bf = substr($bloque['fin'], 0, 5);
            if (!($slotFin <= $bi || $slotInicio >= $bf)) {
                $libre = false;
                break;
            }
        }
        if ($libre) $horas[] = $slotInicio;
    }
    echo json_encode(['fecha' => $fechaEspecifica, 'horas' => $horas]);
    exit;
}

echo json_encode(['fechas' => $fechasDisponibles, 'mes' => $mes, 'anio' => $anio]);
