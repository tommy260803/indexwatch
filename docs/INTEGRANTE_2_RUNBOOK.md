# Runbook del Scanner SQL Server

## Alcance

El scanner se conecta en modo lectura a cada SQL Server activo, recolecta metadata y DMV, y persiste los resultados en PostgreSQL. No ejecuta DDL ni mantenimiento.

## Preparar SQL Server local

1. Abre `database/sqlserver/indexwatch_test_setup.sql` en SSMS.
2. Activa **Consulta > Modo SQLCMD**.
3. Reemplaza `CHANGE_ME_WITH_A_STRONG_LOCAL_PASSWORD` por una clave local fuerte.
4. Ejecuta el script conectado como administrador a `ANTHONY`.
5. Conserva la clave fuera del repositorio.

El script crea:

- Base `IndexWatch_Test`.
- Login `indexwatch_scanner` sin roles de escritura o DDL.
- Permisos de metadata y rendimiento requeridos por SQL Server 2022.
- Una tabla y dos índices con datos no productivos.

Si la conexión TCP falla, habilita TCP/IP para la instancia en SQL Server Configuration Manager, fija el puerto `1433`, reinicia el servicio y permite el puerto solo en el firewall local.

## Registrar el servidor

Desde la pantalla de servidores registra:

| Campo | Valor local sugerido |
|---|---|
| Nombre | SQL Server local de pruebas |
| Host | `ANTHONY` |
| Puerto | `1433` |
| Base | `IndexWatch_Test` |
| Usuario | `indexwatch_scanner` |
| Password | La clave local elegida |
| Encrypt | Sí |
| Trust server certificate | Sí, solo para desarrollo local |
| Timeout | `60` |
| Minimum index pages | `1000` |

La contraseña se cifra mediante el cast de `Server` y no se serializa en respuestas.

## Aplicar esquema de control

Haz backup de Neon antes de producción. Revisa primero:

```powershell
php artisan migrate --pretend
```

Después aplica las migraciones en el entorno de desarrollo autorizado:

```powershell
php artisan migrate
```

## Verificar y escanear

Sustituye `1` por el ID mostrado en la lista de servidores:

```powershell
php artisan indexwatch:verify 1
php artisan indexwatch:scan --server=1 --sync
```

Para procesar todos los servidores mediante la cola:

```powershell
php artisan indexwatch:scan
php artisan queue:work --queue=scans --tries=3 --timeout=300
```

El scheduler ejecuta el comando con `INDEXWATCH_SCAN_SCHEDULE`, cuyo valor predeterminado es cada hora.

## Estados

- `success`: inventario y métricas disponibles.
- `degraded`: inventario guardado, pero una DMV opcional no estuvo disponible.
- `error`: no fue posible conectar o completar el inventario principal.
- `running`: scan en curso.

Consulta `server_scan_runs` para duración, capacidades, conteos, advertencias y errores sanitizados.

## Permisos mínimos

Para SQL Server 2022 el scanner usa:

- `VIEW DEFINITION` en la base monitoreada.
- `VIEW DATABASE PERFORMANCE STATE` en la base monitoreada.
- `VIEW SERVER PERFORMANCE STATE` para uso y tiempo de inicio.
- `SELECT` para propiedades de estadísticas. En producción debe limitarse por objetos o sustituirse con un módulo firmado si leer datos de negocio no es aceptable.

No concedas `sysadmin`, `db_owner`, `db_ddladmin`, `ALTER`, `CONTROL`, `CREATE` ni `DROP` a la cuenta scanner.

## Diagnóstico

- Certificado: en desarrollo local activa `trust_server_certificate`; en producción instala una cadena TLS válida.
- Timeout: reduce concurrencia o aumenta `INDEXWATCH_STATEMENT_TIMEOUT` con aprobación del DBA.
- Permiso DMV: `indexwatch:verify` mostrará la métrica degradada sin imprimir credenciales ni SQL.
- Reinicio SQL Server: el siguiente page-split delta queda como nueva línea base, nunca como valor negativo.
- Page splits: las alertas usan una tasa sostenida por minuto, no un contador global ni un delta dependiente de la frecuencia del scheduler.
- Índices pequeños: no reciben snapshot de fragmentación si tienen menos páginas que `minimum_index_pages`.
