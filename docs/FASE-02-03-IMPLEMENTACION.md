# Fase 2 y 3 — Catálogos, Geografía y Registro NNA

## Backend implementado

### Geografía Venezuela
- Tablas: `estados`, `municipios`, `parroquias`
- 24 estados + municipios/parroquias de DC y Miranda (extensible)
- Endpoints: `/api/v1/geography/*`

### Catálogos administrables
- Tabla unificada `catalogs` con tipos: género, colores, discapacidad, necesidades, parentescos, etc.
- Endpoints CRUD: `/api/v1/catalogs/{type}`
- Bundle offline: `GET /api/v1/catalogs/bundle`

### Ubicaciones de atención
- Tabla `attention_locations` (hospitales, refugios, campamentos, plazas...)
- CRUD: `/api/v1/attention-locations`

### Registro NNA (Fase 3)
- Tablas: `nna_registrations`, `nna_acompanantes`, `nna_catalog`, `nna_photos`
- UUID local + servidor para sync offline
- Endpoints: CRUD `/api/v1/nna`, batch sync `/api/v1/nna/sync/batch`, fotos `/api/v1/nna/{id}/photos`

## Frontend

- Limpieza completa de boilerplate POS/CRM
- Rutas: Dashboard, Operativos, Catálogos, Registro NNA, Wizard
- Cola offline localStorage para registros NNA
- Sincronización automática al reconectar

## Migrar

```bash
cd idenna
php artisan migrate --seed
php artisan storage:link
```
