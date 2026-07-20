# Guía de Pruebas End-to-End — IndexWatch v2

**Versión:** 2.0 — UI-focused  
**Fecha:** Julio 2026  
**Objetivo:** Verificar el flujo completo del sistema desde la interfaz web: registro de servidor → escaneo → alertas → WhatsApp → mantenimiento → verificación → reportes

---

## Requisitos previos

- PHP 8.3+ con extensiones: `pdo_sqlite`, `fileinfo`, `zip`, `gd`, `mbstring`, `xml`, `curl`, `bcmath`, `ctype`, `json`, `tokenizer`
- Composer 2.10+ | Node.js 18+
- SQL Server 2019+ local (opcional para escaneo real; sin él se usa datos semilla)

---

## Paso 1: Instalar y preparar el entorno

```powershell
# 1. Entrar al proyecto
cd D:\Proyects\indexwatch

# 2. Copiar .env (si no existe)
Copy-Item .env.example .env -ErrorAction SilentlyContinue

# 3. Instalar dependencias
composer install
npm install

# 4. Generar clave
php artisan key:generate

# 5. Migraciones + datos semilla + datos demo
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoDataSeeder

# 6. Compilar assets
npm run build

# 7. Levantar servidor
php artisan serve
```

Abre tu navegador en **http://127.0.0.1:8000**

---

## Paso 2: Verificar línea base

**Comando (opcional):**
```powershell
php artisan test
```
**Esperado:** `54 passed, 2 skipped` (los 2 requieren `pdo_sqlsrv` para SQL Server real)

---

## Paso 3: Login y Dashboard

### 3.1 Iniciar sesión

1. Ve a **http://127.0.0.1:8000/login**
2. Ingresa:
   - Email: `test@example.com`
   - Password: `admin123`
3. Haz clic en **Log in**

**Esperado:** Redirige a `/dashboard`

### 3.2 Verificar Dashboard (KPIs)

En **http://127.0.0.1:8000/dashboard**, verifica que:

| Elemento | Qué debe mostrar |
|----------|-----------------|
| **Tarjeta "Total de índices"** | Un número (ej. 19) |
| **Tarjeta "Críticos (>30%)"** | Un número > 0 |
| **Tarjeta "Recomendados (5-30%)"** | Un número > 0 |
| **Tarjeta "Saludables (<5%)"** | Un número > 0 |
| **Gráfico de anillo** | Los mismos valores en la leyenda |
| **Lista de alertas** | 5 alertas con texto, severidad y tiempo relativo ("hace X min/h") |

> **Los KPIs NO deben ser estáticos.** Cada 30 segundos se actualizan vía polling. Si presionas F5, los números deben mantenerse consistentes (no resetearse a valores hardcodeados).

### 3.3 Navegación lateral

Haz clic en cada ítem del menú lateral y verifica que carga la página correspondiente:

| Menú | URL | Debe mostrar |
|------|-----|-------------|
| Panel de control | `/dashboard` | KPIs + alertas + donut |
| Servidores | `/servers` | Tabla con servidor(es) |
| Índices | `/indices` | Tabla con índices, filtros, buscador |
| Centro de Operaciones | `/actions` | Interfaz de cola de mantenimiento |
| Auditoría | `/audit` | Tabla con registros de auditoría + filtros |
| Reportes | `/reports` | Formulario + tabla de reportes generados |
| Configuración | `/settings` | Umbrales y ajustes |

---

## Paso 4: Gestión de Servidores

Navega a **http://127.0.0.1:8000/servers**

### 4.1 Lista de servidores

**Debe mostrar:**

- Tabla con el servidor "SQL Server Demo"
- Columnas: Nombre, Host, Health Score, Último Scan, Estado, Índices, Alertas, Acciones
- Health Score debe mostrar **72** (o el valor cargado)

### 4.2 Ver detalle de un servidor

1. Haz clic en el nombre del servidor o en el botón **Ver**
2. Debe mostrar: nombre, host, puerto, base de datos, umbrales, health score

### 4.3 Editar umbrales

1. Haz clic en **Editar** en la fila del servidor
2. Modifica el campo **Warning Threshold** a `10.00`
3. Haz clic en **Actualizar**
4. Verifica que el mensaje de éxito aparece y los nuevos valores se reflejan

### 4.4 Registrar un servidor nuevo (si tienes SQL Server)

