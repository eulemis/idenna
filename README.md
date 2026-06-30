# SIRP-NNA

**Sistema Integral de Registro y Protección de Niños, Niñas y Adolescentes**

Plataforma multi-operativo para la gestión de emergencias nacionales. Permite registrar, caracterizar y dar seguimiento a NNA afectados por contingencias (terremotos, inundaciones, deslaves, etc.) con soporte offline-first para brigadas en campo.

## Stack

- Laravel 12
- Sanctum (API tokens)
- Spatie Permission + Activity Log
- MySQL 8

## Inicio rápido

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB en .env
php artisan migrate --seed
php artisan serve
```

## Frontend

El frontend PWA está en el repositorio `started-kit/`.

## Documentación

- [Instalación](docs/INSTALACION.md)
- [Arquitectura Fase 1](docs/FASE-01-ARQUITECTURA.md)

## Licencia

Uso institucional — IDENNA / Sistema Nacional de Protección.
