# CenTI-R - Sistema de Gestión de Citas de Terapia Psicológica

Sistema web para la gestión de citas de terapia psicológica, desarrollado con **PHP** (backend/API) y **Astro** (frontend).

## Características

- **Login y registro** de usuarios (pacientes)
- **Gestión de citas**: agendar, ver, cancelar
- **Panel de administración** para administradores
- **API REST** para autenticación, citas y terapeutas
- Base de datos **SQLite** (sin instalación adicional)
- Diseño responsivo siguiendo el prototipo CenTI-R (colores rosa/magenta, branding)

## Requisitos

- PHP 8.0 o superior (extensión PDO habilitada)
- Node.js 18+
- npm o pnpm
- Para MySQL: servidor MySQL 5.7+ o MariaDB 10.3+

## Instalación

### Con SQLite (por defecto)
```bash
npm install
php api/seed.php
```

### Con MySQL
```bash
# 1. Crear la base de datos y tablas
mysql -u root -p < database/schema_mysql.sql

# 2. Configurar .env
cp .env.example .env
# Editar .env: DB_DRIVER=mysql, DB_HOST, DB_NAME, DB_USER, DB_PASS

# 3. Datos de prueba
npm install
php api/seed.php
```

## Ejecución

Necesitas **dos terminales**:

### Terminal 1 - API PHP
```bash
npm run api
# o: php -S localhost:8080 -t api api/router.php
```

### Terminal 2 - Frontend Astro
```bash
npm run dev
```

Luego abre: **http://localhost:4321**

## Usuarios por defecto

| Rol       | Email              | Contraseña   |
|----------|--------------------|--------------|
| Admin    | admin@centir.mx    | admin123     |
| Terapeuta| [nombre]@centir.mx | terapeuta123 |

Los terapeutas se crean al ejecutar `php api/seed.php`.

## Estructura del proyecto

```
Centir/
├── api/                 # Backend PHP
│   ├── config/          # Configuración (DB)
│   ├── data/            # Base SQLite (generada)
│   ├── auth.php         # Login, registro, logout
│   ├── citas.php        # CRUD citas
│   ├── terapeutas.php   # Listado terapeutas
│   ├── router.php       # Router para servidor PHP
│   └── seed.php         # Datos de prueba
├── src/
│   └── pages/
│       ├── index.astro       # Login/registro
│       ├── dashboard/        # Panel del paciente
│       └── admin/            # Panel administradores
├── astro.config.mjs
└── package.json
```

## Pagos y recordatorios

### Métodos de pago
- **Tarjeta**: Formulario (configurar pasarela para producción)
- **Efectivo**: Genera comprobante imprimible
- **Transferencia**: Datos bancarios + comprobante
- **PayPal**: Configura `TU_CLIENT_ID_PAYPAL` en `src/pages/pagos/index.astro`

### Recordatorios WhatsApp
Configura Twilio en `.env`:
```
TWILIO_ACCOUNT_SID=tu_sid
TWILIO_AUTH_TOKEN=tu_token
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
```
Desde el panel admin: "Enviar recordatorio WhatsApp" o "Confirmar cita por WhatsApp".

## API Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET/POST | /api/auth | Sesión / login / registro |
| GET | /api/citas | Listar citas |
| POST | /api/citas | Crear cita |
| GET | /api/citas/:id | Obtener cita |
| PUT | /api/citas/:id | Actualizar cita |
| DELETE | /api/citas/:id | Cancelar cita |
| GET | /api/terapeutas | Listar terapeutas |
| POST | /api/pagos | Registrar pago |
| GET | /api/pagos/comprobante/:folio | Comprobante (HTML) |
| POST | /api/recordatorios | Enviar recordatorio/confirmar por WhatsApp |

## Producción

1. **Frontend**: `npm run build` → carpeta `dist/`
2. **API PHP**: Configurar Apache/Nginx para que sirva la carpeta `api/` y use `router.php` como front controller.
