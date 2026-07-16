# Plan de Trabajo Detallado: IndexWatch v2 para 5 Integrantes

**Estado:** Propuesto para ejecucion

**Alcance:** Conversion del prototipo actual de IndexWatch en un sistema de monitoreo y mantenimiento real para multiples instancias o bases de datos SQL Server.

**Fuente tecnica revisada:** `current_system_context.md` y el codigo actual del repositorio.

**Supuesto de planificacion:** cinco integrantes con disponibilidad similar. Los sprints se expresan como ciclos de dos semanas, excepto la preparacion inicial. La duracion real debe ajustarse a las horas semanales disponibles del equipo, no reduciendo pruebas ni controles de seguridad.

## 1. Objetivo del proyecto

IndexWatch v2 debe conectarse de forma segura a multiples SQL Server reales, recolectar metricas de indices, detectar problemas de rendimiento, generar alertas por WhatsApp, requerir autorizacion humana, ejecutar mantenimiento dentro de ventanas permitidas, conservar auditoria completa y mostrar resultados reales en el dashboard.

El producto no debe ejecutar cambios de base de datos desde el navegador ni directamente dentro del webhook. Todo mantenimiento real debe seguir este flujo:

```text
Descubrir -> Persistir evidencia -> Crear alerta -> Notificar -> Autorizar -> Auditar
-> Programar o ejecutar Job -> Verificar resultado -> Notificar -> Mostrar en dashboard
```

## 2. Estado inicial y decisiones tecnicas

| Tema | Estado actual | Decision para v2 |
|---|---|---|
| Framework | El documento de contexto dice Laravel 11, pero `composer.json` declara Laravel `^13.8` y PHP `^8.3`. | Tomar `composer.json` como fuente de verdad. Confirmar version instalada durante Sprint 0. |
| Base interna | PostgreSQL alojado en Neon. | Mantener PostgreSQL como plano de control, historico, alertas, auditoria y configuracion. |
| Bases monitoreadas | Datos semilla; existe configuracion `sqlsrv`. | Usar conexiones SQL Server dinamicas por cada registro de `servers`. |
| Dashboard | Blade, CSS y JavaScript vanilla; varias secciones siguen siendo maquetado estatico. | Conservar lenguaje visual, reemplazar datos estaticos por endpoints autenticados y datos reales. |
| WhatsApp | Servicio y webhook funcionales para demo. | Mantener el canal, corregir seguridad, parsing real de Meta, idempotencia y flujo asincrono. |
| Queue | Existen migraciones de jobs, pero no hay ejecucion real. | Usar Jobs de Laravel. Redis es recomendado para produccion; queue basada en base de datos es alternativa inicial. |
| Credenciales SQL | `servers.password` existe como texto normal. | Cifrar valores existentes, usar cast `encrypted`, ocultarlos en serializacion y no escribirlos en logs. |
| Ejecucion de acciones | El webhook genera texto, espera con `sleep(1)` y marca resuelto. | El webhook solo autoriza y agenda. Un Job controlado ejecuta y verifica la accion real. |

## 3. Decisiones funcionales base

Estas decisiones convierten las ideas iniciales en un comportamiento seguro de produccion. Deben aprobarse por el equipo antes de Sprint 1.

| Decision | Propuesta base | Motivo |
|---|---|---|
| Reportes | Descarga bajo demanda en v2. El envio semanal o mensual queda preparado pero desactivado hasta validar destinatarios y retencion. | Evita correos no deseados y permite validar datos antes de automatizar. |
| Identidad en auditoria | Guardar el numero de WhatsApp como `authorized_by` y asociarlo a un contacto autorizado. Si existe usuario Laravel vinculado, guardar tambien `user_id`. | Permite trazabilidad inmediata y deja evolucionar a cuentas internas. |
| Ejecucion automatica | Ninguna accion riesgosa se ejecuta sin aprobacion previa. Una aprobacion se programa para la siguiente ventana valida si no hay ventana activa. | Evita mantenimiento inesperado en horario productivo. |
| Acciones destructivas | `DROP INDEX`, `DISABLE INDEX`, `CREATE INDEX` y `CREATE CLUSTERED` requieren modo de alto riesgo, vista previa y una segunda confirmacion o politica administrativa explicita. | Estas acciones pueden afectar disponibilidad, planes, escrituras y almacenamiento. |
| Fragmentacion | Escanear primero con modo de DMV de bajo impacto y filtros por numero minimo de paginas. | Evita que el monitoreo se convierta en una carga sobre SQL Server. |
| Detecciones DMV | Las recomendaciones de missing indexes, uso y page splits se tratan como evidencia, no como prueba absoluta. | Las DMV pueden reiniciarse con SQL Server y no conocen por si solas el contexto del negocio. |

## 4. Alcance y trazabilidad de especificaciones

La siguiente tabla cubre las doce especificaciones recibidas y asigna un responsable tecnico. La letra A significa responsable final; R significa quien implementa; C significa colaborador necesario.

| ID | Especificacion | Implementacion final | A | R | C | Criterio de aceptacion |
|---|---|---|---|---|---|---|
| F01 | Ventanas de mantenimiento | `maintenance_windows`, resolvedor de proxima ventana y `ExecuteMaintenanceJob`. | Integrante 4 | Integrante 4 | Integrantes 1 y 5 | Una aprobacion fuera de ventana queda programada con hora y zona correctas; el Job vuelve a validar la ventana antes de ejecutar. |
| F02 | Historial de auditoria | `audit_logs` y `maintenance_actions` con actor, origen, SQL, resultado y timestamps. | Integrante 1 | Integrantes 1 y 4 | Integrante 5 | Cada autorizacion, intento, exito, fallo y descarte queda consultable e inmutable. |
| F03 | Reportes PDF y Excel | Dataset de reportes, exportaciones asincronas y pantalla de descarga. | Integrante 3 | Integrantes 3 y 5 | Integrante 1 | Se puede descargar un reporte filtrado por servidor y rango con datos reales de snapshots y auditoria. |
| F04 | Health score por servidor | Algoritmo versionado de 0 a 100 y snapshots por servidor. | Integrante 2 | Integrante 2 | Integrantes 1 y 5 | El score es reproducible desde las metricas almacenadas y visible en el dashboard. |
| F05 | Umbrales personalizados | Campos por servidor, validacion y pantalla de configuracion. | Integrante 1 | Integrantes 1 y 5 | Integrante 2 | El siguiente escaneo usa los umbrales del servidor, no valores fijos. |
| F06 | Fill factor optimo | Reglas basadas en lecturas y escrituras, almacenadas por indice e incluidas en T-SQL permitido. | Integrante 2 | Integrantes 2 y 4 | Integrante 5 | Un `REBUILD` aprobado usa el fill factor recomendado y deja evidencia de la regla aplicada. |
| F07 | Missing indexes | Consultas DMV, persistencia de candidatos, score de confianza y alerta de revision/creacion. | Integrante 3 | Integrantes 3 y 4 | Integrante 2 | Los candidatos tienen columnas, impacto, evidencia y no se crean automaticamente. |
| F08 | Estadisticas desactualizadas | `statistics_status`, umbral de modificaciones y accion `UPDATE STATISTICS`. | Integrante 2 | Integrantes 2 y 4 | Integrante 1 | La alerta aparece cuando el ratio supera el umbral y la accion se ejecuta solo tras aprobacion. |
| F09 | Indices no usados o subutilizados | Analizador de lecturas/escrituras con ventanas de observacion y alerta segura. | Integrante 3 | Integrantes 3 y 4 | Integrante 2 | El sistema no recomienda eliminar un indice antes de cumplir periodo minimo y excluir claves importantes. |
| F10 | Indices duplicados o redundantes | Comparacion normalizada de claves, inclusiones, filtros y restricciones. | Integrante 3 | Integrante 3 | Integrante 2 | Cada recomendacion explica el indice cubriente y excluye PK, unique y casos ambiguos. |
| F11 | Tablas Heap | Detector de heaps con actividad, tamano y posibles forwarded records. | Integrante 3 | Integrantes 3 y 4 | Integrante 2 | La alerta identifica la tabla, evidencia y una propuesta de indice, sin crearla automaticamente. |
| F12 | Page splits excesivos | Muestreo de contadores operativos por indice y tasa entre scans. | Integrante 2 | Integrante 2 | Integrante 3 | La alerta se basa en deltas temporales por indice, no solo en un contador global de instancia. |

