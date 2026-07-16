# Contexto Actual del Sistema: IndexWatch (Prototipo)

Este documento describe el estado **actual** del proyecto antes de la implementación de la versión 2.0.

## Infraestructura y Tecnologías
- **Framework:** Laravel 11
- **Base de Datos Interna:** PostgreSQL (alojada en Neon).
- **Frontend del Dashboard:** Vanilla HTML/CSS/JS estructurado dentro de vistas Blade de Laravel (`dashboard.blade.php`).

## Base de Datos (Esquema Actual)
Actualmente el sistema cuenta con migraciones y modelos Eloquent para 3 entidades principales:
1. **`servers`**: Almacena información de los servidores SQL Server (nombre, host, base de datos, usuario, contraseña, estado).
2. **`indexes`**: Almacena métricas estáticas de los índices monitoreados (nombre del índice, tabla, fragmentación %, tamaño en MB).
3. **`alerts`**: Almacena el estado de las alertas generadas (estado pendiente/resuelto, severidad, tipo de problema, y el ID del mensaje de WhatsApp enviado).

## Funcionalidades Principales (Operativas)

### 1. Integración con Meta (WhatsApp Cloud API)
- **`WhatsAppService.php`**: Un servicio dedicado que utiliza el `Http` facade de Laravel para enviar mensajes de plantilla e interactivos a la API de WhatsApp de Meta usando un Token Temporal.
- **`SendDemoAlert.php`**: Un comando de consola (`php artisan demo:send-alert`) que busca una alerta pendiente en la base de datos y la envía al celular del DBA. El mensaje incluye botones interactivos (ej. "REBUILD", "REORGANIZE").

### 2. Recepción de Respuestas (Webhook)
- **`WhatsAppWebhookController.php`**: 
  - Expone una ruta `GET` para la verificación inicial de Meta (`hub.challenge`).
  - Expone una ruta `POST` que recibe las respuestas de los botones cuando el usuario interactúa en WhatsApp.
  - Valida el botón presionado, cambia el estado de la alerta en la base de datos a `resolved`, genera un script T-SQL básico (actualmente solo lo genera, no lo ejecuta contra SQL Server real), y envía un mensaje de confirmación de vuelta al WhatsApp del usuario.

### 3. Dashboard en Tiempo Real
- **`DashboardController.php`**: Expone una API JSON en `/api/dashboard/data` que devuelve el consolidado de KPIs (Total, Críticos, Recomendados, Saludables) y las 5 últimas alertas de la base de datos.
- **Frontend (`dashboard.js`)**: Realiza peticiones asíncronas (`fetch`) al endpoint `/api/dashboard/data` cada 3 segundos (Polling).
- Si el usuario presiona el botón en WhatsApp, el Webhook marca la alerta como resuelta, y en menos de 3 segundos, la interfaz del Dashboard (KPIs, gráficos de anillo y lista de alertas) se actualiza visualmente de forma automática sin recargar la página.

## Limitaciones Actuales (Por qué es un prototipo)
- Los datos de fragmentación provienen de datos "semilla" (Seeders) en PostgreSQL, no de conexiones reales a SQL Server.
- Los scripts de mantenimiento (REBUILD) se generan como texto, pero aún no hay un motor que se conecte por ODBC/PDO al SQL Server real para ejecutarlos.
- No hay auditoría formal, reportes en PDF/Excel ni manejo de ventanas de mantenimiento.
