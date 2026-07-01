# SIRP-NNA — Comandos para producción

Referencia de comandos usados en el despliegue e importación masiva (terremoto). Ejecutar desde el directorio `idenna` salvo que se indique lo contrario.

## 1. Instalación inicial

```bash
cd /ruta/al/proyecto/idenna
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Configurar `.env` (BD, `FRONTEND_URL`, `CONAPDIS_DB_*` si aplica geografía legacy).

```bash
php artisan migrate --force
php artisan db:seed --force
```

Solo geografía (estados/municipios/parroquias desde BD conapdis):

```bash
php artisan config:clear
php artisan db:seed --class=GeographySeeder --force
```

Frontend (`started-kit`):

```bash
cd /ruta/al/proyecto/started-kit
npm ci
npm run build
```

Servidor API (desarrollo):

```bash
php artisan serve
# API: http://localhost:8000/api/v1
```

Credenciales iniciales: `admin@idenna.gob.ve` / `Admin123!` — **cambiar antes de producción**.

---

## 2. Importación masiva NNA (Google Forms terremoto)

Archivo de referencia: `DATA NNA TERREMOTO (3).xlsx`

### Primera importación (crea registros nuevos)

```bash
php artisan nna:import-terremoto "/ruta/DATA NNA TERREMOTO (3).xlsx"
```

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--operativo=ID` | Operativo destino (default: `TER-2026-VE-001`) |
| `--user=ID` | Usuario que figura como importador del lote (default: admin) |
| `--download-photos` | Descargar fotos de Google Drive al storage local |
| `--allow-duplicates` | No omitir filas ya importadas |

Ejemplo con fotos:

```bash
php artisan nna:import-terremoto "/ruta/DATA NNA TERREMOTO (3).xlsx" --download-photos
```

### Rehidratar metadata (registros ya importados)

Actualiza peso, tallas, refugio/hospital, cédula del encuestador, etc. sin duplicar filas:

```bash
php artisan nna:rehydrate-import-metadata "/ruta/DATA NNA TERREMOTO (3).xlsx"
```

Opcional: `--operativo=ID`

---

## 3. Registradores del Excel → usuarios del sistema

Requiere migración con campo `document_id` en `users`:

```bash
php artisan migrate --force
```

### Crear usuarios + vincular registros NNA por cédula

```bash
php artisan nna:import-registradores "/ruta/DATA NNA TERREMOTO (3).xlsx"
```

Opciones:

| Opción | Descripción |
|--------|-------------|
| `--operativo=ID` | Operativo asignado a usuarios nuevos |
| `--link-only` | Solo vincular `registered_by` sin crear usuarios |
| `--dry-run` | Simular sin escribir en BD |

Orden recomendado en producción:

```bash
# 1. Migraciones al día
php artisan migrate --force

# 2. Importar NNA (si aún no están)
php artisan nna:import-terremoto "/ruta/DATA NNA TERREMOTO (3).xlsx"

# 3. Completar metadata en registros existentes
php artisan nna:rehydrate-import-metadata "/ruta/DATA NNA TERREMOTO (3).xlsx"

# 4. Crear usuarios registradores y vincular por cédula
php artisan nna:import-registradores "/ruta/DATA NNA TERREMOTO (3).xlsx"
```

Usuarios creados desde Excel:

- Rol: `registrador`
- Email: `{cedula}@registradores.idenna.local`
- Contraseña inicial: **`Registrador123!`** (configurable)

Configuración en `.env`:

```env
IMPORT_REGISTRAR_DEFAULT_PASSWORD=Registrador123!
```

Opciones del comando:

| Opción | Descripción |
|--------|-------------|
| `--password=Clave123!` | Sobrescribe la contraseña por defecto en esa ejecución |
| `--reset-password` | Aplica la contraseña también a usuarios **ya existentes** (por cédula) |

Ejemplo — crear usuarios y resetear clave de los que ya existían con contraseña aleatoria:

```bash
php artisan nna:import-registradores "/ruta/DATA NNA TERREMOTO (3).xlsx" --reset-password
```

Registros manuales en el sistema usan el usuario **logueado** (`registered_by`).

---

## 4. Despliegue en un solo dominio (recomendado)

El frontend React se compila y se copia dentro de Laravel en `public/app/`. En producción todo vive en **un solo repositorio** (`idenna`):

| Ruta | Qué es |
|------|--------|
| `https://tu-dominio.gob.ve/` | Redirige a `/app` |
| `https://tu-dominio.gob.ve/app/` | Interfaz SIRP-NNA (PWA) |
| `https://tu-dominio.gob.ve/api/v1/` | API Laravel |

### En tu máquina (antes de subir)

```bash
# 1. Compilar front y copiar a idenna/public/app
cd /ruta/started-kit
npm ci
chmod +x scripts/deploy-to-idenna.sh
./scripts/deploy-to-idenna.sh

# 2. Verificar localmente (con idenna.test o php artisan serve)
cd ../idenna
php artisan serve
# Abrir http://127.0.0.1:8000/app/
```

El build usa `VITE_API_URL=/api/v1` (ruta relativa), así la API apunta al mismo dominio sin recompilar por entorno.

### En el servidor

```bash
cd /var/www/idenna
composer install --no-dev --optimize-autoloader
cp .env.example .env   # o subir tu .env de producción
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

`.env` de producción (ejemplo):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.gob.ve
FRONTEND_URL=https://tu-dominio.gob.ve
```

**Document root de Nginx/Apache:** `idenna/public` (no la raíz del proyecto).

Opcional — cola para exportaciones:

```bash
php artisan queue:work --tries=3
```

### Subir solo el repo `idenna`

Incluye en el deploy (git, rsync o zip):

- Todo el código Laravel (`app/`, `routes/`, `config/`, …)
- `public/app/` con el build del frontend
- **No** subas `node_modules/` de `started-kit` al servidor (no hacen falta)

---

## 5. Cache y optimización (producción)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Tras cambios en `.env` o código:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

---

## 6. Cola y storage (si se activan exportaciones async / fotos)

```bash
php artisan storage:link
php artisan queue:work --tries=3
```

---

## 7. Verificación rápida

```bash
php artisan tinker --execute="
echo App\Models\NnaRegistration::count().' registros NNA'.PHP_EOL;
echo App\Models\User::whereNotNull('document_id')->count().' usuarios con cédula'.PHP_EOL;
"
```

---

## 8. Resumen de comandos artisan NNA

| Comando | Propósito |
|---------|-----------|
| `nna:import-terremoto {file}` | Importar filas Excel/CSV Google Forms |
| `nna:rehydrate-import-metadata {file}` | Actualizar metadata de registros ya importados |
| `nna:import-registradores {file}` | Crear usuarios encuestadores y vincular por cédula |
