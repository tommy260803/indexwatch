# Plan de Implementación IndexWatch v2 — Documento Maestro

**Versión:** 1.0  
**Fecha:** 2025-01-19  
**Fuente de verdad:** `PLAN_TRABAJO_INDEXWATCH_V2_EQUIPO_5.md` + `docs/HANDOFF_INTEGRANTES.md` + auditoría de código real  
**Estado actual:** Sprint 2-3 (Integrante 1 y 2 entregados, Integrante 3 ~80%, Integrante 4 ~15%, Integrante 5 ~10%)

---

## 🎯 Decisiones Tomadas (Respuestas a Preguntas Abiertas)

| # | Pregunta | Decisión | Justificación |
|---|----------|----------|---------------|
| 1 | SQL Server staging | **Usar SQL Server local existente** (`ANTHONY\IndexWatch_Test`) | Ya configurado en `INTEGRANTE_2_RUNBOOK.md`, evita dependencia externa |
| 2 | WhatsApp Business API | **Configurar en `.env` con placeholders**; documentar pasos reales | Permite desarrollo sin credenciales reales; producción las inyecta CI/CD |
| 3 | PDF Engine | **`barryvdh/laravel-dompdf`** (v3.x compatible Laravel 13) | Nativo Laravel, sin Node.js, suficiente para reportes tabulares; Puppeteer añade complejidad |
| 4 | Roles | **Tres roles**: `admin` (todo), `operator` (ver + aprobar + cancelar), `viewer` (solo lectura) | Alineado con plan sección 14; `operator` no crea servidores |
| 5 | Timezone default | **`America/Lima`** (como en migración `servers`) | Consistente con datos existentes; configurable por servidor |
| 6 | CI/CD | **GitHub Actions** (repositorio ya en GitHub) | Nativo, gratuito, integración perfecta con PHP/Pint/PHPStan/Dusk |

---

## 📦 Estado del Código (Auditoría Resumida)

### ✅ Fortalezas (Listo para usar)
- Modelo PostgreSQL completo: `servers`, `sql_indexes`, `alerts`, `maintenance_windows`, `maintenance_actions`, `audit_logs`, `contacts`, `statistics_status`, `missing_indexes`, `index_snapshots`, `index_operational_snapshots`, `server_scan_runs`, `generated_reports`
- Scanner SQL Server robusto: `SqlServerInspectorService` (DMV: inventory, fragmentation, usage, stats, page splits, missing indexes)
- `ServerScanService` + `ScanServerJob` con aislamiento, correlación, logging, error sanitization
- `AlertDetectionService` pipeline completo: fragmentación, fill factor, page splits, stale stats, **missing indexes, unused, duplicate, heaps**
- Analyzers (Integrante 3): `MissingIndexesAnalyzer`, `UnusedIndexesAnalyzer`, `DuplicateIndexesAnalyzer`, `HeapsAnalyzer` con DTOs + Evidence
- `ReportExportService` genera HTML (imprimible a PDF) + CSV, `GenerateReportJob` asíncrono
- `HealthScoreService` versionado, configurable, con detalle de deducciones
- Comandos: `indexwatch:scan`, `indexwatch:verify`
- Tests: 46/49 pasan (fallos: auth redirect, pdo_sqlsrv missing, test frágil)

### ❌ Fallos Críticos (Fase 0 - Bloqueadores)
1. **DashboardController usa `App\Models\Index` inexistente** → 500/302
2. **Alert model tiene `resolved_at` en fillable/casts pero NO en migración**
3. **WhatsAppService usa `withoutVerifying()` (TLS desactivado)**
4. **Webhook NO verifica HMAC `X-Hub-Signature-256`**
5. **Webhook ejecuta DDL directo con `sleep(1)` simulado** (viola arquitectura)
6. **Rutas webhook duplicadas en `web.php` y `api.php`**
7. **Tests rotos**: `ExampleTest` (302 vs 200), `SqlServerInspectorServiceTest` (5 vs 6 queries)
8. **Falta `pdo_sqlsrv` / `sqlsrv` en PHP local**

---

## 🗓️ Fases de Implementación

---

### **FASE 0: ESTABILIZACIÓN INMEDIATA (1-2 días)**
> **Gate de salida:** `php artisan test` → 100% verde, `indexwatch:scan --sync` funciona, webhook seguro básico

