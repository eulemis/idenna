# Fase 1 — Fundamentos de la Plataforma de Gestión de Emergencias

## 1. Visión del producto

**SIRP-NNA** (Sistema Integral de Registro y Protección de Niños, Niñas y Adolescentes) es una plataforma multi-operativo diseñada para registrar, caracterizar y dar seguimiento a NNA afectados por contingencias nacionales.

No está limitada a un evento específico (terremoto). Cada registro queda asociado a un **Operativo** (emergencia/contingencia), lo que permite reutilizar el sistema para inundaciones, deslaves, incendios, migraciones, epidemias u otros eventos.

## 2. Decisiones arquitectónicas

### 2.1 Separación frontend / backend

| Capa | Repositorio | Stack |
|------|-------------|-------|
| Frontend PWA | `started-kit/` | Vite, React 19*, TypeScript, Tailwind, shadcn/ui, Dexie, Workbox |
| API REST | `idenna/` | Laravel 12, Sanctum, Spatie Permission, MySQL |

\* El starter-kit usa React 18; la actualización a React 19 se planifica en Fase 2.

### 2.2 Patrón modular (backend)

```
app/
├── Enums/              # Estados y tipos tipados
├── Http/
│   ├── Controllers/Api/
│   ├── Requests/       # Validación de entrada
│   └── Resources/      # Transformación JSON consistente
├── Models/
└── Services/           # Lógica de negocio reutilizable
```

### 2.3 Autenticación

- **Sanctum** con tokens Bearer para la PWA (offline-first, SPA desacoplada).
- Tokens con nombre de dispositivo para revocación selectiva.
- Roles y permisos con **Spatie Laravel Permission**.

### 2.4 Auditoría

- **Spatie Activity Log** en modelos críticos desde Fase 1 (User, Operativo).
- Extensión a NNA y catálogos en fases posteriores.

### 2.5 Entidad central: Operativo

Todo dato operativo (NNA, reportes, importaciones) se vincula a un operativo activo.

| Campo | Descripción |
|-------|-------------|
| `uuid` | Identificador global único |
| `code` | Código corto (ej. `TER-2026-VE-001`) |
| `name` | Nombre descriptivo |
| `type` | Tipo de contingencia (terremoto, inundación, etc.) |
| `status` | `draft`, `active`, `closed`, `archived` |
| `started_at` / `ended_at` | Ventana temporal del operativo |

### 2.6 Roles iniciales

| Rol | Descripción |
|-----|-------------|
| `super-admin` | Acceso total, gestión de operativos y usuarios |
| `admin-nacional` | Administración nacional, reportes y catálogos |
| `coordinador-estatal` | Coordinación regional dentro de un operativo |
| `registrador` | Captura de NNA en campo (offline) |
| `consultor` | Solo lectura y exportación |

### 2.7 Permisos granulares

- `operativos.manage`, `operativos.view`
- `users.manage`, `users.view`
- `catalogs.manage`, `catalogs.view`
- `nna.register`, `nna.view`, `nna.edit`
- `reports.view`, `reports.export`
- `imports.manage`

### 2.8 API REST

- Prefijo: `/api/v1`
- Respuestas JSON con estructura `{ data, message?, meta? }`
- Códigos HTTP estándar
- Documentación OpenAPI en Fase 2

### 2.9 Offline-first (frontend)

- IndexedDB (Dexie) como store local primario.
- Cola de sincronización con UUID local por registro.
- Resolución de conflictos: **last-write-wins con timestamp del servidor** como fallback; conflictos explícitos en Fase 4.

### 2.10 Base de datos

- MySQL 8, charset `utf8mb4_unicode_ci`
- Claves foráneas con `restrict` / `cascade` según contexto
- Soft deletes en entidades administrables
- Índices en campos de búsqueda frecuente (estado, municipio, operativo_id)

## 3. Alcance de Fase 1 (completado en esta entrega)

- [x] Instalación Laravel 12 con dependencias base
- [x] Migraciones: users extendido, operativos, roles/permisos, auditoría
- [x] API de autenticación (login, logout, me)
- [x] API CRUD de operativos (admin)
- [x] Seeders con roles, permisos y usuario admin
- [x] Configuración CORS para frontend
- [x] Documentación de instalación
- [x] Frontend: branding IDENNA, variables de entorno, servicio auth simplificado

## 4. Roadmap de fases

| Fase | Contenido |
|------|-----------|
| **1** | Fundamentos, auth, operativos, instalación |
| **2** | Catálogos administrables + geografía Venezuela |
| **3** | Registro NNA (wizard) + fotos + offline sync |
| **4** | Importación Google Forms + resolución conflictos |
| **5** | Dashboard nacional + reportes + exportaciones |
| **6** | Pruebas, hardening, manuales, despliegue |

## 5. Variables de entorno

### Backend (`idenna/.env`)

```env
APP_NAME="SIRP-NNA"
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_DATABASE=idenna
FRONTEND_URL=http://localhost:5173
```

### Frontend (`started-kit/.env`)

```env
VITE_APP_NAME="SIRP-NNA"
VITE_API_URL=http://localhost:8000/api/v1
```
