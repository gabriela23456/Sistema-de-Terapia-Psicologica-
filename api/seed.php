<?php
/**
 * Script para poblar datos de prueba - CenTI-R
 * Ejecutar: php api/seed.php
 */

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Crear terapeutas de prueba (usuarios + registro en terapeutas)
$terapeutas = [
    ['María García López', 'maria.garcia@centir.mx', 'Terapia cognitivo-conductual', 'Especialista en ansiedad y depresión. Más de 10 años de experiencia.', 'mujer'],
    ['Carlos Hernández Ruiz', 'carlos.hernandez@centir.mx', 'Psicología infantil', 'Atención a niños y adolescentes. Terapia familiar.', 'hombre'],
    ['Ana Martínez Sánchez', 'ana.martinez@centir.mx', 'Trauma y estrés postraumático', 'EMDR y técnicas de regulación emocional.', 'mujer']
];

foreach ($terapeutas as $t) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$t[1]]);
    if ($stmt->fetch()) continue; // Ya existe

    $hash = password_hash('terapeuta123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'terapeuta')")
        ->execute([$t[0], $t[1], $hash]);
    $userId = $pdo->lastInsertId();

    $genero = $t[4] ?? null;
    $pdo->prepare("INSERT INTO terapeutas (usuario_id, especialidad, descripcion, genero) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $t[2], $t[3], $genero]);
}

echo "Datos de prueba creados correctamente.\n";
echo "Usuarios admin: admin@centir.mx / admin123\n";
echo "Terapeutas: [email]@centir.mx / terapeuta123\n";