## 5. Arquitectura objetivo

### 5.1 Componentes

| Capa | Responsabilidad | Componentes sugeridos |
|---|---|---|
| Presentacion | Dashboard, operaciones, configuracion, auditoria y reportes. | Blade, `public/js/dashboard.js`, controladores y requests autenticados. |
| Aplicacion | Casos de uso, reglas, validacion, coordinacion de Jobs. | Servicios de monitoreo, analisis, acciones, reportes y politicas. |
| Conectividad SQL Server | Crear conexiones dinamicas, comprobar capacidad y ejecutar consultas autorizadas. | `SqlServerConnectionFactory`, `SqlServerInspectorService`. |
| Persistencia | Configuracion, inventario, snapshots, alertas, acciones y auditoria. | Modelos Eloquent, migraciones, indices PostgreSQL, transacciones. |
| Automatizacion | Escaneos programados, alertas, mantenimiento y reportes. | Commands, scheduler, queue workers, Jobs unicos. |
| Integraciones | WhatsApp Cloud API, correo futuro, almacenamiento de exportaciones. | `WhatsAppService`, cliente HTTP con TLS, notificaciones y storage. |
| Seguridad y observabilidad | Autorizacion, secretos, logs, alertas de fallos y metricas operativas. | Middleware, policies, logging estructurado, health checks y runbooks. |

### 5.2 Estructura sugerida de codigo

```text
app/
  Console/Commands/
    ScanIndexWatchServers.php
    VerifyIndexWatchPrerequisites.php
  Domain/
    Alerts/
    Maintenance/
    Monitoring/
    Reports/
  Http/
    Controllers/
    Requests/
    Resources/
  Jobs/
    ScanServerJob.php
    ExecuteMaintenanceJob.php
    GenerateReportJob.php
    SendAlertNotificationJob.php
  Models/
    Server.php
    Index.php
    IndexSnapshot.php
    Alert.php
    MaintenanceAction.php
    MaintenanceWindow.php
    AuditLog.php
    MissingIndex.php
    StatisticsStatus.php
    AuthorizedContact.php
  Services/
    SqlServer/
      SqlServerConnectionFactory.php
      SqlServerInspectorService.php
      SqlServerCapabilityService.php
    Monitoring/
      HealthScoreService.php
      AlertDetectionService.php
    Maintenance/
      MaintenanceWindowResolver.php
      TsqlGeneratorService.php
      MaintenanceActionService.php
    Reports/
      ReportDataService.php
      ReportExportService.php
    WhatsAppService.php
database/
  migrations/
  factories/
  seeders/
tests/
  Feature/
  Unit/
  Integration/
```

Los nombres son una guia. El equipo debe mantener una sola convencion, no duplicar servicios y no crear un segundo motor de reglas en controladores o JavaScript.

### 5.3 Flujo de monitoreo

```text
Scheduler
  -> indexwatch:scan
  -> ScanServerJob por servidor activo
  -> SqlServerConnectionFactory valida la conexion
  -> SqlServerInspectorService recolecta inventario y metricas
  -> Servicios de analisis generan hallazgos
  -> Persistencia actualiza indices y snapshots en PostgreSQL
  -> AlertDetectionService deduplica y crea alertas
  -> SendAlertNotificationJob envia WhatsApp cuando corresponde
  -> Dashboard consulta solo PostgreSQL, nunca SQL Server en cada carga
```

### 5.4 Flujo de aprobacion y mantenimiento

```text
Usuario autorizado pulsa boton de WhatsApp
  -> Webhook verifica firma de Meta y deduplica evento
  -> valida accion permitida para alerta y contacto autorizado
  -> MaintenanceActionService crea o actualiza maintenance_action
  -> AuditLog registra autorizacion
  -> MaintenanceWindowResolver decide ahora o proxima ventana
  -> ExecuteMaintenanceJob adquiere bloqueo por servidor y accion
  -> verifica estado, ventana, capacidad SQL Server y permisos
  -> TsqlGeneratorService produce SQL a partir de metadata confiable
  -> ejecuta, captura resultado y guarda evidencia
  -> actualiza alerta, accion y auditoria
  -> SendAlertNotificationJob comunica exito, fallo o programacion
  -> siguiente scan confirma la nueva metrica
```

## 6. Modelo de datos y migraciones

### 6.1 Reglas de migracion

- No modificar migraciones historicas ya aplicadas.
- Crear migraciones nuevas, reversibles y probadas en PostgreSQL.
- Hacer backup de Neon antes de aplicar cambios de produccion.
- Aplicar cambios aditivos primero; migrar datos; activar restricciones despues.
- Ejecutar una tarea unica para cifrar las contrasenas existentes antes de eliminar cualquier acceso al formato previo.
- Probar todas las migraciones desde una base limpia y desde una base que contiene datos del prototipo.

### 6.2 Cambios a `servers`

| Campo o regla | Uso |
|---|---|
| `port` | Puerto SQL Server, por defecto 1433 si no se especifica. |
| `password` cifrado | Usar cast de Laravel `encrypted`; mantenerlo oculto en JSON. |
| `connection_options` JSONB | Cifrado, confianza de certificado, timeout y otras opciones no secretas validadas. |
| `health_score` | Score actual de 0 a 100. |
| `health_score_updated_at` | Fecha de calculo del score. |
| `frag_warning_threshold` | Umbral personalizado para recomendacion. |
| `frag_critical_threshold` | Umbral personalizado para criticidad. |
| `stats_stale_threshold` | Porcentaje de filas modificadas que dispara revision de estadisticas. |
| `minimum_index_pages` | Evita alertar por indices demasiado pequenos. |
| `timezone` | Zona IANA para programacion de ventanas. |
| `last_scan_at` y `last_scan_status` | Estado operativo visible en dashboard. |
| `last_scan_error` | Error sanitizado, nunca credenciales ni SQL sensible. |

Las validaciones deben impedir umbrales incoherentes. El warning debe ser menor que critical, ambos deben estar entre 0 y 100, y la zona horaria debe ser valida.

### 6.3 Cambios a `indexes`

| Campo o regla | Uso |
|---|---|
| `schema_name`, `object_id`, `index_id` | Identificador estable de un indice SQL Server. No depender solo del nombre. |
| `index_type`, `is_primary_key`, `is_unique`, `is_disabled` | Protecciones para recomendaciones y acciones. |
| `page_count`, `size_mb`, `fragmentation_percent` | Estado fisico actual. |
| `fill_factor`, `optimal_fill_factor`, `fill_factor_reason` | Estado configurado y recomendacion explicable. |
| `user_seeks`, `user_scans`, `user_lookups`, `user_updates` | Uso acumulado observado. |
| `last_user_seek_at`, `last_user_scan_at`, `last_user_lookup_at` | Evidencia temporal de lectura. |
| `usage_stats_since` | Inicio de los contadores para detectar reinicios de SQL Server. |
| `last_checked_at` | Fecha del ultimo inventario valido. |

Crear una restriccion unica basada en `server_id`, `object_id` e `index_id`. Cuando SQL Server no devuelva identificadores por alguna consulta, el adaptador debe reportar error de datos y no crear duplicados silenciosos.

### 6.4 Tablas nuevas

| Tabla | Datos principales | Finalidad |
|---|---|---|
| `index_snapshots` | indice, fragmentacion, paginas, tamano, lecturas, escrituras, fill factor, tomado_en | Tendencias y reportes historicos. |
| `statistics_status` | servidor, objeto, stats_id, nombre, filas, modificaciones, ratio, ultimo_scan | Detectar estadisticas envejecidas. |
| `missing_indexes` | servidor, objeto, columnas equality/inequality/include, impacto DMV, estado, fingerprint | Persistir sugerencias y evitar alertas repetidas. |
| `maintenance_windows` | servidor, dia de semana, hora inicio, hora fin, zona, activo | Definir periodos validos de mantenimiento. |
| `maintenance_actions` | alerta, servidor, tipo, estado, SQL preview, programado_para, iniciado_en, terminado_en, resultado | Separar el ciclo de ejecucion del ciclo de alerta. |
| `audit_logs` | accion, actor, fuente, alerta, maintenance_action, servidor, estado, metadata, timestamp | Evidencia inmutable de decisiones y ejecuciones. |
| `authorized_contacts` | telefono normalizado, usuario opcional, rol, activo, permitido_desde | Limitar autorizaciones WhatsApp a personas conocidas. |
| `generated_reports` | solicitante, filtros, formato, estado, ruta, expira_en | Controlar exportaciones asincronas y descargas. |