1. Haz clic en **Nuevo Servidor** (o ve a `/servers/create`)
2. Llena el formulario:
   - Nombre: `Mi SQL Server`
   - Host: `127.0.0.1`
   - Puerto: `1433`
   - Base de datos: `IndexWatch_Test`
   - Usuario: `indexwatch_scanner`
   - Contraseña: `tu_clave`
   - Timeout: `60`
   - Umbral warning: `5`
   - Umbral crítico: `30`
3. Marca **Encrypt: Sí** y **Trust Certificate: Sí** (solo desarrollo)
4. Haz clic en **Crear**
5. Verifica que el servidor aparece en la lista

---

## Paso 5: Lista de Índices

Navega a **http://127.0.0.1:8000/indices**

### 5.1 Tabla de índices

**Debe mostrar:**

- Tabla con filas de índices (debe haber ~19)
- Columnas: Checkbox, Esquema/Tabla, Índice, Fragmentación (barra + %), Tamaño (MB), Última acción, Acción recomendada
- Colores: verde (saludable), amarillo (warning), rojo (crítico)

### 5.2 Filtros

1. Haz clic en los pills de filtro: **Todos → Críticos → Advertencia → OK**
2. La tabla debe filtrarse en tiempo real (sin recargar)
3. El contador al pie debe actualizarse: "Mostrando X de 19 índices"

### 5.3 Buscador

1. Escribe el nombre de un índice o tabla en el campo de búsqueda
2. La tabla debe filtrarse mientras escribes

### 5.4 Ordenamiento

1. Haz clic en el encabezado **Fragmentación** para ordenar ascendente
2. Haz clic de nuevo para descendente
3. Haz clic en **Tamaño** para ordenar por tamaño

### 5.5 Drawer de detalle

1. Haz clic en cualquier fila de la tabla
2. Debe abrirse un panel lateral (drawer) con:
   - Nombre de la tabla
   - Nombre del índice
   - % de fragmentación con color
   - Tamaño en MB
   - Última acción
   - Acción recomendada
3. Haz clic fuera del drawer o presiona **Escape** para cerrarlo

---

## Paso 6: Alertas en el Dashboard

Regresa a **http://127.0.0.1:8000/dashboard**

### 6.1 Lista de alertas

En la sección **Últimas alertas**, verifica:

- Hay al menos 5 alertas visibles
- Cada alerta tiene un **punto de color** (rojo = crítico, amarillo = warning, verde = OK)
- El texto describe el índice afectado, el tipo de problema y la acción recomendada
- Muestra tiempo relativo ("hace X minutos")

### 6.2 Tipos de alertas visibles

Los tipos de alerta que deberían aparecer incluyen:

- **Fragmentación** — Índice con alto % de fragmentación
- **Estadísticas obsoletas** — Estadísticas que necesitan UPDATE
- **Missing index** — Índice sugerido por DMV
- **Fill factor** — Recomendación de ajuste

---

## Paso 7: Auditoría

Navega a **http://127.0.0.1:8000/audit**

### 7.1 Tabla de auditoría

**Debe mostrar:**

- Tabla con registros (debe haber ~12)
- Columnas: Fecha/Hora, Servidor, Actor, Origen, Acción, Descripción
- Paginación al pie si hay más de 10 registros

### 7.2 Filtros

1. Selecciona una acción del dropdown **"Todas las acciones"** → elige **"approved"**
2. Haz clic en **Filtrar**
3. La tabla debe mostrar solo registros con esa acción

4. Selecciona una fuente del dropdown **"Todas las fuentes"** → elige **"webhook"**
5. Haz clic en **Filtrar**
6. Debe filtrar por fuente

7. Selecciona un rango de fechas (Desde / Hasta)
8. Haz clic en **Filtrar**

### 7.3 Paginación

1. Si hay más de 10 registros, haz clic en los números de página al pie
2. La tabla debe cargar la página correspondiente sin recargar la página completa

---

## Paso 8: Reportes

Navega a **http://127.0.0.1:8000/reports**

### 8.1 Reportes existentes

En la tabla **Reportes Generados**:

- Debe aparecer al menos 1 reporte
- Columnas: Servidor, Formato, Estado, Generado, Expira, Acción
- El estado debe ser **completed** (badge verde)

### 8.2 Solicitar un reporte nuevo

1. En el formulario **Solicitar Reporte**:
   - **Servidor**: selecciona "Todos los servidores" o "SQL Server Demo"
   - **Formato**: selecciona "HTML"
2. Haz clic en **Generar Reporte**
3. Debe aparecer un mensaje de confirmación
4. El nuevo reporte aparece en la tabla con estado **pending** o **completed**

