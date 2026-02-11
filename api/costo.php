<?php
/**
 * API para calcular costo de consulta - CenTI-R
 */

header('Content-Type: application/json; charset=utf-8');

// Tarifas base (configurables)
$tarifas = [
    'individual' => ['presencial' => 600, 'en_linea' => 500],
    'pareja' => ['presencial' => 900, 'en_linea' => 750],
    'familiar' => ['presencial' => 1000, 'en_linea' => 850],
    'infantil' => ['presencial' => 550, 'en_linea' => 450]
];

$tipo = $_GET['tipo'] ?? 'individual';
$modalidad = $_GET['modalidad'] ?? 'presencial';

$tipo = in_array($tipo, array_keys($tarifas)) ? $tipo : 'individual';
$modalidad = in_array($modalidad, ['presencial', 'en_linea']) ? $modalidad : 'presencial';

$costo = $tarifas[$tipo][$modalidad] ?? 600;

echo json_encode(['costo' => $costo, 'moneda' => 'MXN']);
