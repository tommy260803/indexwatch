# IndexWatch v2

Sistema de monitoreo y mantenimiento de índices SQL Server, con notificaciones por WhatsApp y reportes bajo demanda.

**Stack:** Laravel 13 / PHP 8.3 / Tailwind 4 / Vite / PostgreSQL / SQLite (dev)

## Estado

[![CI](https://github.com/your-org/indexwatch/actions/workflows/ci.yml/badge.svg)](https://github.com/your-org/indexwatch/actions/workflows/ci.yml)
[![Tests](https://img.shields.io/badge/tests-54%20passed-green)](https://github.com/your-org/indexwatch)
[![PHP](https://img.shields.io/badge/php-^8.3-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-^13.8-red)](https://laravel.com)

## Arquitectura

```text
                     ┌──────────────────┐
                     │   PostgreSQL      │  (Neon)
                     │  Plano de control │
                     └────────┬─────────┘
                              │
    ┌─────────────────────────┼─────────────────────────┐
    │                         │                         │
    v                         v                         v
┌─────────┐           ┌──────────────┐           ┌──────────────┐
│ Scanner │           │  Dashboard   │           │  WhatsApp    │
│  (Job)  │──────────>│  (Blade/API) │<──────────>  Webhook     │
└────┬────┘           └──────────────┘           └──────┬───────┘
     │                                                  │
     v                                                  v
┌──────────────┐                              ┌──────────────────┐
│ SQL Server   │                              │  Autorización    │
│ (monitoreado)│                              │  + Ventanas      │
└──────────────┘                              │  + Execute Job   │
                                              └──────────────────┘
```

## Características principales

| Funcionalidad | Estado | Descripción |
|--------------|--------|-------------|
| **Escáner SQL Server** | ✅ | Conexión dinámica, DMV (fragmentación, uso, stats, missing indexes, page splits) |
| **Alertas inteligentes** | ✅ | Fabricación, fill factor, page splits, estadísticas obsoletas, missing indexes, unused, duplicados, heaps |
| **Health Score** | ✅ | Algoritmo versionado 0-100 por servidor |
| **WhatsApp** | ✅ | Webhook con HMAC, idempotencia, catálogo de acciones, AuthorizedContact |
| **Mantenimiento** | ✅ | Ventanas por servidor, T-SQL generator, ExecuteMaintenanceJob con locks y auditoría |
| **Dashboard** | ✅ | KPIs reales, alertas, índices paginados, polling 30s |
| **Auditoría** | ✅ | append-only, paginable, filtrable por servidor/actor/acción/fuente |
| **Reportes** | ✅ | HTML/CSV bajo demanda, asíncronos, expiran 7 días |
| **Auth/Roles** | ✅ | admin / operator / viewer, policies, Sanctum API tokens |
| **Rate limiting** | ✅ | Webhook 100/min, API 60/min |
| **Health check** | ✅ | `/up` endpoint |

## Instalación local

```bash
# Requisitos: PHP 8.3+, Composer, Node.js, SQLite

cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Comandos principales

```bash
# Escanear servidores
php artisan indexwatch:scan              # Todos (cola)
php artisan indexwatch:scan --sync       # Síncrono
php artisan indexwatch:scan --server=1 --sync  # Un servidor

# Verificar conectividad
php artisan indexwatch:verify 1

# Cola de trabajos
php artisan queue:work --queue=scans --tries=3 --timeout=300

# Tests
php artisan test

# Migraciones
php artisan migrate:fresh --seed
```

## API REST

| Método | Ruta | Rol |
|--------|------|-----|
| GET | `/api/dashboard/data` | viewer |
| GET/POST | `/api/servers` | viewer/admin |
| PATCH/DELETE | `/api/servers/{id}` | admin |
| POST | `/api/servers/{id}/test-connection` | admin |
| GET/POST/PATCH/DELETE | `/api/maintenance-windows` | admin |
| GET | `/api/maintenance-actions` | operator |
| POST | `/api/maintenance-actions/{id}/cancel` | operator |
| GET | `/api/audit-logs` | operator |
| POST | `/api/reports` | operator |
| GET | `/api/reports/{id}/download` | operator |

## Flujo de monitoreo

```text
Scheduler -> indexwatch:scan
  -> ScanServerJob por servidor activo
  -> SqlServerConnectionFactory (conexión dinámica)
  -> SqlServerInspectorService (DMV: inventory, fragmentation, usage, stats, page splits, missing indexes)
  -> ScanPersistenceService (upsert en PostgreSQL)
  -> AlertDetectionService (fragmentación, fill factor, stats, unused, duplicate, heap, missing)
  -> Dashboard solo consulta PostgreSQL
```

## Flujo de aprobación (WhatsApp)

```text
WhatsApp button -> Webhook (HMAC + idempotencia)
  -> AuthorizedContact validation
  -> Alert -> approved / scheduled
  -> ExecuteMaintenanceJob (lock + ventana + T-SQL + auditoría + notificación)
  -> ScanServerJob (verificación post-ejecución)
```

## Documentación

- [Plan de implementación](PLAN_IMPLEMENTACION_INDEXWATCH_V2.md)
- [Plan de trabajo (equipo 5)](PLAN_TRABAJO_INDEXWATCH_V2_EQUIPO_5.md)
- [Handoff integrantes](docs/HANDOFF_INTEGRANTES.md)
- [Runbook Integrante 2 (Scanner)](docs/INTEGRANTE_2_RUNBOOK.md)
- [Runbook Integrante 3 (Analytics)](docs/runbooks/integrante3.md)
- [Guía instalación local](INSTALACION_LOCAL.md)

## Licencia

MIT