### 6.5 Evolucion de `alerts`

La tabla actual depende obligatoriamente de `index_id` y su enum solo permite `fragmentation` e `inactive`. v2 debe permitir alertas sobre estadisticas, heaps, missing indexes y otros recursos.

| Cambio | Regla |
|---|---|
| `server_id` | Obligatorio en nuevas alertas. |
| `index_id` | Convertir en nullable para hallazgos sin indice existente. |
| `type` | Migrar de enum limitado a string validado por enum de PHP o lista de dominio. |
| `severity` | Permitir al menos `info`, `warning`, `critical`. |
| `status` | Usar estado claro: `pending`, `approved`, `scheduled`, `running`, `succeeded`, `failed`, `dismissed`, `expired`. |
| `subject_type`, `subject_id`, `metadata` | Identificar recurso afectado y guardar evidencia especifica. |
| `recommended_action` | Accion propuesta por el motor. |
| `fingerprint` | Huella estable para deduplicar alertas abiertas. |
| `approved_by`, `approved_at`, `scheduled_for`, `executed_at` | Trazabilidad del flujo. |

Crear un indice parcial para impedir dos alertas abiertas con el mismo `server_id` y `fingerprint`. Las alertas cerradas deben conservarse para auditoria e historico.

## 7. Reglas tecnicas de deteccion

### 7.1 Fragmentacion y mantenimiento de indices

| Regla | Implementacion |
|---|---|
| Fuente | `sys.dm_db_index_physical_stats` con modo y alcance configurables. |
| Filtro inicial | Omitir indices con `page_count` menor que `servers.minimum_index_pages`. |
| Healthy | Fragmentacion menor que warning. |
| Warning | Fragmentacion mayor o igual que warning y menor que critical. |
| Critical | Fragmentacion mayor o igual que critical. |
| Recomendacion | `REORGANIZE` para nivel moderado; `REBUILD` para criticidad, sujeto a capacidad y politica. |
| Verificacion | Un scan posterior, no solo el resultado SQL, confirma el cambio de fragmentacion. |

No asumir que `ONLINE = ON` esta disponible. El sistema debe detectar edicion, version y capacidad de SQL Server antes de incluir esa opcion en un script.

### 7.2 Health score

Formula inicial, versionada y configurable por servidor:

```text
score = max(0, 100
  - 10 por cada indice critico, hasta un limite configurado
  - 5 por cada estadistica vencida, hasta un limite configurado
  - 15 por cada heap de alto impacto, hasta un limite configurado
  - deduccion adicional configurable por page splits sostenidos)
```

El resultado debe guardar el detalle de deducciones en snapshot o metadata para que el dashboard explique por que un servidor recibio cierto score. No se deben ocultar problemas solo porque el score ya llego a cero.

### 7.3 Fill factor optimo

| Señal | Recomendacion inicial |
|---|---|
| `user_updates` alto respecto a seeks, scans y lookups | Fill factor entre 75 y 85, sujeto a politica. |
| Lecturas dominantes y pocos updates | Fill factor entre 90 y 100. |
| Evidencia insuficiente o contadores reiniciados recientemente | No cambiar fill factor; marcar como observacion. |
| Page splits sostenidos | Priorizar revision de fill factor y patron de inserciones. |

La regla debe registrar numerador, denominador, ventana observada y razon. Ningun valor se toma de un boton WhatsApp ni de texto ingresado por el usuario.

### 7.4 Estadisticas desactualizadas

Usar `sys.dm_db_stats_properties` junto con metadata de `sys.stats`. Calcular `modification_counter / rows`, proteger division por cero y excluir objetos no compatibles. La alerta debe identificar tabla, estadistica, filas, modificaciones, ratio y umbral efectivo.

### 7.5 Missing indexes

Usar `sys.dm_db_missing_index_details`, `sys.dm_db_missing_index_groups` y `sys.dm_db_missing_index_group_stats`. La recomendacion debe incluir columnas de igualdad, desigualdad e inclusion, impacto estimado, frecuencia y un fingerprint normalizado.

No crear automaticamente un indice basado solo en estas DMV. Antes de mostrar `CREATE INDEX`, validar que no existe un indice equivalente, que no supera limites de columnas/tamano definidos y que no es una sugerencia obsoleta.

### 7.6 Indices no usados o subutilizados

La condicion inicial propuesta es lecturas iguales a cero o inferiores al umbral durante un periodo minimo de observacion, con escrituras significativas. Deben excluirse de forma automatica:

- Primary keys y unique constraints.
- Indices usados por claves foraneas o restricciones identificadas por politica.
- Indices con contadores reiniciados recientemente.
- Indices creados despues del inicio de la ventana de observacion.
- Indices marcados como protegidos por un administrador.

La primera accion segura es `REVIEW`. `DISABLE INDEX` y `DROP INDEX` requieren politica de alto riesgo, SQL preview y confirmacion adicional.

### 7.7 Indices duplicados o redundantes

Normalizar por servidor, schema, tabla, orden de columnas clave, direccion ASC/DESC, columnas incluidas, filtro, unicidad y propiedades especiales. Un prefijo comun no basta para afirmar equivalencia. La alerta debe explicar cual indice cubre a cual y por que no se recomienda eliminar una clave o restriccion.

### 7.8 Heaps

Detectar tablas con `sys.indexes.type = 0`, actividad relevante, tamano y, cuando sea posible, evidencia de forwarded records. La propuesta de indice clustered debe ser una recomendacion revisable: IndexWatch no puede deducir con certeza la clave de negocio correcta sin contexto adicional.

### 7.9 Page splits

No basar alertas por indice solo en `sys.dm_os_performance_counters`, porque sus valores son globales por instancia. Usar `sys.dm_db_index_operational_stats` cuando este disponible, almacenar muestras y calcular deltas entre scans. El contador global puede aparecer como contexto de servidor, no como atribucion directa a un indice.

## 8. Politica de acciones y SQL generado

| Accion | Nivel | Condiciones obligatorias | Ejecutor |
|---|---|---|---|
| `REORGANIZE` | Medio | Alerta valida, contacto autorizado, ventana si la politica la exige. | Job de mantenimiento. |
| `REBUILD` | Alto | Vista previa, capacidad confirmada, fill factor validado, ventana, contacto autorizado. | Job de mantenimiento. |
| `UPDATE STATISTICS` | Medio | Alerta de estadisticas vigente y autorizacion. | Job de mantenimiento. |
| `CREATE INDEX` | Alto riesgo | Deteccion validada, no duplicado, presupuesto de almacenamiento, doble confirmacion o politica admin. | Job de mantenimiento. |
| `DISABLE INDEX` | Alto riesgo | Periodo de observacion, exclusiones aprobadas, doble confirmacion. | Job de mantenimiento. |
| `DROP INDEX` | Muy alto riesgo | Todo lo anterior, backup/log de rollback y politica administrativa explicita. | Job de mantenimiento. |
| `CREATE CLUSTERED` | Muy alto riesgo | Revision humana de clave, impacto de bloqueo y espacio, ventana estricta. | Job de mantenimiento. |

Reglas no negociables:

- Los identificadores SQL provienen de metadata almacenada y validada; nunca de texto de WhatsApp.
- Los nombres se escapan con un generador centralizado de identificadores SQL Server.
- Las opciones de T-SQL se construyen desde una lista permitida, no por concatenacion libre.
- Cada Job vuelve a leer la alerta y la accion bajo bloqueo antes de ejecutar.
- Un Job que ya dejo evidencia de exito no se repite por un retry automatico.
- Los errores se almacenan sanitizados. El SQL completo queda en auditoria solo para usuarios autorizados.

