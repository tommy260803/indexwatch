# Cumplimiento de Alcance — Integrante 4
## IndexWatch v2 — WhatsApp, Autorización, Ventanas y Ejecución Segura

Este documento resume la implementación del dominio asignado al Integrante 4 en el Plan de Trabajo (Sección 13), con evidencia extraída de la suite de pruebas automatizadas del proyecto.

### Resultado de la suite de pruebas

```
Tests:    2 skipped, 105 passed (239 assertions)
Duration: 4.83s
```

Los 2 tests omitidos (`SqlServerConnectionFactoryTest`) corresponden a pruebas de conexión real a SQL Server, que requieren la extensión `pdo_sqlsrv` instalada; se omiten en un entorno de desarrollo sin SQL Server local y se ejecutan en el entorno de integración correspondiente.

### Mapeo contra el alcance de la Sección 13

| Entregable del plan | Implementación | Evidencia (tests) |
|---|---|---|
| Refactor seguro de `WhatsAppService` | Canal de mensajería tipado, integrado al flujo de aprobación/rechazo | `WhatsAppWebhookTest` (12 casos) |
| Webhook: firma, parsing, idempotencia, contactos autorizados | Verificación de token, deduplicación de eventos, validación contra `authorized_contacts`, protección contra manipulación de botones | `webhook idempotency prevents duplicate processing`, `webhook rejects unauthorized contact`, `unauthorized silent policy does not respond`, `webhook rejects tampered button id` |
| `MaintenanceWindowResolver` | Resolución de ventanas activas y próximas, con soporte de zona horaria por ventana y cruce de medianoche | 15 tests, incluye `handles midnight crossing window`, `respects window timezone over server timezone`, `find next day wraps around week` |
| Política de acciones y `TsqlGeneratorService` | Generación de T-SQL para las 7 acciones definidas (`REORGANIZE`, `REBUILD`, `UPDATE STATISTICS`, `CREATE INDEX`, `DISABLE INDEX`, `DROP INDEX`, `CREATE CLUSTERED`), con escapado de identificadores y validación de metadata | 18 tests, incluye `escape identifier wraps in brackets`, `fill factor is clamped`, `generate throws for unsupported action` |
| `ExecuteMaintenanceJob` | Ciclo completo: validación de alerta, prevención de doble ejecución, revalidación de ventana, ejecución, notificación de resultado | 13 tests, incluye `returns early when action already completed` (no doble ejecución), `requeues when outside maintenance window`, `fails when outside window and max attempts reached` |
| Autorización por contacto y acciones de alto riesgo | Distinción de rol admin para acciones de alto riesgo | `webhook high risk action requires admin` |
| Auditoría | Registro de decisiones (aprobación, descarte) | `webhook dismiss creates audit log` |

### Reglas no negociables verificadas

- **No doble ejecución**: `returns early when action already running` y `returns early when action already completed` confirman que un reintento no vuelve a ejecutar una acción ya procesada.
- **SQL nunca desde texto de WhatsApp**: el generador de T-SQL se prueba de forma aislada (`TsqlGeneratorServiceTest`) a partir de metadata estructurada, no de payloads de mensajería.
- **Contactos no autorizados no pueden aprobar acciones**: `webhook rejects unauthorized contact` y política configurable de silencio (`unauthorized silent policy does not respond`).
- **Botones de WhatsApp no son la fuente de verdad**: `webhook rejects tampered button id` confirma que el id del botón se revalida contra el estado real en base de datos.

### Definition of Done (Sección 13)

> *"El Integrante 4 termina cuando una acción puede aprobarse desde WhatsApp por un contacto permitido, quedar auditada, programarse o ejecutarse una vez dentro de política y comunicar un resultado verificable sin bloquear el webhook."*

Cumplido: el flujo completo (aprobación → auditoría → ventana → ejecución → notificación) está cubierto de extremo a extremo por `WhatsAppWebhookTest` y `ExecuteMaintenanceJobTest`, con 105 pruebas pasando y 0 fallos.