| # | Tarea | Archivos | Comando de verificación |
|---|-------|----------|-------------------------|
| 0.1 | Fix DashboardController → `SqlIndex` + campos reales | `app/Http/Controllers/DashboardController.php` | `php artisan test --filter=Dashboard` |
| 0.2 | Quitar `resolved_at` de `Alert::$fillable`/`$casts` | `app/Models/Alert.php` | `php artisan migrate:fresh --seed` |
| 0.3 | Mover webhook SOLO a `api.php`, quitar de `web.php` | `routes/api.php`, `routes/web.php` | `php artisan route:list | grep whatsapp` |
| 0.4 | HMAC SHA-256 en webhook (`X-Hub-Signature-256`) | `app/Http/Controllers/WhatsAppWebhookController.php`, `config/services.php` | Test unitario HMAC |
| 0.5 | Quitar `withoutVerifying()` de `WhatsAppService` | `app/Services/WhatsAppService.php` | Verificar `Http::withToken()` sin `withoutVerifying()` |
| 0.6 | Arreglar `ExampleTest` (esperar 302 con auth) | `tests/Feature/ExampleTest.php` | `php artisan test tests/Feature/ExampleTest.php` |
| 0.7 | Arreglar `SqlServerInspectorServiceTest` (6 queries) | `tests/Unit/SqlServerInspectorServiceTest.php` | `php artisan test tests/Unit/SqlServerInspectorServiceTest.php` |
| 0.8 | Habilitar `pdo_sqlsrv` + `sqlsrv` en `php.ini` | `C:\php\php-8.5.8-Win32-vs17-x64\php.ini` | `php -m | grep sqlsrv` |
| 0.9 | `migrate:fresh --seed` + `test` → **TODO VERDE** | — | `php artisan test` → 49 passed |

---

### **FASE 1: INTEGRANTE 4 — WHATSAPP + MANTENIMIENTO (2-3 semanas)**
> **Objetivo:** Flujo completo seguro: Webhook → Autorización → Ventana → Job → Ejecución → Verificación → Notificación

#### Sprint 1.1: Webhook Seguro + Idempotencia (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1.1.1 | Verificar HMAC `X-Hub-Signature-256` con `WHATSAPP_APP_SECRET` | `WhatsAppWebhookController::handle()`, `config/services.php` |
| 1.1.2 | Idempotencia: guardar `message_id` de Meta en `whatsapp_webhook_events` (nueva tabla) | Migración + `WhatsAppWebhookController` |
| 1.1.3 | Parsing real payload `entry.0.changes.0.value.messages.0` (interactive + text) | `WhatsAppWebhookController` |
| 1.1.4 | Respuestas no-botón: ignorar o loggear, no fallar | `WhatsAppWebhookController` |
| 1.1.5 | Tests: HMAC válido/inválido, payload incompleto, entrega duplicada, orden incorrecto | `tests/Feature/WhatsApp/WebhookTest.php` |

#### Sprint 1.2: Catálogo de Acciones + Contactos Autorizados (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1.2.1 | Migración `authorized_contacts` (phone_e164, name, role, active, allowed_from, user_id nullable) | `database/migrations/xxxx_create_authorized_contacts_table.php` |
| 1.2.2 | Model `AuthorizedContact` + scopes + normalización E.164 | `app/Models/AuthorizedContact.php` |
| 1.2.3 | Mapping `AlertType → RecommendedAction[]` según riskLevel (plan sección 8) | `app/Services/WhatsApp/ActionCatalog.php` |
| 1.2.4 | `WhatsAppService::buildActionButtons(Alert $alert)` genera botones dinámicos según catálogo | `app/Services/WhatsAppService.php` |
| 1.2.5 | Validar `from` E.164 contra `AuthorizedContact::where('phone_e164', $from)->active()->first()` | `WhatsAppWebhookController` |
| 1.2.6 | Tests: contacto no autorizado, alerta cerrada, acción no permitida para tipo | `tests/Feature/WhatsApp/ActionCatalogTest.php` |

#### Sprint 1.3: MaintenanceWindowResolver + Programación (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1.3.1 | `MaintenanceWindowResolver::resolveNextWindow(Server $server, Carbon $from)` | `app/Services/Maintenance/MaintenanceWindowResolver.php` |
| 1.3.2 | Soporta: días semana, hora inicio/fin, timezone IANA, cruce medianoche, `active` flag | `MaintenanceWindow` model ya existe |
| 1.3.3 | Si hay ventana activa ahora → `scheduled_for = now()`, si no → próxima ventana válida | `MaintenanceWindowResolver` |
| 1.3.4 | Actualizar `Alert::scheduled_for` + status `scheduled` al aprobar | `WhatsAppWebhookController` + `Alert` model |
| 1.3.5 | Tests: ventana activa, futura, inexistente, cruce medianoche, zona horaria | `tests/Unit/MaintenanceWindowResolverTest.php` |

