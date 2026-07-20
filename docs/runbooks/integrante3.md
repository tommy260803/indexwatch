# Runbook Integrante 3 — Analytics Avanzadas y Reportes

## Alcance

Este dominio convierte métricas de SQL Server en **hallazgos explicables** con evidencia, nivel de confianza y acción recomendada. También genera **reportes bajo demanda** (HTML/CSV) con datos históricos reales.

## Componentes

| Componente | Archivo | Función |
|------------|---------|---------|
| MissingIndexAnalyzer | `app/Services/Analytics/MissingIndexesAnalyzer.php` | Detecta índices faltantes vía DMV `sys.dm_db_missing_index_*` |
| UnusedIndexAnalyzer | `app/Services/Analytics/UnusedIndexesAnalyzer.php` | Detecta índices sin lecturas en ventana de observación |
| DuplicateIndexAnalyzer | `app/Services/Analytics/DuplicateIndexesAnalyzer.php` | Compara claves, includes y filtros para detectar redundancia |
| HeapsAnalyzer | `app/Services/Analytics/HeapsAnalyzer.php` | Detecta tablas sin índice clúster con actividad relevante |
| ReportDataService | `app/Services/Reports/ReportDataService.php` | Construye dataset unificado para reportes |
| ReportExportService | `app/Services/Reports/ReportExportService.php` | Genera HTML/CSV descargable |
| GenerateReportJob | `app/Jobs/GenerateReportJob.php` | Job asíncrono para generación de reportes |

## Umbrales (config/indexwatch.php)

```php
'analytics' => [
    'unused_index_min_days' => 30,       // Ventana mínima de observación
    'unused_index_min_writes' => 100,    // Escrituras mínimas para considerar
    'heap_min_size_mb' => 100,           // Tamaño mínimo heap para alertar
    'heap_min_activity' => 1000,         // Actividad mínima para alertar
    'missing_index_min_impact' => 100.0,  // Impacto DMV mínimo
    'missing_index_min_ops' => 10,       // Seeks+Scans mínimo
],
```

## Ajustar confianza y umbrales

1. **Falsos positivos missing index**: aumentar `missing_index_min_impact` o `missing_index_min_ops`
2. **Índices "no usados" que sí se usan**: aumentar `unused_index_min_days` para exigir más observación
3. **Heaps pequeños generando alertas**: aumentar `heap_min_size_mb`

## Generar reportes

```bash
# Vía API
curl -X POST /api/reports -H "Authorization: Bearer TOKEN" \
  -d '{"server_id":1,"filters":{},"format":"html"}'

# Descarga
curl /api/reports/{id}/download > report.html
```

Los reportes expiran a 7 días (configurable en `config/indexwatch.php`).

## Limitaciones DMV documentadas

- Las métricas de uso (`sys.dm_db_index_usage_stats`) se reinician al reiniciar SQL Server
- Los missing indexes son sugerencias, no verdades absolutas
- Los page splits requieren muestreo temporal para calcular deltas
- El fill factor óptimo es una estimación basada en patrones de lectura/escritura
- Las DMV no conocen el contexto de negocio de la base de datos

## Diagnóstico

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| Sin hallazgos de missing index | Permisos DMV insuficientes | Verificar con `indexwatch:verify` |
| Duplicados no detectados | Índices en schemas distintos | Verificar `schema_name` en `sql_indexes` |
| Reporte vacío | Rango sin datos | Ampliar rango de fechas |
| Reporte "failed" | Error en generación | Revisar logs de `GenerateReportJob` |