# Fases 4-6 — Importación, Reportes y Producción

## Fase 4 — Importación Google Forms / Excel

### Backend
- Tabla `import_batches` con historial y errores por fila
- `POST /api/v1/imports/preview` — detecta columnas y sugiere mapeo
- `POST /api/v1/imports` — importa con mapeo de columnas
- `GET /api/v1/imports` — historial

### Frontend
- `/imports` — carga archivo, mapeo visual, vista previa

## Fase 5 — Dashboard y reportes

### Backend
- `GET /api/v1/dashboard/stats` — KPIs, por estado, género, edad, timeline, productividad
- `GET /api/v1/reports/export?format=xlsx|csv|pdf` — exportación

### Frontend
- `/dashboard` — gráficos Recharts (barras, líneas, pastel)
- `/reports` — descarga Excel, CSV, PDF

## Pruebas

```bash
# Requiere extensión pdo_sqlite o configurar DB de pruebas en phpunit.xml
php artisan test
```
- Manual de usuario: `docs/MANUAL-USUARIO.md`
- Health check: `GET /api/v1/health`

## Despliegue producción

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
```

```bash
php artisan config:cache
php artisan route:cache
php artisan migrate --force
npm run build  # frontend → public/app
```