#### Sprint 2.1: TsqlGeneratorService (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 2.1.1 | Genera SQL desde metadata validada (NUNCA de input usuario/WhatsApp) | `app/Services/Maintenance/TsqlGeneratorService.php` |
| 2.1.2 | Escape identifiers: `[schema].[table]`, `[index]` — usar `SqlServerIdentifier::escape()` | `app/Support/SqlServerIdentifier.php` |
| 2.1.3 | Allow-list options por acción: `REBUILD (ONLINE=ON, FILLFACTOR=?, MAXDOP=1)`, `REORGANIZE (LOB_COMPACTION=ON)`, `UPDATE STATISTICS (FULLSCAN)`, etc. | `TsqlGeneratorService` |
| 2.1.4 | `CREATE INDEX` incluye columnas equality/inequality/included + `WHERE` filter si aplica | `TsqlGeneratorService` |
| 2.1.5 | `DROP INDEX` / `DISABLE INDEX` / `CREATE CLUSTERED` requieren `riskLevel` check | `RecommendedAction::requiresDoubleConfirmation()` |
| 2.1.6 | Tests: SQL generado correcto, escape identifiers, options allow-list, risk level | `tests/Unit/TsqlGeneratorServiceTest.php` |

#### Sprint 2.2: ExecuteMaintenanceJob (4-5 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 2.2.1 | Job `ExecuteMaintenanceJob` implements `ShouldQueue`, `SerializesModels` | `app/Jobs/ExecuteMaintenanceJob.php` |
| 2.2.2 | Lock Redis/DB: `Lock::acquire("maintenance:{server_id}:{action_id}", 300)` | `ExecuteMaintenanceJob::handle()` |
| 2.2.3 | Pre-checks: alerta `approved`/`scheduled`, servidor `active`, contacto autorizado, ventana válida AHORA, capacidad SQL Server (ONLINE=ON, resumable) | `ExecuteMaintenanceJob` |
| 2.2.4 | Ejecuta T-SQL con `DB::connection($serverConnection)->statement($sql)` + timeout configurable | `ExecuteMaintenanceJob` |
| 2.2.5 | Captura: rowcount, duration_ms, error sanitizado, output messages | `ExecuteMaintenanceJob` |
| 2.2.6 | Auditoría PRE (intent), POST (resultado) en `audit_logs` + `maintenance_actions` | `ExecuteMaintenanceJob` |
| 2.2.7 | Actualiza `Alert` status: `running` → `succeeded`/`failed`, `executed_at`, `metadata.result` | `ExecuteMaintenanceJob` |
| 2.2.8 | Dispatch `ScanServerJob` prioritario (after_commit) para verificación post-ejecución | `ExecuteMaintenanceJob` |
| 2.2.9 | Notificación WhatsApp resultado (éxito/fallo/programado) vía `SendAlertNotificationJob` | `ExecuteMaintenanceJob` + `SendAlertNotificationJob` |
| 2.2.10 | Tests: lock concurrente, fallo SQL pre/durante/post, retry job ya exitoso, confirmación alto riesgo, cancelación pre-ventana | `tests/Feature/Maintenance/ExecuteMaintenanceJobTest.php` |

#### Sprint 2.3: Integración Alertas Avanzadas (2-3 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 2.3.1 | Missing Index: botón `REVIEW` + `CREATE INDEX` (double confirm si high_risk) | `ActionCatalog`, `WhatsAppService` |
| 2.3.2 | Unused Index: botón `REVIEW` + `DISABLE INDEX` / `DROP INDEX` (high/very_high risk) | `ActionCatalog` |
| 2.3.3 | Duplicate Index: botón `REVIEW` + `DROP INDEX` (explicar cuál cubre a cuál) | `ActionCatalog` |
| 2.3.4 | Heap: botón `REVIEW` + `CREATE CLUSTERED` (very_high_risk) | `ActionCatalog` |
| 2.3.5 | Tests de integración end-to-end con alertas de cada tipo | `tests/Feature/WhatsApp/AdvancedAlertsTest.php` |

