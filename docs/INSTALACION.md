# SIRP-NNA — Instalación Backend

Sistema Integral de Registro y Protección de Niños, Niñas y Adolescentes.

## Requisitos

- PHP 8.2+
- Composer 2.x
- MySQL 8.x
- Extensiones PHP: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`

## Instalación

```bash
cd idenna
composer install
cp .env.example .env
php artisan key:generate
```

### Base de datos

1. Crear la base de datos MySQL:

```sql
CREATE DATABASE idenna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Configurar credenciales en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=idenna
DB_USERNAME=root
DB_PASSWORD=tu_password
FRONTEND_URL=http://localhost:5173
```

3. Ejecutar migraciones y seeders:

```bash
php artisan migrate --seed
```

### Servidor de desarrollo

```bash
php artisan serve
```

API disponible en: `http://localhost:8000/api/v1`

## Credenciales iniciales

| Campo | Valor |
|-------|-------|
| Email | `admin@idenna.gob.ve` |
| Contraseña | `Admin123!` |

> Cambiar la contraseña antes de desplegar en producción.

## Endpoints Fase 1

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/health` | Estado del servicio |
| POST | `/api/v1/login` | Autenticación |
| GET | `/api/v1/me` | Usuario autenticado |
| POST | `/api/v1/logout` | Cerrar sesión |
| GET | `/api/v1/operativos` | Listar operativos |
| POST | `/api/v1/operativos` | Crear operativo |
| GET | `/api/v1/operativos/{id}` | Ver operativo |
| PUT | `/api/v1/operativos/{id}` | Actualizar operativo |
| DELETE | `/api/v1/operativos/{id}` | Eliminar operativo |

## Documentación

Ver [docs/FASE-01-ARQUITECTURA.md](docs/FASE-01-ARQUITECTURA.md) para decisiones técnicas y roadmap.