## 9. Distribucion equilibrada del equipo

Cada integrante tiene trabajo de implementacion, pruebas, documentacion y revision. La estimacion usa puntos relativos para detectar desbalance; no es una promesa de horas exactas.

| Integrante | Dominio principal | Puntos estimados | Entregable principal |
|---|---|---:|---|
| 1 | Modelo de datos, configuracion y persistencia de dominio | 47 | Base de control confiable y trazable en PostgreSQL. |
| 2 | Conectividad SQL Server, inventario y salud base | 48 | Motor de escaneo seguro para metricas reales. |
| 3 | Analitica avanzada y motor de reportes | 47 | Hallazgos explicables y exportaciones reales. |
| 4 | WhatsApp, autorizacion, ventanas y ejecucion segura | 48 | Flujo asincrono de aprobacion a mantenimiento auditado. |
| 5 | Dashboard, APIs de experiencia, calidad e integracion | 47 | Interfaz real, segura y lista para liberar. |

### 9.1 Reglas de equidad

- Ningun integrante queda asignado solo a pruebas, documentacion o tareas de soporte.
- Cada integrante escribe sus propias pruebas unitarias y feature para lo que entrega.
- Cada integrante redacta un runbook corto para su dominio.
- Los puntos se revisan al cierre de cada sprint. Si un bloqueo desplaza mas de cinco puntos, se redistribuye trabajo antes de iniciar el siguiente sprint.
- Los cambios de alto riesgo requieren revision de dos personas: propietario del dominio y revisor asignado.
- El responsable final no debe aprobar su propio cambio critico en solitario.

### 9.2 Parejas de revision

| Autor principal | Revisor principal | Revisor de respaldo |
|---|---|---|
| Integrante 1 | Integrante 4 | Integrante 5 |
| Integrante 2 | Integrante 3 | Integrante 1 |
| Integrante 3 | Integrante 2 | Integrante 5 |
| Integrante 4 | Integrante 1 | Integrante 2 |
| Integrante 5 | Integrante 3 | Integrante 4 |

## 10. Plan detallado: Integrante 1

### Mision

Construir el plano de control PostgreSQL que soporta configuracion, inventario historico, alertas, acciones, auditoria y contactos autorizados. Este integrante no implementa consultas DMV ni ejecucion SQL Server; entrega contratos de persistencia estables para los demas dominios.

### Carga estimada: 47 puntos

| Trabajo | Puntos | Entregable verificable |
|---|---:|---|
| Migraciones v2, constraints, indices y plan de rollback | 9 | Migraciones nuevas ejecutan y revierten en base limpia y con datos demo. |
| Modelos, relaciones, casts cifrados y enums de dominio | 7 | Modelos sin secretos serializables y relaciones cubiertas por pruebas. |
| Configuracion de servidores, umbrales y contactos autorizados | 7 | Requests, validaciones y persistencia de configuracion consistentes. |
| Snapshots, retencion e historico de indices | 7 | Insercion idempotente de muestras y consultas de tendencia. |
| Ciclo de alertas, fingerprints y persistencia de acciones | 8 | No hay alertas abiertas duplicadas para el mismo hallazgo. |
| Auditoria y consultas filtradas de historial | 5 | Registros inmutables y paginables por servidor, alerta, actor y fecha. |
| Backfill, fixtures de desarrollo y documentacion de esquema | 4 | Datos existentes migrados de forma segura y fixtures reproducibles. |
| Pruebas, revision cruzada y runbook de migracion | 5 | Cobertura de regresion y guia operativa entregada. |

### Tareas por sprint

| Sprint | Trabajo detallado |
|---|---|
| 0 | Inventariar columnas actuales de `servers`, `indexes` y `alerts`; definir contrato de estados con Integrante 4; confirmar capacidades JSONB y indices parciales en Neon. |
| 1 | Crear migraciones aditivas para campos de `servers`, expansion de `indexes`, `index_snapshots`, `authorized_contacts` y cambios compatibles de `alerts`. Preparar script de backfill de claves estables. |
| 2 | Crear modelos, casts, relaciones, factories y validadores. Implementar cifrado de credenciales existentes en entorno de prueba y documentar procedimiento seguro para produccion. |
| 3 | Implementar persistencia de `maintenance_windows`, `maintenance_actions` y `audit_logs`. Acordar transacciones e invariantes con Integrante 4. |
| 4 | Implementar deduplicacion de alertas por fingerprint, retencion de snapshots y consultas de historico que usaran dashboard y reportes. |
| 5 | Preparar migracion de datos de prototipo, ejecutar pruebas de upgrade, revisar planes de indices PostgreSQL y entregar runbook de backup, migracion y rollback. |
| 6 | Corregir hallazgos de UAT, validar integridad tras ejecuciones reales de prueba y cerrar documentacion de modelo. |

### Contratos que entrega al equipo

| Contrato | Consumidores | Regla |
|---|---|---|
| `Server` con thresholds y conexion protegida | Integrantes 2, 4 y 5 | Ningun consumidor puede recibir password mediante JSON. |
| `Index` e `IndexSnapshot` | Integrantes 2, 3 y 5 | El identificador estable es servidor + object_id + index_id. |
| `Alert` y fingerprint | Integrantes 2, 3, 4 y 5 | Una alerta abierta representa un unico hallazgo activo. |
| `MaintenanceAction` | Integrante 4 y 5 | El estado cambia de forma transaccional y auditable. |
| `AuditLog` | Integrantes 3, 4 y 5 | Es append-only desde el codigo de aplicacion. |

### Pruebas obligatorias

- Migraciones `up` y `down` sobre PostgreSQL de prueba.
- Validacion de thresholds invalidos, zonas horarias invalidas y telefonos no normalizados.
- Cast cifrado de password y ausencia de secreto en `toArray`, recursos JSON y logs.
- Unicidad de inventario y fingerprint de alertas abiertas.
- Creacion de snapshots repetidos sin duplicar la muestra esperada.
- Integridad referencial al eliminar o desactivar un servidor.
- Consultas de auditoria filtradas y paginadas.

### Definition of Done

El Integrante 1 termina cuando los demas pueden guardar y consultar cualquier hallazgo, accion y evidencia sin tocar el esquema manualmente; las migraciones funcionan en limpio y en upgrade; y ningun secreto llega a respuestas HTTP o logs.

## 11. Plan detallado: Integrante 2

### Mision

Construir la conexion dinamica, el inventario real y las metricas base de SQL Server. Este integrante entrega lecturas confiables y de bajo impacto para fragmentacion, uso, fill factor, estadisticas, health score y page splits.

### Carga estimada: 48 puntos

| Trabajo | Puntos | Entregable verificable |
|---|---:|---|
| Drivers, capacidad, conexion dinamica y timeouts | 7 | Prueba de conexion por servidor sin contaminar otras conexiones. |
| Inventario de tablas e indices y fragmentacion | 9 | Upsert de indices reales con identificadores estables y filtros de paginas. |
| Uso, fill factor y estadisticas | 8 | Metricas de lecturas/escrituras y recomendacion explicable. |
| Comando de escaneo, Jobs por servidor y manejo de fallos | 8 | Un servidor fallido no bloquea el scan de los demas. |
| Health score y page splits por deltas | 6 | Score reproducible y muestreo temporal de actividad operativa. |
| Deteccion de capacidades SQL Server | 4 | El generador conoce version, edicion y permisos disponibles. |
| Fixtures SQL, pruebas de integracion y limites de carga | 6 | Consultas verificadas contra SQL Server de pruebas. |

### Tareas por sprint