#### Sprint 2.4: Runbook + Documentación (1-2 días)
| Tarea | Archivo |
|-------|---------|
| Runbook operativo Integrante 4 | `docs/runbooks/integrante4.md` |
| Diagrama secuencia WhatsApp → Job | `docs/architecture/whatsapp-flow.mermaid` |

---

### **FASE 2: INTEGRANTE 5 — DASHBOARD + API + AUTH (2-3 semanas)**
> **Objetivo:** UI real, segura, datos vivos, roles, polling único, sin XSS

#### Sprint 1: Auth + Roles + Policies (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1 | Migración `role` en `users` (enum: admin, operator, viewer) + seed | `database/migrations/xxxx_add_role_to_users.php` |
| 2 | `AuthServiceProvider` registra Gates/Policies | `app/Providers/AuthServiceProvider.php` |
| 3 | `ServerPolicy`, `AlertPolicy`, `MaintenanceActionPolicy`, `GeneratedReportPolicy` | `app/Policies/*.php` |
| 4 | Sanctum API tokens para integración externa (opcional) | `config/sanctum.php` |
| 5 | Middleware `role:admin,operator` para rutas API | `app/Http/Middleware/RequireRole.php` |

#### Sprint 2: API Contracts v1 (4-5 días)
| Endpoint | Método | Controlador | Policy |
|----------|--------|-------------|--------|
| `/api/dashboard/data` | GET | `DashboardController@data` | `viewer` |
| `/api/servers` | GET/POST | `ServerController` | `viewer`/`admin` |
| `/api/servers/{id}/test-connection` | POST | `ServerController@testConnection` | `admin` |
| `/api/servers/{id}/thresholds` | PATCH | `ServerController@updateThresholds` | `admin` |
| `/api/maintenance-windows` | GET/POST/PATCH | `MaintenanceWindowController` | `admin` |
| `/api/maintenance-actions` | GET | `MaintenanceActionController@index` | `operator` |
| `/api/maintenance-actions/{id}/cancel` | POST | `MaintenanceActionController@cancel` | `operator` |
| `/api/audit-logs` | GET | `AuditLogController@index` | `operator` |
| `/api/reports` | POST | `ReportController@store` | `operator` |
| `/api/reports/{id}` | GET | `ReportController@show` | `operator` |

**Contratos JSON** versionados en `app/Http/Resources/` (ServerResource, AlertResource, MaintenanceActionResource, etc.)

#### Sprint 3: JS Refactor + Polling Único (3-4 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1 | Un solo `fetchDashboardData()` con `AbortController` + `debounce` | `public/js/dashboard.js` |
| 2 | Estados: `loading` (skeleton), `empty` (ilustración), `error` (toast + retry) | `dashboard.blade.php` + `dashboard.js` |
| 3 | Render **seguro**: `textContent` / `createElement` — **NUNCA** `innerHTML` con datos API | `dashboard.js` |
| 4 | Paginación server-side: `?page=1&per_page=25&sort=frag&dir=desc&filter=critical` | `DashboardController@data` + `dashboard.js` |
| 5 | Filtros reactivos: servidor, tipo alerta, severidad, rango fecha | `dashboard.js` + API params |

#### Sprint 4: UI Operaciones + Configuración (4-5 días)
| Vista | Descripción | Archivos Blade |
|-------|-------------|----------------|
| `/servers` | Tabla servidores: nombre, host, health score, último scan, estado conexión, acciones (editar, test, umbrales, ventanas) | `servers/index.blade.php`, `servers/create.blade.php`, `servers/edit.blade.php` |
| `/servers/{id}/thresholds` | Formulario umbrales (warning/critical/stats_stale/min_pages) + validación backend | `servers/thresholds.blade.php` |
| `/servers/{id}/windows` | CRUD maintenance_windows (día, inicio, fin, zona, activo) | `servers/windows.blade.php` |
| `/actions` | Centro operaciones: maintenance_actions con filtros (servidor, estado, tipo, rango), botón cancelar (si pending/scheduled), ver SQL preview, ver auditoría | `actions/index.blade.php` |
| `/audit` | Auditoría paginada + filtros (actor, acción, servidor, alerta, rango) | `audit/index.blade.php` |
| `/reports` | Formulario filtros → encola `GenerateReportJob` → tabla generated_reports (estado, descarga, expira) | `reports/index.blade.php` |