### 8.3 Descargar reporte

1. En la tabla de reportes, busca uno con estado **completed**
2. Haz clic en **Descargar**
3. El navegador debe iniciar la descarga de un archivo `.html`
4. Abre el archivo descargado — debe contener KPIs, tablas de fragmentación, alertas y secciones

---

## Paso 9: Roles y permisos

### 9.1 Cerrar sesión y probar Viewer

1. Haz clic en tu nombre (esquina superior derecha) → **Log out**
2. Inicia sesión con:
   - Email: `viewer@indexwatch.test`
   - Password: `admin123`

3. **Verifica que puedes ver:**
   - Dashboard (`/dashboard`) — KPIs y alertas
   - Índices (`/indices`) — tabla de índices
   - Auditoría (`/audit`) — registros

4. **Verifica que NO puedes:**
   - Ir a `/servers/create` — debe mostrar error 403 o redirigir
   - Ir a `/settings` — acceso restringido

### 9.2 Probar Operator

1. Cierra sesión y entra con `operator@indexwatch.test` / `admin123`
2. Verifica acceso a dashboard, índices, auditoría, reportes
3. Verifica acceso a **Centro de Operaciones** (`/actions`)

### 9.3 Probar Admin

1. Cierra sesión y entra con `test@example.com` / `admin123`
2. Verifica que puedes acceder a **TODAS** las páginas sin restricción

---

## Paso 10: Generar un reporte y verificar su contenido

### 10.1 Desde la UI de Reportes

1. Ve a **http://127.0.0.1:8000/reports**
2. En el formulario, selecciona:
   - Servidor: "SQL Server Demo"
   - Formato: "HTML"
3. Haz clic en **Generar Reporte**
4. Espera unos segundos y recarga la página
5. Cuando el estado cambie a **completed**, haz clic en **Descargar**

### 10.2 Verificar contenido

Abre el archivo `.html` descargado. Debe contener:

| Sección | Contenido esperado |
|---------|-------------------|
| **Encabezado** | "IndexWatch - Reporte de Monitoreo", período, servidores incluidos |
| **Resumen ejecutivo** | KPIs: servidores, alertas críticas, warning, info |
| **Fragmentación** | Tabla con índices: servidor, esquema, tabla, índice, tipo, frag %, tamaño, fill factor, lecturas, escrituras |
| **Estadísticas** | Tabla con estadísticas obsoletas (si las hay) |
| **Alertas** | Tabla con tipo, severidad, estado, asunto, acción, fechas |
| **Mantenimiento** | Tabla con acciones ejecutadas |
| **Auditoría** | Tabla con actor, fuente, acción, descripción, fecha |
| **Pie de página** | "IndexWatch v2 - Reporte generado automáticamente", limitaciones |

---

## Paso 11: Probar el Webhook de WhatsApp (simulado)

> El webhook acepta peticiones sin firma HMAC en entorno `local`.

### 11.1 Verificar el webhook (GET)

Abre en tu navegador:
```
http://127.0.0.1:8000/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test&hub.challenge=12345
```

**Esperado:** La página debe mostrar `12345` (el challenge que Meta espera)

> Con un token incorrecto (`hub.verify_token=wrong`) debe mostrar `Forbidden`.

### 11.2 Ver datos actuales antes de simular

Ve al Dashboard y observa la sección de alertas. Identifica mentalmente cuántas alertas hay en estado "Pendiente".

### 11.3 Simular una aprobación vía webhook (POST)

Usa la consola del navegador (F12 → Console) o Postman para enviar:

```javascript
fetch('http://127.0.0.1:8000/api/webhook/whatsapp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        entry: [{
            changes: [{
                value: {
                    messages: [{
                        id: 'msg_test_001',
                        from: '+51999999999',
                        type: 'interactive',
                        interactive: {
                            type: 'button_reply',
                            button_reply: {
                                id: 'rebuild_1',
                                title: 'REBUILD'
                            }
                        }
                    }]
                }
            }]
        }]
    })
}).then(r => r.json()).then(console.log)
```

**Esperado:** `{"status": "ok"}`

### 11.4 Verificar idempotencia

Vuelve a ejecutar el mismo fetch (mismo `message_id`).  
**Esperado:** `{"status": "duplicate", "message_id": "msg_test_001"}`

### 11.5 Verificar que la alerta cambió de estado

1. Recarga el Dashboard
2. La alerta con ID 1 (si era `pending`) ahora debería aparecer como **Aprobada** o **Programada** en la lista de alertas
3. Ve a **Auditoría** (`/audit`) — debe aparecer un nuevo registro con acción `approved`