| Sprint | Trabajo detallado |
|---|---|
| 0 | Verificar extensiones `sqlsrv` y `pdo_sqlsrv`, conectividad de red, TLS, permisos minimos y disponibilidad de una instancia SQL Server de pruebas. Documentar matriz de versiones y ediciones objetivo. |
| 1 | Crear `SqlServerConnectionFactory` con nombre de conexion aislado por servidor, `DB::purge`, timeout, encryption y manejo de certificados. Crear `SqlServerCapabilityService` para version, edition y permisos DMV. |
| 2 | Implementar consultas de metadata, inventario y fragmentacion. Usar identificadores SQL Server, schema y page count. Integrar upsert con contratos del Integrante 1. |
| 3 | Implementar uso de indices, deteccion de reinicio de contadores, fill factor recomendado y estado de estadisticas. Crear `indexwatch:scan` y Jobs por servidor. |
| 4 | Implementar health score versionado y muestreo de page splits con deltas entre scans. Publicar DTOs para Integrante 3 y reglas para Integrante 4. |
| 5 | Medir impacto de queries en instancia de pruebas, ajustar queries, timeouts y concurrencia. Ejecutar pruebas de desconexion, permisos insuficientes y servidor lento. |
| 6 | Participar en UAT con SQL Server de staging, validar resultados contra consultas manuales de DBA y corregir discrepancias. |

### Consultas y salvaguardas

| Area | Fuente SQL Server | Salvaguarda |
|---|---|---|
| Fragmentacion | `sys.dm_db_index_physical_stats` | Filtrar por paginas; usar modo menos costoso al inicio; no consultar todos los objetos con detalle maximo sin necesidad. |
| Uso | `sys.dm_db_index_usage_stats` | Guardar momento de inicio; los contadores se reinician en restart. |
| Fill factor | `sys.indexes` mas estadisticas de uso | Recomendar, no cambiar automaticamente. |
| Estadisticas | `sys.dm_db_stats_properties`, `sys.stats` | Proteger objetos sin filas o no compatibles. |
| Page splits | `sys.dm_db_index_operational_stats` | Calcular delta temporal por indice; tratar contador global como contexto. |
| Capacidades | `SERVERPROPERTY`, permisos y prueba controlada | Nunca asumir soporte de `ONLINE = ON`. |

### Pruebas obligatorias

- Conexion a dos servidores con credenciales y opciones diferentes sin cruce de configuracion.
- Fallo de conexion, timeout, certificado no confiable y permiso DMV insuficiente.
- Inventario con mismo nombre de indice en tablas distintas.
- Fragmentacion en indice pequeno que debe ser ignorado por el filtro.
- Reinicio de SQL Server que invalida contadores de uso.
- Calculo de fill factor para carga predominantemente OLTP y predominantemente lectura.
- Calculo de health score con limites y sin valores negativos.
- Deltas de page split en dos scans sucesivos.

### Definition of Done

El Integrante 2 termina cuando `php artisan indexwatch:scan` puede recorrer servidores activos, guardar metricas reales y fallar de forma aislada y visible sin ejecutar DDL ni comprometer el rendimiento de SQL Server.

## 12. Plan detallado: Integrante 3

### Mision

Implementar las detecciones avanzadas que convierten metricas en recomendaciones explicables y construir el motor de datos y exportacion para PDF y Excel. Este integrante no aprueba ni ejecuta DDL; entrega hallazgos con evidencia y riesgo clasificado.

### Carga estimada: 47 puntos

| Trabajo | Puntos | Entregable verificable |
|---|---:|---|
| Analizador de missing indexes | 7 | Candidatos normalizados con evidencia y exclusiones de duplicados. |
| Analizador de indices no usados | 5 | Recomendaciones solo despues de ventana de observacion segura. |
| Analizador de redundancia y duplicados | 7 | Comparacion de claves, inclusiones, filtros y restricciones. |
| Analizador de heaps | 4 | Hallazgos de heaps con actividad y evidencia relevante. |
| Normalizador de hallazgos y recomendaciones | 6 | DTO comun consumible por alerta, auditoria y dashboard. |
| Dataset, PDF y Excel bajo demanda | 9 | Exportaciones filtradas y asincronas con datos historicos. |
| Ciclo de vida de reportes y almacenamiento | 4 | Archivos con estado, expiracion y acceso controlado. |
| Pruebas, documentacion de confianza y revision | 5 | Casos borde, explicacion de limites DMV y guia de usuario. |

### Tareas por sprint

| Sprint | Trabajo detallado |
|---|---|
| 0 | Definir con el DBA criterios de exclusion para PK, unique, foreign keys, objetos protegidos y periodo minimo de observacion. Diseñar formato comun de evidencia y severidad. |
| 1 | Crear contratos de hallazgo, fingerprints y datos necesarios desde el motor de scans. Investigar compatibilidad de `maatwebsite/excel` y `barryvdh/laravel-dompdf` con la version real de Laravel antes de instalarlos. |
| 2 | Implementar missing indexes con normalizacion de columnas y verificaciones contra indices existentes. Implementar prueba de falsos positivos conocidos. |
| 3 | Implementar analizador de indices no usados y detector de heaps. Definir claramente cuando una recomendacion solo debe ser `REVIEW`. |
| 4 | Implementar deteccion de duplicados y redundancia. Construir `ReportDataService`, exportacion Excel y PDF con filtros de servidor, fecha y tipo de alerta. |
| 5 | Implementar `GenerateReportJob`, almacenamiento, expiracion y registro en `generated_reports`. Integrar enlace de descarga con Integrante 5. |
| 6 | Validar recomendaciones con un DBA, documentar falsos positivos aceptados, ajustar score de confianza y apoyar UAT. |

### Reglas de analitica avanzada

| Hallazgo | Evidencia minima | Accion inicial por defecto |
|---|---|---|
| Missing index | Impacto DMV, frecuencia, columnas normalizadas y ausencia de equivalente. | `REVIEW`; habilitar `CREATE INDEX` solo por politica de alto riesgo. |
| Indice no usado | Ventana suficiente, lecturas bajas, actualizaciones altas y contador estable. | `REVIEW`. |
| Indice redundante | Indice cubriente identificado, sin violar unicidad, filtro o constraint. | `REVIEW`. |
| Heap | Heap, actividad o tamano relevante, evidencia de riesgo. | `REVIEW`. |

### Contenido minimo de cada reporte

| Seccion | Datos |
|---|---|
| Resumen ejecutivo | Periodo, servidores incluidos, health score y conteos por severidad. |
| Fragmentacion | Top indices, tendencia, accion recomendada y resultado de mantenimientos. |
| Estadisticas | Objetos vencidos, ratio de modificaciones, umbrales y acciones. |
| Riesgos de diseno | Missing indexes, heaps, indices no usados y redundantes con nivel de confianza. |
| Auditoria | Autorizaciones, ejecutores, resultado, fallos y SQL preview autorizado. |
| Anexo | Filtros usados, fecha de generacion, version de algoritmo y limitaciones. |

### Pruebas obligatorias

- Missing index ya cubierto por un indice existente no debe generar recomendacion duplicada.
- PK, unique y objetos protegidos no deben entrar en lista de eliminacion.
- Indice con contadores reiniciados no debe etiquetarse como no usado.
- Redundancia con filtros distintos debe clasificarse como ambigua, no como eliminacion segura.
- Heap pequeno e inactivo no debe escalar a alerta critica.
- Reportes sin datos, con rango grande y con datos de varios servidores.
- Autorizacion de descarga, expiracion de archivo y ausencia de secretos en PDF/Excel.

### Definition of Done

El Integrante 3 termina cuando cada hallazgo avanzado tiene evidencia suficiente, limitaciones documentadas, deduplicacion compatible con alertas y exportaciones descargables que reflejan datos reales sin exponer secretos.

## 13. Plan detallado: Integrante 4

### Mision

Convertir WhatsApp en un canal seguro de aprobacion y construir el ciclo completo de mantenimiento: autorizacion, auditoria, ventana, programacion, bloqueo, generacion T-SQL, ejecucion, resultado y notificacion.

### Carga estimada: 48 puntos

| Trabajo | Puntos | Entregable verificable |
|---|---:|---|
| Refactor seguro de `WhatsAppService` | 8 | Mensajes tipados por alerta, TLS activo y errores manejados. |
| Webhook, firma, parsing real, contactos e idempotencia | 9 | Eventos genuinos de Meta se validan una sola vez. |
| Resolvedor de ventanas y programacion de Jobs | 8 | Calcula correctamente siguiente ventana, incluidas zonas y cruces de medianoche. |
| Politica de acciones y generador T-SQL | 7 | Solo genera SQL permitido desde metadata validada. |
| Ejecucion, bloqueos, revalidacion, auditoria y notificacion | 9 | El Job nunca ejecuta dos veces una accion aprobada. |
| Fallos, retry seguro, runbooks y pruebas | 7 | Estados de error comprensibles y recuperables. |