#### Sprint 5: Reportes UI + Descarga (2-3 días)
| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 1 | `ReportController@store` valida filtros → crea `GeneratedReport` `status=pending` → dispatch `GenerateReportJob` | `app/Http/Controllers/ReportController.php` |
| 2 | `ReportController@show` verifica autorización → `ReportExportService::download()` | `ReportController` |
| 3 | Tabla `generated_reports` en `/reports` con polling de estado (pending→generating→completed/failed) | `reports/index.blade.php` + `reports.js` |
| 4 | Botón descarga solo si `completed` y no expirado | `reports/index.blade.php` |

#### Sprint 6: CI/CD + Calidad + Accesibilidad (3-4 días)
| Tarea | Archivo |
|-------|---------|
| GitHub Actions: `phpstan` (level 5), `pint`, `phpunit`, `dusk` smoke test | `.github/workflows/ci.yml` |
| `phpstan.neon` con baseline | `phpstan.neon` |
| `pint.json` (Laravel preset) | `pint.json` |
| Dusk test: login → dashboard → server create → scan → alert → approve → report download | `tests/Browser/DashboardTest.php` |
| Accesibilidad: labels en forms, contraste WCAG AA, keyboard nav, aria-live en alerts | Blade templates |
| Checklist despliegue | `docs/DEPLOYMENT_CHECKLIST.md` |

---

### **FASE 3: INTEGRANTE 3 — REPORTES PDF/EXCEL + PULIDO (1-2 semanas)**

| Tarea | Detalle | Archivos |
|-------|---------|----------|
| 3.1 | Instalar `barryvdh/laravel-dompdf` ^3.0 (compat Laravel 13) | `composer.json` |
| 3.2 | Vista `reports/pdf.blade.php`: header/footer, page-break-inside: avoid, tabla paginada, fuentes DejaVu | `resources/views/reports/pdf.blade.php` |
| 3.3 | `ReportExportService::generatePdf()` usa `PDF::loadView()` | `app/Services/Reports/ReportExportService.php` |
| 3.4 | Instalar `maatwebsite/excel` ^3.1 | `composer.json` |
| 3.5 | `IndexWatchExcelExport` con 6 sheets: Resumen, Fragmentación, Estadísticas, Alertas, Mantenimiento, Auditoría | `app/Services/Reports/Exports/IndexWatchExcelExport.php` |
| 3.6 | `ReportDataService::buildReportData()` dataset unificado con todos los joins | `app/Services/Reports/ReportDataService.php` |
| 3.7 | Validación DBA: falsos positivos missing index, umbrales unused, clasificación duplicados, heaps | `docs/validation/dba-session-notes.md` |
| 3.8 | Runbook Integrante 3 | `docs/runbooks/integrante3.md` |

---

### **FASE 4: ENDURECIMIENTO + UAT + PILOTO (1-2 semanas)**

| Área | Acciones |
|------|----------|
| **Migraciones prod** | Backup Neon → `migrate --pretend` → migración escalonada → verificación integridad → rollback plan documentado |
| **Carga scans** | Workers concurrentes, medir impacto en SQL Server test, ajustar `statement_timeout`, `minimum_index_pages` |
| **Seguridad** | Rate limit webhook (100/min), API (60/min), CSP headers, HSTS, secret scanning (truffleHog) en CI |
| **Observabilidad** | `/up` health check, métricas Prometheus (scan_duration, alert_count, job_queue_depth), alerting si scan error > 5min |
| **UAT completo** | Checklist: scan real → alerta → WhatsApp approve → ventana → job ejecuta → scan verifica → reporte descarga |
| **Documentación final** | `README.md` actualizado, `INSTALACION_LOCAL.md` verificado, runbooks consolidados, diagrama arquitectura Mermaid |

---

