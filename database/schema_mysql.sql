-- CenTI-R - Esquema MySQL
-- Sistema de gestión de citas de terapia psicológica

CREATE DATABASE IF NOT EXISTS centir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE centir;

-- Usuarios (pacientes, terapeutas, admin)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    apellido_paterno VARCHAR(100) DEFAULT NULL,
    apellido_materno VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('paciente', 'terapeuta', 'admin') DEFAULT 'paciente',
    telefono VARCHAR(20) DEFAULT NULL,
    fecha_nacimiento DATE DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuarios_email (email),
    INDEX idx_usuarios_rol (rol)
) ENGINE=InnoDB;

-- Terapeutas
CREATE TABLE IF NOT EXISTS terapeutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    especialidad VARCHAR(255) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    disponibilidad VARCHAR(255) DEFAULT NULL,
    genero ENUM('hombre', 'mujer') DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_terapeutas_usuario (usuario_id)
) ENGINE=InnoDB;

-- Citas de terapia
CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    terapeuta_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'completada', 'cancelada', 'no_asistio') DEFAULT 'pendiente',
    notas TEXT DEFAULT NULL,
    modalidad ENUM('presencial', 'en_linea') DEFAULT 'presencial',
    tipo_consulta VARCHAR(50) DEFAULT 'individual',
    costo DECIMAL(10,2) DEFAULT NULL,
    genero_especialista VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (terapeuta_id) REFERENCES terapeutas(id) ON DELETE CASCADE,
    INDEX idx_citas_paciente (paciente_id),
    INDEX idx_citas_terapeuta (terapeuta_id),
    INDEX idx_citas_fecha (fecha),
    INDEX idx_citas_estado (estado)
) ENGINE=InnoDB;

-- Sesiones de terapia (historial)
CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cita_id INT NOT NULL,
    notas_terapeuta TEXT DEFAULT NULL,
    progreso TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
    INDEX idx_sesiones_cita (cita_id)
) ENGINE=InnoDB;

-- Pagos
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cita_id INT NOT NULL,
    usuario_id INT NOT NULL,
    metodo ENUM('tarjeta', 'efectivo', 'transferencia', 'paypal') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    referencia VARCHAR(255) DEFAULT NULL,
    estado VARCHAR(50) DEFAULT 'completado',
    comprobante_folio VARCHAR(50) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_pagos_cita (cita_id),
    INDEX idx_pagos_usuario (usuario_id),
    INDEX idx_pagos_folio (comprobante_folio)
) ENGINE=InnoDB;

-- El usuario admin se crea automáticamente al iniciar la aplicación (createAdminIfNeeded)
-- Ejecuta php api/seed.php después de crear las tablas para agregar terapeutas de prueba