### Tareas por sprint

| Sprint | Trabajo detallado |
|---|---|
| 0 | Auditar endpoint actual, payload real de Meta, headers de firma, restricciones de botones y ventana de mensajeria. Acordar estados de alerta/accion con Integrante 1. |
| 1 | Eliminar `withoutVerifying`, agregar configuracion de app secret, verificar `X-Hub-Signature-256`, normalizar payload de `entry.0.changes.0.value` y deduplicar por id de mensaje de Meta. |
| 2 | Crear catalogo de acciones permitidas por tipo de alerta y mensajes interactivos dinamicos. Validar telefono contra `authorized_contacts`; registrar aprobacion y rechazo. |
| 3 | Implementar `MaintenanceWindowResolver`, Jobs unicos y programacion para proxima ventana. Soportar dias, zona horaria y ventanas que cruzan medianoche. |
| 4 | Implementar `TsqlGeneratorService` y `ExecuteMaintenanceJob` con locks por servidor y accion, precondiciones, SQL preview, captura de resultado y auditoria. |
| 5 | Integrar todas las alertas avanzadas de Integrante 3, manejar confirmaciones de alto riesgo, fallos, cancelacion y reintentos seguros. |
| 6 | Ejecutar simulaciones end-to-end con SQL Server staging, revisar runbook de incidentes y completar UAT de WhatsApp. |

### Reglas de WhatsApp

| Area | Regla |
|---|---|
| Verificacion inicial | Conservar verify token solo para challenge; no confundirlo con validacion criptografica de POST. |
| POST | Verificar firma HMAC con app secret antes de procesar el cuerpo. |
| Idempotencia | Guardar id de mensaje/evento Meta procesado; una pulsacion no puede crear dos ejecuciones. |
| Botones | Mostrar como maximo las acciones permitidas y una opcion de descarte o revision. |
| IDs de boton | Usar token de accion corto y firmado o almacenado; no confiar en `accion_alertaId` sin validacion de servidor. |
| Contactos | Solo telefonos activos y autorizados pueden aprobar. Los demas reciben rechazo auditado o ninguna respuesta segun politica. |
| Mensajeria | Usar plantilla aprobada si la conversacion esta fuera de la ventana permitida por Meta. |
| Errores | No incluir credenciales, stack traces ni SQL completo en mensajes de usuario. |

### Reglas del Job de ejecucion

1. Cargar la accion bajo transaccion y bloqueo.
2. Confirmar que no termino ni fue cancelada.
3. Revalidar alerta, servidor activo, contacto aprobador y ventana de mantenimiento.
4. Consultar capacidad SQL Server proporcionada por Integrante 2.
5. Generar SQL desde metadata y politica permitida.
6. Registrar el intento en `audit_logs` antes de ejecutar.
7. Ejecutar con timeout y captura controlada de error.
8. Guardar resultado, estado y timestamps.
9. Despachar nuevo scan o marcar scan prioritario para verificacion posterior.
10. Enviar confirmacion de exito, fallo o programacion por WhatsApp.

### Pruebas obligatorias

- Firma valida, firma invalida y payload incompleto de Meta.
- Pulsacion repetida, entrega repetida y webhook fuera de orden.
- Telefono no autorizado, alerta cerrada y accion no permitida.
- Ventana activa, futura, inexistente y que cruza medianoche.
- Dos Jobs concurrentes para la misma accion o servidor.
- Falla de SQL Server antes, durante y despues de ejecutar.
- Retry de un Job que ya ejecuto con exito.
- Confirmacion de alto riesgo y cancelacion antes de ventana.

### Definition of Done

El Integrante 4 termina cuando una accion puede aprobarse desde WhatsApp por un contacto permitido, quedar auditada, programarse o ejecutarse una vez dentro de politica y comunicar un resultado verificable sin bloquear el webhook.

## 14. Plan detallado: Integrante 5

### Mision

Transformar el dashboard existente en una interfaz de produccion con datos reales, administrar la experiencia de configuracion y operaciones, definir contratos HTTP, aplicar autenticacion/autorizacion e integrar calidad de liberacion. Este integrante no es solo QA: entrega toda la superficie de uso del sistema.

### Carga estimada: 47 puntos

| Trabajo | Puntos | Entregable verificable |
|---|---:|---|
| Autenticacion, roles, policies y proteccion de rutas | 7 | Dashboard y APIs internas no son publicos; webhook conserva excepcion minima. |
| API de dashboard, filtros, paginacion y contratos JSON | 8 | Datos reales, estables y sin HTML inseguro. |
| Refactor de JavaScript y reemplazo de maquetado estatico | 8 | Un solo ciclo de polling, manejo de errores y render seguro. |
| UI de operaciones, servidores, umbrales y ventanas | 8 | Acciones, configuracion y estados conectados a endpoints reales. |
| UI de auditoria y reportes | 5 | Filtros, estados y descargas autorizadas. |
| CI, calidad, pruebas de integracion, accesibilidad y release | 11 | Pipeline reproducible, smoke tests y checklist de produccion. |

### Tareas por sprint

| Sprint | Trabajo detallado |
|---|---|
| 0 | Auditar `dashboard.blade.php`, `public/js/dashboard.js`, rutas duplicadas y endpoint actual. Definir contrato JSON versionado, roles `admin`, `operator` y `viewer`, y flujo de login. |
| 1 | Proteger dashboard y APIs internas, unificar rutas de webhook, preparar layout para selector de servidor, estado de conexion y health score. Definir mocks de API para desarrollo paralelo. |
| 2 | Reescribir el fetch centralizado de dashboard; eliminar definiciones duplicadas de `fetchDashboardData`; evitar interpolar HTML no escapado; implementar estados loading, empty y error. |
| 3 | Conectar lista de indices, filtros, drawer, health score y alertas a datos reales. Crear vistas de configuracion de servidores y umbrales con validacion de backend. |
| 4 | Implementar centro de operaciones con `maintenance_actions`, vista de audit logs, configuracion de ventanas y enlaces de reportes. Integrar confirmaciones de alto riesgo. |
| 5 | Implementar descarga y estado de reportes, pruebas browser/feature, accesibilidad basica, validacion de autorizacion y pipeline CI. Preparar checklist de despliegue. |
| 6 | Ejecutar pruebas end-to-end, corregir regresiones visuales, validar rendimiento del polling y liderar demo/UAT de liberacion. |

### Contratos HTTP iniciales

| Metodo y ruta | Uso | Rol minimo |
|---|---|---|
| `GET /dashboard` | Vista principal. | viewer |
| `GET /api/dashboard/data` | KPIs, estado de servidores, alertas e indices resumidos. | viewer |
| `GET /api/servers` | Lista y estado de servidores. | viewer |
| `POST /api/servers` | Crear servidor. | admin |
| `POST /api/servers/{server}/test-connection` | Probar conexion sin exponer secreto. | admin |
| `PATCH /api/servers/{server}/thresholds` | Guardar thresholds. | admin |
| `GET/POST/PATCH /api/maintenance-windows` | Consultar y administrar ventanas. | admin |
| `GET /api/maintenance-actions` | Cola y estado de acciones. | operator |
| `POST /api/maintenance-actions/{action}/cancel` | Cancelar antes de ejecutar. | operator |
| `GET /api/audit-logs` | Consultar auditoria filtrada. | operator |
| `POST /api/reports` | Solicitar exportacion. | operator |
| `GET /api/reports/{report}` | Estado o descarga autorizada. | operator |

Las rutas finales deben decidirse en Sprint 0. No debe mantenerse el mismo webhook publicado simultaneamente en varias rutas sin una razon operativa documentada.

### Reglas de interfaz