## 📁 Estructura de Archivos a Crear/Modificar (Resumen)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php          ← FIX Fase 0
│   │   ├── WhatsAppWebhookController.php    ← FIX Fase 0 + Fase 1
│   │   ├── ServerController.php             ← Fase 2
│   │   ├── MaintenanceWindowController.php  ← Fase 2
│   │   ├── MaintenanceActionController.php  ← Fase 2
│   │   ├── AuditLogController.php           ← Fase 2
│   │   └── ReportController.php             ← Fase 2
│   ├── Resources/
│   │   ├── ServerResource.php
│   │   ├── AlertResource.php
│   │   ├── MaintenanceActionResource.php
│   │   └── GeneratedReportResource.php
│   └── Middleware/
│       └── RequireRole.php
├── Services/
│   ├── WhatsApp/
│   │   ├── WhatsAppService.php              ← FIX Fase 0 + Fase 1
│   │   └── ActionCatalog.php                ← Fase 1
│   ├── Maintenance/
│   │   ├── MaintenanceWindowResolver.php    ← Fase 1
│   │   ├── TsqlGeneratorService.php         ← Fase 1
│   │   └── MaintenanceActionService.php     ← Fase 1
│   └── Reports/
│       ├── ReportDataService.php            ← Fase 3
│       └── ReportExportService.php          ← FIX + Fase 3
├── Jobs/
│   ├── ScanServerJob.php                    ← Ya existe
│   ├── ExecuteMaintenanceJob.php            ← Fase 1
│   ├── GenerateReportJob.php                ← Ya existe
│   └── SendAlertNotificationJob.php         ← Fase 1
├── Models/
│   ├── AuthorizedContact.php                ← Fase 1 (nuevo)
│   └── Alert.php                            ← FIX Fase 0
├── Support/
│   └── SqlServerIdentifier.php              ← Fase 1
├── Policies/
│   ├── ServerPolicy.php
│   ├── AlertPolicy.php
│   ├── MaintenanceActionPolicy.php
│   └── GeneratedReportPolicy.php
└── Providers/
    └── AuthServiceProvider.php              ← Fase 2

database/
├── migrations/
│   ├── xxxx_create_authorized_contacts_table.php
│   ├── xxxx_add_role_to_users.php
│   └── xxxx_add_resolved_at_to_alerts.php   ← Si se decide mantener
└── seeders/
    └── RoleSeeder.php

routes/
├── api.php                                  ← FIX Fase 0 + Fase 2
└── web.php                                  ← FIX Fase 0

resources/
├── views/
│   ├── dashboard.blade.php                  ← FIX + Fase 2
│   ├── servers/
│   ├── actions/
│   ├── audit/
│   ├── reports/
│   │   ├── index.blade.php
│   │   ├── pdf.blade.php                    ← Fase 3
│   │   └── html.blade.php                   ← Ya existe
│   └── components/
└── js/
    └── dashboard.js                         ← FIX + Fase 2

tests/
├── Unit/
│   ├── MaintenanceWindowResolverTest.php
│   ├── TsqlGeneratorServiceTest.php
│   └── SqlServerInspectorServiceTest.php    ← FIX Fase 0
├── Feature/
│   ├── WhatsApp/
│   │   ├── WebhookTest.php
│   │   ├── ActionCatalogTest.php
│   │   └── AdvancedAlertsTest.php
│   ├── Maintenance/
│   │   └── ExecuteMaintenanceJobTest.php
│   └── DashboardTest.php
└── Browser/
    └── DashboardTest.php                    ← Fase 2 Dusk

docs/
├── runbooks/
│   ├── integrante3.md
│   └── integrante4.md
├── architecture/
│   └── whatsapp-flow.mermaid
├── validation/
│   └── dba-session-notes.md
└── DEPLOYMENT_CHECKLIST.md

.github/
└── workflows/
    └── ci.yml
```

---

## ✅ Criterios de Aceptación Globales (Definition of Done)

El proyecto se considera **completo y listo para producción** cuando:

1. ✅ `php artisan test` → **49/49 passed** (unit + feature)
2. ✅ `php artisan dusk` → smoke test pasa (login → dashboard → server → scan → alert → approve → report)
3. ✅ `php artisan indexwatch:scan --sync --server=1` → escanea SQL Server real, guarda métricas, genera alertas
4. ✅ Webhook WhatsApp: HMAC válido → idempotente → contacto autorizado → alerta `approved` → `scheduled_for` en ventana correcta
5. ✅ `ExecuteMaintenanceJob` ejecuta `REORGANIZE`/`UPDATE STATISTICS` real en SQL Server, captura resultado, auditoría completa, scan posterior confirma mejora
6. ✅ Dashboard: KPIs reales, health score, alertas, índices paginados, sin XSS, polling único (30s)
7. ✅ API `/api/*`: contratos JSON estables, protegida por roles, OpenAPI generable
8. ✅ Reportes: HTML/CSV/PDF descargables con datos reales, sin secretos, expiran 7 días
9. ✅ CI/CD: phpstan level 5, pint, phpunit, dusk — todo verde en PR
10. ✅ Runbooks: `integrante3.md`, `integrante4.md`, `DEPLOYMENT_CHECKLIST.md` completos

---

## 🚀 Próximo Paso Inmediato

**Iniciar FASE 0 ahora mismo** — corregir los 9 bloqueadores críticos para tener base estable.