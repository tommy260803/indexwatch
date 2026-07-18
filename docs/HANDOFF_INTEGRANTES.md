# Handoff: Estado del Proyecto para Integrantes 3, 4 y 5

**Última actualización:** 18 de julio de 2026

¡Hola equipo! El Integrante 1 y el Integrante 2 hemos concluido nuestra parte del proyecto. 
A continuación, les dejamos el estado actual del sistema para que puedan continuar sin bloqueos.

## 0. Requisitos indispensables para su entorno local (¡Importante!)
Para que el escáner (que nosotros construimos) funcione en sus propias laptops cuando prueben el proyecto, **cada uno de ustedes debe configurar su SQL Server local**:
1. **Habiliten TCP/IP:** Abran *SQL Server Configuration Manager*, vayan a Protocolos, habiliten TCP/IP por el puerto 1433 y reinicien el servicio de SQL Server.
2. **Creen la base de datos de prueba:** Abran el archivo `database/sqlserver/indexwatch_test_setup.sql` en SSMS (con el Modo SQLCMD activado), pónganle una contraseña en la línea 5 y ejecútenlo. Esto creará la base `IndexWatch_Test` y el usuario seguro `indexwatch_scanner`.
3. **Registren su servidor:** Entren al Dashboard web, agreguen el servidor usando `127.0.0.1` o el nombre de su PC, y usen la contraseña que acaban de configurar.

## 1. Lo que ya está listo y funcionando

*   **Base de datos base (Neon/PostgreSQL):** El Integrante 1 diseñó un modelo de datos robusto (tablas de servidores, índices, alertas, auditoría, etc.). Las migraciones corren sin problemas.
*   **Conexión a SQL Server (Escáner):** El Integrante 2 construyó el motor completo (`SqlServerInspectorService`). El sistema ya es capaz de conectarse a servidores SQL Server por TCP/IP, ejecutar las vistas dinámicas (DMVs) y guardar las métricas reales de fragmentación y uso en las tablas de PostgreSQL.
*   **Job Automático:** Ya existe el comando `php artisan indexwatch:scan` y un Job encolado que se encarga de realizar la recolección sin que el sistema colapse.

## 2. Recomendaciones para el Integrante 3 (Reportes y Analítica)

Tu tarea depende directamente de los datos que nosotros (Integrante 2) acabamos de recolectar.
*   **Dónde encontrar los datos:** Los resultados de los escaneos se están guardando en las tablas `sql_indexes`, `index_snapshots`, e `index_operational_snapshots`.
*   **Consejo:** Antes de generar los reportes PDF/Excel, revisa el modelo `App\Models\SqlIndex`. Ahí encontrarás métodos útiles que ya dejamos preparados como `isFragmented()`, `isUnused()`, y `getReadWriteRatio()`. Usa esto como base para tu analítica avanzada.

## 3. Recomendaciones para el Integrante 4 (WhatsApp y Mantenimiento)

*   **No ejecutes al instante:** Recuerda que por las reglas del negocio, el Webhook de WhatsApp no debe ejecutar el comando de optimización en el momento exacto en el que el usuario presiona "Sí". Debes guardar la autorización en `audit_logs` y encolar el Job para que espere hasta la **ventana de mantenimiento** (maintenance window) definida para ese servidor.
*   **Idempotencia:** Asegúrate de verificar las firmas de los mensajes de Meta para evitar que el mismo botón, si es presionado dos veces por error, genere dos acciones en base de datos.

## 4. Recomendaciones para el Integrante 5 (Dashboard y Health Score)

*   **Health Score:** Recuerda que tu algoritmo para calcular la salud debe consumir la data que nosotros dejamos en las tablas. No consultes directamente a SQL Server desde el frontend; consulta nuestra tabla de PostgreSQL que ya tiene la última foto (snapshot) del servidor.
*   **UI:** Sigue usando Tailwind y la misma estética que se ha manejado hasta ahora para que el proyecto mantenga una sola identidad visual.

¡Mucho éxito con el resto del proyecto!