---

## Paso 12: Verificar rate limiting

Envía múltiples peticiones rápidamente al webhook (puedes usar la consola del navegador):

```javascript
for (let i = 0; i < 5; i++) {
    fetch('http://127.0.0.1:8000/api/webhook/whatsapp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entry: [{ changes: [{ value: {} }] }] })
    }).then(r => console.log(i, r.status))
}
```

**Esperado:** Todas deben responder `200` (el rate limit es 100/min, así que 5 peticiones no deberían activarlo).

---

## Paso 13: Verificar health check

Abre en tu navegador: **http://127.0.0.1:8000/up**

**Esperado:** Página en blanco con HTTP 200 (o un JSON `{"status":"ok"}`).

---

## Checklist final

| # | Verificación | Dónde | Qué observar |
|---|-------------|-------|-------------|
| 1 | Login funciona | `/login` | Redirige a `/dashboard` tras login exitoso |
| 2 | KPIs con datos reales | `/dashboard` | 4 tarjetas con números > 0, distintos entre sí |
| 3 | Donut actualizado | `/dashboard` | Leyenda coincide con KPIs |
| 4 | Alertas visibles | `/dashboard` | Al menos 5 alertas con severidad y tiempo relativo |
| 5 | Polling automático | `/dashboard` | Esperar 30s — los datos se refrescan sin F5 |
| 6 | Servidores en lista | `/servers` | Al menos 1 servidor con health score |
| 7 | Índices con filtros | `/indices` | Filtros (Todos/Críticos/Warning/OK) funcionan |
| 8 | Buscador de índices | `/indices` | Escribir en el buscador filtra en tiempo real |
| 9 | Drawer de índice | `/indices` | Click en fila abre panel lateral con detalles |
| 10 | Auditoría con datos | `/audit` | Tabla con registros + filtros funcionales |
| 11 | Auditoría paginada | `/audit` | Click en páginas carga sin recargar |
| 12 | Reportes listados | `/reports` | Tabla con al menos 1 reporte completado |
| 13 | Generar reporte nuevo | `/reports` | Formulario → click Generar → aparece en tabla |
| 14 | Descargar reporte | `/reports` | Click Descargar → archivo .html con KPIs + tablas |
| 15 | Webhook verify (GET) | `/api/webhook/whatsapp?...` | Challenge devuelto correctamente |
| 16 | Webhook aprueba alerta (POST) | Consola navegador | `{"status":"ok"}` → alerta cambia a "Aprobada" |
| 17 | Webhook idempotencia | Consola navegador | Mismo POST → `{"status":"duplicate"}` |
| 18 | Viewer no crea servidores | Login viewer | Sin acceso a `/servers/create` |
| 19 | Operator ve operaciones | Login operator | Acceso a `/actions` y `/audit` |
| 20 | Admin acceso total | Login admin | Acceso a todas las páginas |
| 21 | Health check | `/up` | HTTP 200 |
| 22 | Tests pasan | `php artisan test` | 54 passed, 2 skipped |

---

## Datos de acceso

| Rol | Email | Password |
|-----|-------|----------|
| Admin | `test@example.com` | `admin123` |
| Operator | `operator@indexwatch.test` | `admin123` |
| Viewer | `viewer@indexwatch.test` | `admin123` |

---

## Datos cargados en el sistema

| Entidad | Cantidad |
|---------|----------|
| Usuarios | 3 |
| Servidores | 1 (SQL Server Demo, health 72) |
| Índices | 19 |
| Alertas | 10 (7 pending, 1 approved, 1 succeeded, 1 dismissed) |
| Registros auditoría | 12 |
| Ventanas mantenimiento | 5 (días 1-5, 22:00-23:00) |
| Contactos autorizados | 2 (+51999999999, +51988888888) |
| Reportes generados | 1 |

---

## Notas

- **PDF/Excel**: El sistema genera HTML/CSV. Los paquetes `maatwebsite/excel` y `barryvdh/laravel-dompdf` se instalarán cuando soporten PHP 8.5 / Laravel 13.
- **WhatsApp real**: El webhook está listo. Agrega `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` al `.env`.
- **PostgreSQL producción**: Cambia `DB_CONNECTION=sqlite` a `pgsql` en `.env`.
- **SQL Server real**: Configúralo con `database/sqlserver/indexwatch_test_setup.sql` y registra el servidor desde `/servers/create`.