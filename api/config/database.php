<?php
/**
 * Configuración de base de datos - CenTI-R
 * Soporta MySQL y SQLite
 */

class Database {
    private $db;
    private $driver;

    public function __construct() {
        $this->connect();
    }

    private function loadEnv() {
        $envPath = __DIR__ . '/../../.env';
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

    private function connect() {
        $this->loadEnv();
        $driver = $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?? 'sqlite';

        if ($driver === 'mysql') {
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '3306';
            $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'centir';
            $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $this->driver = 'mysql';
            $this->db = new PDO($dsn, $user, $pass);
        } else {
            $dbPath = __DIR__ . '/../data/centir.db';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $this->driver = 'sqlite';
            $this->db = new PDO('sqlite:' . $dbPath);
        }

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->driver === 'sqlite') {
            $this->initSchemaSqlite();
        } else {
            $this->initSchemaMysql();
        }
        $this->createAdminIfNeeded();
    }

    private function initSchemaSqlite() {
        $sql = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            rol TEXT DEFAULT 'paciente' CHECK(rol IN ('paciente', 'terapeuta', 'admin')),
            telefono TEXT,
            activo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS terapeutas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL UNIQUE,
            especialidad TEXT,
            descripcion TEXT,
            disponibilidad TEXT,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        );
        CREATE TABLE IF NOT EXISTS citas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            paciente_id INTEGER NOT NULL,
            terapeuta_id INTEGER NOT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            estado TEXT DEFAULT 'pendiente',
            notas TEXT,
            modalidad TEXT DEFAULT 'presencial',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (paciente_id) REFERENCES usuarios(id),
            FOREIGN KEY (terapeuta_id) REFERENCES terapeutas(id)
        );
        CREATE TABLE IF NOT EXISTS sesiones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cita_id INTEGER NOT NULL,
            notas_terapeuta TEXT,
            progreso TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cita_id) REFERENCES citas(id)
        );
        CREATE INDEX IF NOT EXISTS idx_citas_paciente ON citas(paciente_id);
        CREATE INDEX IF NOT EXISTS idx_citas_terapeuta ON citas(terapeuta_id);
        CREATE INDEX IF NOT EXISTS idx_citas_fecha ON citas(fecha);
        CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email);
        ";
        $this->db->exec($sql);
        $this->runMigrationsSqlite();
    }

    private function runMigrationsSqlite() {
        $migrations = [
            "ALTER TABLE usuarios ADD COLUMN apellido_paterno TEXT",
            "ALTER TABLE usuarios ADD COLUMN apellido_materno TEXT",
            "ALTER TABLE usuarios ADD COLUMN fecha_nacimiento DATE",
            "ALTER TABLE terapeutas ADD COLUMN genero TEXT",
            "ALTER TABLE citas ADD COLUMN tipo_consulta TEXT DEFAULT 'individual'",
            "ALTER TABLE citas ADD COLUMN costo DECIMAL(10,2)",
            "ALTER TABLE citas ADD COLUMN genero_especialista TEXT",
            "CREATE TABLE IF NOT EXISTS pagos (id INTEGER PRIMARY KEY AUTOINCREMENT, cita_id INTEGER NOT NULL, usuario_id INTEGER NOT NULL, metodo TEXT NOT NULL, monto DECIMAL(10,2) NOT NULL, referencia TEXT, estado TEXT DEFAULT 'completado', comprobante_folio TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (cita_id) REFERENCES citas(id), FOREIGN KEY (usuario_id) REFERENCES usuarios(id))"
        ];
        foreach ($migrations as $sql) {
            try { $this->db->exec($sql); } catch (PDOException $e) { }
        }
    }

    private function initSchemaMysql() {
        $tables = ['usuarios', 'terapeutas', 'citas', 'sesiones', 'pagos'];
        foreach ($tables as $t) {
            $stmt = $this->db->query("SHOW TABLES LIKE '$t'");
            if ($stmt->rowCount() === 0) {
                throw new PDOException("La tabla '$t' no existe. Ejecuta database/schema_mysql.sql primero.");
            }
        }
    }

    private function createAdminIfNeeded() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin'");
        if ($stmt->fetchColumn() == 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $this->db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')")
                ->execute(['Administrador', 'admin@centir.mx', $hash]);
        }
    }

    public function getConnection() {
        return $this->db;
    }
}