- Mantener el estilo visual actual salvo que la funcionalidad requiera una nueva pantalla.
- El dashboard muestra datos de PostgreSQL, no consultas directas a SQL Server desde la peticion del navegador.
- Reemplazar el polling duplicado actual por una unica funcion centralizada con cancelacion o control de concurrencia.
- Escapar texto de alertas antes de insertarlo en DOM. El backend debe retornar datos estructurados, no fragmentos HTML concatenados.
- Paginar listas grandes y filtrar en servidor cuando corresponda.
- Mostrar hora de ultimo scan, estado de conexion, error sanitizado y estado de cada mantenimiento.
- Deshabilitar visualmente acciones que no correspondan a permisos, estado o politica, pero mantener validacion real en backend.
- Cumplir navegacion por teclado, etiquetas de formularios, contraste y mensajes de error legibles.

### Pruebas obligatorias

- Viewer no puede cambiar thresholds ni ejecutar acciones.
- Operator no puede crear servidor si la politica reserva esa accion a admin.
- Dashboard con cero datos, servidor caido, alerta resuelta y lista paginada.
- Un solo polling activo tras filtrar, ordenar o cambiar de pagina.
- Render de nombre de indice malicioso sin ejecucion HTML o JavaScript.
- Formularios invalidos, errores de API y expiracion de sesion.
- Descarga de reporte autorizada y no autorizada.
- Smoke test de `/up`, login, dashboard, webhook y worker en entorno staging.

### Definition of Done

El Integrante 5 termina cuando la interfaz actual conserva su valor visual pero todas las secciones operativas muestran datos reales, respetan permisos, no exponen XSS ni secretos y pasan el checklist de liberacion.

## 15. Roadmap por sprints

### Sprint 0: Preparacion y contratos (1 semana)

| Integrante | Compromiso |
|---|---|
| 1 | Auditoria de esquema, estrategia de migracion, estados de dominio y seguridad de credenciales. |
| 2 | Verificacion de driver SQL Server, acceso de red, permisos DMV y base de pruebas. |
| 3 | Criterios DBA para recomendaciones avanzadas y compatibilidad de paquetes de reportes. |
| 4 | Auditoria de WhatsApp, firma, payload real y politica de acciones. |
| 5 | Auditoria de rutas, dashboard, JS, roles y contrato inicial de API. |

**Gate de salida:** existe una instancia SQL Server no productiva, un archivo de variables de entorno sin secretos reales, un contrato de estados aprobado y un backlog refinado.

### Sprint 1: Fundacion de datos y conectividad

| Integrante | Compromiso |
|---|---|
| 1 | Migraciones base, modelos iniciales, cifrado y validadores. |
| 2 | Connection factory y deteccion de capacidad. |
| 3 | DTO comun de hallazgos y diseno de datasets de reporte. |
| 4 | Seguridad de WhatsApp y catalogo de acciones. |
| 5 | Auth base, limpieza de rutas y mocks de frontend. |

**Gate de salida:** se puede registrar un servidor, probar conexion de forma segura y persistir configuracion sin exponer secretos.

### Sprint 2: Escaneo real de solo lectura

| Integrante | Compromiso |
|---|---|
| 1 | Upsert de indices, snapshots y fixture de datos. |
| 2 | Inventario, fragmentacion, uso y primer comando de scan. |
| 3 | Missing indexes y validacion contra indice equivalente. |
| 4 | Parsing webhook, contactos autorizados e idempotencia. |
| 5 | Fetch unico, dashboard real basico y estados de error. |

**Gate de salida:** un scan real actualiza PostgreSQL y el dashboard muestra inventario y fragmentacion de un servidor de pruebas.

### Sprint 3: Alertas, score y programacion

| Integrante | Compromiso |
|---|---|
| 1 | Alert fingerprints, auditoria y modelos de acciones/ventanas. |
| 2 | Health score, estadisticas, fill factor y Jobs por servidor. |
| 3 | Indices no usados y heaps con reglas de exclusion. |
| 4 | Ventanas, agenda de Jobs y autorizacion por WhatsApp. |
| 5 | Configuracion de thresholds, servidores y operaciones basicas. |

**Gate de salida:** una alerta real se deduplica, se notifica, se aprueba en entorno de prueba y queda programada correctamente sin ejecutar DDL aun.

### Sprint 4: Ejecucion controlada y analitica avanzada

| Integrante | Compromiso |
|---|---|
| 1 | Consultas de historico, retencion y endurecimiento de transacciones. |
| 2 | Page splits por delta, capacidades y ajuste de scanner. |
| 3 | Duplicados, reportes PDF/Excel y datos de tendencia. |
| 4 | T-SQL generator, locks, ejecucion staging y notificaciones de resultado. |
| 5 | Centro de operaciones, auditoria, ventanas y reportes en UI. |

**Gate de salida:** un `UPDATE STATISTICS` o mantenimiento de indice de bajo riesgo aprobado se ejecuta una vez en staging y se verifica con scan posterior.

### Sprint 5: Endurecimiento y UAT

| Integrante | Compromiso |
|---|---|
| 1 | Upgrade de base, backup/rollback y pruebas de integridad. |
| 2 | Pruebas de carga de scans, desconexion y permisos insuficientes. |
| 3 | Validacion DBA de hallazgos y calidad de reportes. |
| 4 | Simulacion de fallos, retries y acciones de alto riesgo sin ejecutar en produccion. |
| 5 | E2E, CI, accesibilidad, seguridad web y checklist de despliegue. |

**Gate de salida:** todos los criterios de aceptacion, pruebas de seguridad y runbooks estan aprobados por el equipo.

### Sprint 6: Piloto controlado y liberacion

| Integrante | Compromiso |
|---|---|
| 1 | Soporte de migracion productiva y verificacion de datos. |
| 2 | Monitoreo de scans de piloto y comparacion con DBA. |
| 3 | Revision de recomendaciones y primer reporte real. |
| 4 | Validacion de WhatsApp, colas, ventanas y acciones permitidas en piloto. |
| 5 | Monitoreo de interfaz, logs, feedback UAT y cierre de release. |

**Gate de salida:** piloto con uno o dos servidores no criticos, resultados revisados y aprobacion explicita antes de ampliar alcance.

## 16. Dependencias e integracion

| Dependencia | Productor | Consumidor | Regla de integracion |
|---|---|---|---|
| Esquema y estados | Integrante 1 | Todos | Debe fusionarse antes de persistir metricas o acciones. |
| Conexion y DTO de metricas | Integrante 2 | Integrantes 3 y 4 | Exponer contrato probado; no compartir arrays sin estructura definida. |
| Hallazgos normalizados | Integrante 3 | Integrantes 1, 4 y 5 | Deben incluir fingerprint, severidad, evidencia y accion sugerida. |
| Politica y ejecucion | Integrante 4 | Integrantes 1, 2 y 5 | Debe consumir metadata, no datos ingresados por cliente. |
| API y UI | Integrante 5 | Todos | Publicar contrato OpenAPI simple o documento equivalente antes de integrar frontend. |

### Orden obligatorio de fusion

1. Migraciones, modelos y factories del Integrante 1.
2. Conexion y scan de solo lectura del Integrante 2.
3. Dashboard de lectura real del Integrante 5.
4. Alertas y analitica de Integrantes 2 y 3.
5. WhatsApp y programacion del Integrante 4 sin DDL habilitado.
6. Ejecucion staging de acciones de bajo riesgo.
7. Reportes, hallazgos de alto riesgo y politicas administrativas.

No se debe habilitar un boton de ejecucion real hasta que el scan, alerta, auditoria, ventana y Job hayan pasado pruebas de integracion.

## 17. Seguridad, operaciones y despliegue

### 17.1 Seguridad obligatoria

- Usar HTTPS valido para la aplicacion y para llamadas a Meta. Eliminar `Http::withoutVerifying()`.
- Guardar `WHATSAPP_TOKEN`, app secret, credenciales SQL y claves de Neon exclusivamente en variables de entorno o gestor de secretos.
- Cifrar credenciales almacenadas; no registrar valores de `password`, headers Authorization ni cuerpos sensibles.
- Verificar firma del webhook POST de Meta y aplicar rate limiting.
- Separar cuenta SQL de lectura y cuenta de mantenimiento cuando la operacion lo permita.
- Dar permisos minimos: la cuenta de scanner necesita solo vistas DMV requeridas; la de mantenimiento solo DDL permitido en bases autorizadas.
- Proteger dashboard y APIs con autenticacion y roles.
- Mantener webhook como unica ruta publica necesaria y con exclusion CSRF limitada a esa ruta concreta.
- Validar input con Form Requests y escapar salida HTML en el navegador.
- Aplicar politicas de retencion para snapshots, auditoria y exportaciones.

