# Niveles de Configuración para Probar IndexWatch v2

> **Versión:** 1.0 — Julio 2026  
> **Objetivo:** Elegir qué tan lejos quieres llegar probando el sistema: datos demo, SQL Server real o WhatsApp real.

---

## Tabla de contenidos

1. [Resumen de niveles](#1-resumen-de-niveles)
2. [Nivel 1 — Datos demo](#2-nivel-1--datos-demo)
3. [Nivel 2 — SQL Server real](#3-nivel-2--sql-server-real)
4. [Nivel 3 — WhatsApp Business API real](#4-nivel-3--whatsapp-business-api-real)
5. [¿Por dónde empezar?](#5-por-dónde-empezar)
6. [Checklist por nivel](#6-checklist-por-nivel)

---

## 1. Resumen de niveles

| Nivel | Nombre | Requiere instalar | Qué puedes probar |
|-------|--------|-------------------|-------------------|
| **1** | Datos demo | Nada extra | UI, roles, dashboard, reportes, webhook simulado |
| **2** | SQL Server real | SQL Server + SSMS | Escaneo real de índices, alertas reales, health score |
| **3** | WhatsApp real | Cuenta Meta + ngrok | Notificaciones por WhatsApp, aprobación con botones |

> **Recomendación:** Empieza por el **Nivel 1**, luego sube al **Nivel 2** y finalmente al **Nivel 3**.

---

## 2. Nivel 1 — Datos demo

### Descripción
El proyecto ya trae datos de demostración cargados. No necesitas SQL Server ni WhatsApp reales.

### Requisitos
- Tener el proyecto corriendo en `http://127.0.0.1:8000`

### Datos de acceso

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | `test@example.com` | `admin123` |
| Operator | `operator@indexwatch.test` | `admin123` |
| Viewer | `viewer@indexwatch.test` | `admin123` |

### Qué probar

1. **Login y roles**
   - Entra con cada usuario.
   - Verifica que el `viewer` no pueda crear servidores.
   - Verifica que el `operator` pueda ver el Centro de Operaciones.

2. **Dashboard**
   - KPIs con datos reales.
   - Gráfico de anillo.
   - Lista de alertas.
   - Polling automático cada 30 segundos.

3. **Índices**
   - Filtros: Todos / Críticos / Advertencia / OK.
   - Buscador en tiempo real.
   - Ordenamiento por fragmentación o tamaño.
   - Drawer de detalle al hacer clic en una fila.

4. **Auditoría**
   - Filtros por acción, fuente y rango de fechas.
   - Paginación.

5. **Reportes**
   - Generar un reporte HTML.
   - Descargarlo y abrirlo.

6. **Webhook simulado**
   - Abre la consola del navegador (F12).
   - Envía una aprobación simulada:

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
                               button_reply: { id: 'rebuild_1', title: 'REBUILD' }
                           }
                       }]
                   }
               }]
           }]
       })
   }).then(r => r.json()).then(console.log)
   ```

   - Verifica que la respuesta sea `{"status":"ok"}`.
   - Recarga el dashboard y revisa que la alerta cambie de estado.

### Guía completa

📄 [`GUIA_PRUEBAS_END_TO_END.md`](./GUIA_PRUEBAS_END_TO_END.md)

---

## 3. Nivel 2 — SQL Server real

### Descripción
Conectas una instancia real de SQL Server para que IndexWatch escanee índices, fragmentación, estadísticas y missing indexes reales.

### Requisitos
- SQL Server instalado (local o en red).
- SQL Server Management Studio (SSMS).
- Acceso administrador a la instancia para crear el usuario scanner.

### Paso 1 — Preparar SQL Server

1. Abre `database/sqlserver/indexwatch_test_setup.sql` en SSMS.
2. Activa **Consulta > Modo SQLCMD**.
3. Reemplaza `CHANGE_ME_WITH_A_STRONG_LOCAL_PASSWORD` por una clave local fuerte.
4. Ejecuta el script conectado como administrador.

El script crea:
- Base de datos `IndexWatch_Test`.
- Login `indexwatch_scanner` (solo lectura).
- Permisos para leer DMV y metadata.
- Tabla de prueba con 100,000 filas y dos índices.

### Paso 2 — Habilitar TCP/IP (si falla la conexión)

1. Abre **SQL Server Configuration Manager**.
2. Ve a **Configuración de red de SQL Server > Protocolos para TU_INSTANCIA**.
3. Habilita **TCP/IP**.
4. En propiedades de TCP/IP, pestaña **Direcciones IP**, fija el puerto `1433` en **IPAll**.
5. Reinicia el servicio de SQL Server.

### Paso 3 — Registrar el servidor en IndexWatch

Ve a **http://127.0.0.1:8000/servers/create** y completa:

| Campo | Valor local sugerido |
|-------|----------------------|
| Nombre | SQL Server local de pruebas |
| Host | `127.0.0.1` o nombre de tu instancia (ej. `ANTHONY`) |
| Puerto | `1433` |
| Base de datos | `IndexWatch_Test` |
| Usuario | `indexwatch_scanner` |
| Contraseña | La que pusiste en el script |
| Encrypt | Sí |
| Trust server certificate | Sí, solo para desarrollo local |
| Timeout | `60` |
| Minimum index pages | `1000` |

### Paso 4 — Verificar conectividad

```powershell
php artisan indexwatch:verify 1
```

> Reemplaza `1` por el ID que se muestra en la lista de servidores.

### Paso 5 — Escanear

Síncrono (recomendado para la primera prueba):

```powershell
php artisan indexwatch:scan --server=1 --sync
```

Asíncrono (usa la cola):

```powershell
php artisan indexwatch:scan --server=1
```

Y en otra terminal:

```powershell
php artisan queue:work --queue=scans,default --tries=3 --timeout=300
```

### Paso 6 — Verificar resultados

- Ve al dashboard y revisa que los KPIs reflejen datos reales.
- Ve a **Índices** y comprueba que aparezcan los índices de `IndexWatch_Test`.
- Ve a **Servidores** y revisa el health score.

### Guía completa

📄 [`docs/INTEGRANTE_2_RUNBOOK.md`](./docs/INTEGRANTE_2_RUNBOOK.md)

---

## 4. Nivel 3 — WhatsApp Business API real

### Descripción
Conectas la API oficial de WhatsApp Business para enviar alertas con botones y aprobar mantenimiento desde tu celular.

### Requisitos
- Cuenta de Facebook personal.
- Número de celular propio para recibir mensajes de prueba.
- ngrok instalado.

### Paso 1 — Crear app en Meta

1. Entra a https://developers.facebook.com/.
2. Inicia sesión y crea una app tipo **Empresa**.
3. Agrega el caso de uso **WhatsApp**.

### Paso 2 — Obtener credenciales

En el panel de WhatsApp de tu app copia:

| Dato | Variable `.env` |
|------|-----------------|
| Token de acceso temporal | `WHATSAPP_TOKEN` |
| Phone Number ID | `WHATSAPP_PHONE_ID` |
| App Secret | `WHATSAPP_APP_SECRET` |

> El `WHATSAPP_VERIFY_TOKEN` lo inventas tú; sirve para el handshake del webhook.

### Paso 3 — Configurar `.env`

```env
WHATSAPP_TOKEN=tu_token_de_meta
WHATSAPP_PHONE_ID=tu_phone_id
WHATSAPP_VERIFY_TOKEN=cualquier_palabra_segura
WHATSAPP_APP_SECRET=tu_app_secret
```

Luego limpia caché:

```powershell
php artisan config:clear
```

### Paso 4 — Exponer tu local con ngrok

```powershell
ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"
```

Copia la URL HTTPS que te da, por ejemplo:

```
https://tu-url.ngrok-free.dev
```

Actualiza `APP_URL` en `.env`:

```env
APP_URL=https://tu-url.ngrok-free.dev
```

### Paso 5 — Configurar webhook en Meta

1. Ve a la configuración de webhooks de WhatsApp.
2. Callback URL:

   ```
   https://tu-url.ngrok-free.dev/api/webhook/whatsapp
   ```

3. Verify token: el mismo valor de `WHATSAPP_VERIFY_TOKEN`.
4. Suscríbete al campo `messages`.

### Paso 6 — Probar el flujo

1. Desde tu celular, escribe "Hola" al número de prueba de Meta.
2. En IndexWatch, genera una alerta y envíala:

   ```powershell
   php artisan tinker
   ```

   ```php
   $whatsapp = app(App\Services\WhatsAppService::class);
   $whatsapp->sendAlertWithButtons('+51987654321', [
       'alert_id'   => 1,
       'index_name' => 'IX_Ejemplo',
       'table_name' => 'TablaEjemplo',
       'problem'    => 'Fragmentación del 85% (Crítico)',
       'size_mb'    => 250,
   ]);
   ```

3. Recibe el mensaje en tu celular y toca un botón.
4. El webhook recibe la respuesta, ejecuta el mantenimiento y te notifica el resultado.

### Guía completa

📄 [`GUIA_CONFIGURACION_WHATSAPP.md`](./GUIA_CONFIGURACION_WHATSAPP.md)

---

## 5. ¿Por dónde empezar?

### Si solo quieres ver la app funcionar
→ **Nivel 1**. Usa los datos demo y la guía de pruebas end-to-end.

### Si quieres ver escaneos reales de índices
→ **Nivel 2**. Instala SQL Server y ejecuta el script de setup.

### Si quieres la experiencia completa (alertas + aprobación desde celular)
→ **Nivel 3**. Configura Meta, ngrok y WhatsApp.

### Si quieres todo
Sigue el orden: **Nivel 1 → Nivel 2 → Nivel 3**.

---

## 6. Checklist por nivel

### Nivel 1 — Datos demo
- [ ] Proyecto corriendo en `http://127.0.0.1:8000`
- [ ] Login con `test@example.com` / `admin123`
- [ ] Dashboard con KPIs y alertas
- [ ] Filtros y búsqueda en `/indices`
- [ ] Generar y descargar un reporte
- [ ] Simular webhook desde consola del navegador

### Nivel 2 — SQL Server real
- [ ] SQL Server instalado
- [ ] Script `indexwatch_test_setup.sql` ejecutado
- [ ] TCP/IP habilitado y puerto 1433 abierto
- [ ] Servidor registrado en `/servers/create`
- [ ] `php artisan indexwatch:verify 1` devuelve OK
- [ ] `php artisan indexwatch:scan --server=1 --sync` completa sin errores
- [ ] Índices reales visibles en el dashboard

### Nivel 3 — WhatsApp real
- [ ] App creada en Meta for Developers
- [ ] Credenciales copiadas al `.env`
- [ ] ngrok instalado y autenticado
- [ ] Túnel ngrok corriendo
- [ ] Webhook configurado y verificado en Meta
- [ ] Número de celular agregado como destinatario de prueba
- [ ] Mensaje de prueba enviado y recibido
- [ ] Botón tocado y mantenimiento ejecutado

---

**¿Decidiste por cuál nivel empezar?** Avísame y te acompaño paso a paso.