### 17.2 Queue y scheduler

| Proceso | Requisito |
|---|---|
| Scheduler | Ejecutar `php artisan schedule:run` cada minuto mediante servicio de plataforma o cron. |
| Workers | Worker persistente supervisado; configurar timeout, retry y memoria. |
| Colas | Separar como minimo `scans`, `notifications`, `maintenance` y `reports`. |
| Exclusividad | Jobs de mantenimiento deben ser unicos y usar locks por servidor/accion. |
| Fallos | Configurar tabla o backend de failed jobs, alerta operativa y procedimiento de reintento manual. |
| Redis | Recomendado para locks y colas de produccion; si no esta disponible, documentar limitaciones de alternativa database. |

### 17.3 Observabilidad

- Log estructurado con `server_id`, `alert_id`, `maintenance_action_id`, correlacion de webhook y duracion.
- Metricas: servidores escaneados, scans fallidos, alertas creadas, mensajes enviados, Jobs fallidos, acciones exitosas y duracion de mantenimiento.
- Alertar al equipo si un servidor no se escanea dentro del intervalo esperado.
- Alertar si el worker no procesa cola o si una accion queda `running` mas alla del timeout.
- Mantener endpoint `/up` y un smoke test autenticado para dashboard.

## 18. Estrategia de pruebas y calidad

| Nivel | Responsable principal | Cobertura |
|---|---|---|
| Unit | Cada propietario | Reglas de score, thresholds, fingerprints, T-SQL, ventanas, detectores y normalizadores. |
| Feature | Cada propietario con Integrante 5 | Requests, policies, dashboard APIs, webhook, cancelacion y reportes. |
| Integration SQL | Integrante 2 con Integrantes 3 y 4 | DMV, capacidades, scan y ejecucion staging. |
| Integration WhatsApp | Integrante 4 | Fixtures de Meta, firma, idempotencia y respuestas. |
| E2E | Integrante 5 con equipo | Login, scan visible, alerta, aprobacion, programacion, resultado y reporte. |
| Seguridad | Integrantes 4 y 5 | Secretos, firma, roles, XSS, CSRF, rate limiting y autorizacion. |
| UAT DBA | Integrantes 2 y 3 | Exactitud de metricas, recomendaciones y SQL preview. |

### Calidad minima de pull request

- Una tarea vinculada a issue o item del plan.
- Pruebas nuevas o explicacion escrita de por que no aplican.
- `composer test` exitoso.
- Formato aplicado con Laravel Pint.
- Sin secretos, dumps de produccion ni archivos locales versionados.
- Revision aprobada por la pareja asignada.
- Captura de UI o ejemplo de respuesta JSON para cambios visibles.
- Documentacion actualizada si cambia contrato, variable de entorno o procedimiento operativo.

## 19. Convenciones de colaboracion

| Tema | Regla |
|---|---|
| Ramas | `feature/iw-<id>-descripcion`, `fix/iw-<id>-descripcion` y `docs/iw-<id>-descripcion`. |
| Commits | Pequenos, descriptivos y sin mezclar refactor no relacionado con funcionalidad. |
| Pull requests | Un dominio por PR; incluir riesgo, prueba ejecutada y plan de rollback cuando cambia DB o DDL. |
| Ramas protegidas | No hacer push directo a `main`; requerir revision y CI verde. |
| Contratos | Cambios de estado, API o esquema se anuncian antes de fusionarse. |
| Reunion diaria | Maximo 15 minutos: hecho, siguiente, bloqueo y dependencia. |
| Refinamiento | Al inicio de cada sprint, revisar puntos, riesgos y capacidad real. |
| Demo | Al cierre de sprint, demostrar flujo funcional sobre datos de prueba. |

## 20. Riesgos y respuestas

| Riesgo | Impacto | Prevencion | Respuesta |
|---|---|---|---|
| Driver SQL Server no disponible en host | Bloquea escaneo real. | Verificar en Sprint 0. | Resolver imagen/host y no avanzar a ejecucion. |
| Permisos DMV insuficientes | Datos incompletos. | Matriz de permisos aprobada por DBA. | Mostrar capacidad degradada y no inventar metricas. |
| Scan pesado sobre produccion | Riesgo de rendimiento. | Filtros, modo limitado, timeouts y piloto. | Reducir frecuencia y alcance; pausar servidor. |
| Falso positivo de indice faltante o redundante | Cambio incorrecto. | Evidencia, score, exclusion y revision DBA. | Mantener accion en `REVIEW`; no automatizar DDL. |
| Webhook repetido | Doble ejecucion. | Firma, idempotencia, locks y Jobs unicos. | Marcar duplicado, no reprocesar. |
| Accion fuera de ventana | Interrupcion operativa. | Resolvedor con zona horaria y revalidacion en Job. | Cancelar Job y notificar al operador. |
| Error parcial de DDL | Estado incierto. | Auditoria previa, resultado SQL, scan posterior. | Marcar `failed` o `needs_review`; no reintentar a ciegas. |
| Exposicion de secretos | Incidente grave. | Cifrado, env, masking, revision y no TLS bypass. | Rotar secretos, investigar logs y bloquear acceso. |
| Desbalance entre integrantes | Retraso o burnout. | Puntos por sprint y propiedad clara. | Redistribuir backlog antes del siguiente sprint. |

## 21. Criterios de aceptacion de v2

IndexWatch v2 se considera listo para un piloto controlado solo si se cumplen todos los puntos siguientes:

- Se pueden registrar y probar al menos dos SQL Server o bases monitorizadas sin mezclar conexiones.
- El scan programado recolecta fragmentacion, uso, estadisticas y health score con evidencia persistida.
- Los thresholds cambian el comportamiento del siguiente scan por servidor.
- El dashboard muestra informacion real, ultimo scan, salud, alertas, inventario y estados de operacion.
- Las doce capacidades F01 a F12 estan implementadas al menos como deteccion o flujo de revision segun su riesgo.
- WhatsApp valida firma, contacto y accion, y no bloquea mientras espera ejecucion.
- Cada autorizacion y ejecucion deja auditoria consultable.
- Las ventanas programan acciones correctamente y el Job vuelve a verificarlas antes de ejecutar.
- Las acciones de bajo riesgo se prueban en staging; las de alto riesgo estan protegidas por politica explicita.
- PDF y Excel se generan desde datos reales, respetan permisos y no exponen secretos.
- Tests unitarios, feature, integracion y smoke tests pasan en CI.
- Existe backup, rollback, runbook de incidentes y aprobacion de DBA para el piloto.

## 22. Lista de arranque inmediato

1. Asignar nombres reales a Integrantes 1 a 5 y confirmar disponibilidad semanal.
2. Crear issues a partir de Sprint 0 y marcar responsable, revisor, puntos y dependencia.
3. Preparar SQL Server de pruebas con datos representativos, no productivos.
4. Revisar permisos, red, extensiones PHP y secretos con el administrador de infraestructura.
5. Aprobar las tres decisiones base: reportes bajo demanda, auditoria por telefono/contacto y aprobacion previa obligatoria.
6. Ejecutar Sprint 0 antes de crear migraciones o habilitar conexiones productivas.

## 23. Registro de decisiones del equipo

| Fecha | Decision | Responsable | Estado |
|---|---|---|---|
| Pendiente | Confirmar version Laravel efectiva y plataforma de despliegue. | Equipo | Pendiente |
| Pendiente | Confirmar Redis o alternativa de queue/locks. | Integrantes 4 y 5 | Pendiente |
| Pendiente | Confirmar SQL Server de pruebas, version, edicion y permisos. | Integrante 2 con DBA | Pendiente |
| Pendiente | Confirmar politica de alto riesgo para create/disable/drop/clustered. | Equipo y DBA | Pendiente |
| Pendiente | Confirmar destinatarios y frecuencia de reportes programados futuros. | Equipo | Pendiente |

Este documento debe actualizarse al cierre de cada sprint. Cualquier cambio que afecte seguridad, acciones SQL o modelo de datos requiere registrar la decision antes de implementarlo.
