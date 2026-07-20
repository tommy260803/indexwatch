# Guía de Configuración: Integración WhatsApp Business API — IndexWatch

Esta guía documenta el procedimiento completo para conectar el proyecto IndexWatch con WhatsApp Business API (Meta), probarlo en un entorno local de desarrollo, y los problemas más comunes que van a encontrar (junto con su solución).

**Autor:** Integrante 4 (Notificaciones WhatsApp / Ejecución de mantenimiento)
**Última actualización:** Julio 2026

---

## Índice

1. [Requisitos previos](#1-requisitos-previos)
2. [Crear la app en Meta for Developers](#2-crear-la-app-en-meta-for-developers)
3. [Configurar el producto WhatsApp](#3-configurar-el-producto-whatsapp)
4. [Configurar las variables de entorno](#4-configurar-las-variables-de-entorno)
5. [Exponer el servidor local con ngrok](#5-exponer-el-servidor-local-con-ngrok)
6. [Configurar el webhook en Meta](#6-configurar-el-webhook-en-meta)
7. [Probar el flujo completo](#7-probar-el-flujo-completo)
8. [Problemas comunes y soluciones](#8-problemas-comunes-y-soluciones)
9. [Checklist rápido antes de cada demo](#9-checklist-rápido-antes-de-cada-demo)
10. [Limitaciones conocidas (cuenta de prueba)](#10-limitaciones-conocidas-cuenta-de-prueba)

---

## 1. Requisitos previos

- Cuenta de Facebook personal (para crear la cuenta de Meta Developer)
- Un número de WhatsApp propio para recibir mensajes de prueba
- PHP 8.3+ corriendo localmente (el proyecto requiere `^8.3` en `composer.json`)
- Proyecto Laravel funcionando con `php artisan serve`
- ngrok instalado (ver sección 5)

---

## 2. Crear la app en Meta for Developers

1. Entra a **https://developers.facebook.com/**
2. Inicia sesión con tu cuenta de Facebook
3. Click en **"Mis apps"** → **"Crear app"**
4. Tipo de app: **"Otro"** → **"Empresa"**
5. Ponle un nombre (ej. `IndexWatch Demo`)
6. Si no tienes un "portfolio comercial" (Business Portfolio), créalo ahí mismo con cualquier nombre — no requiere verificación para las pruebas
7. Confirma y crea la app

Al final de este proceso vas a tener un **ID de app** (App ID), visible en la URL del panel: `developers.facebook.com/apps/TU_APP_ID/...`

---

## 3. Configurar el producto WhatsApp

1. Dentro del panel de tu app, agrega el caso de uso **"Conectarte con los clientes a través de WhatsApp"**
2. Selecciona tu portfolio comercial y continúa
3. Ve a **"Prueba la API"** (Test the API)

Ahí Meta te asigna automáticamente y de forma gratuita:

| Dato | Dónde se usa |
|---|---|
| **Número de prueba** (ej. `+1 555 XXX XXXX`) | Es el número desde el que llegan los mensajes |
| **Phone Number ID** | Va en `.env` como `WHATSAPP_PHONE_ID` |
| **Token de acceso** (dura 24h) | Va en `.env` como `WHATSAPP_TOKEN` |
| **WhatsApp Business Account ID** | Informativo, no se usa directamente en el código |

### Agregar tu número como destinatario de prueba

En la misma pantalla, en la sección **"Destinatario"**, agrega tu número personal de WhatsApp (con código de país, sin `+` ni espacios al guardarlo en el `.env`, ej. `51987654321`). Meta lo verifica al instante, sin código SMS en la mayoría de los casos.

### Envía un mensaje de prueba desde la consola

Antes de tocar código, usa el botón de enviar mensaje que aparece en esa misma consola, para confirmar que las credenciales funcionan y que tu número está bien vinculado.

### Consigue el App Secret

Ve a **Configuración → Básica** (`/apps/TU_APP_ID/settings/basic/`) y copia el campo **"Clave secreta de la app"** (App Secret), dando click en "Mostrar" (pide tu contraseña de Facebook). Este valor se usa para validar la firma HMAC del webhook — **es distinto** al token de acceso.

---

## 4. Configurar las variables de entorno

En el `.env` del proyecto, agrega:

```env
WHATSAPP_TOKEN=el_token_de_acceso_de_meta
WHATSAPP_PHONE_ID=el_phone_number_id
WHATSAPP_TO=tu_numero_verificado_sin_signos
WHATSAPP_VERIFY_TOKEN=cualquier_palabra_que_inventes
WHATSAPP_APP_SECRET=el_app_secret_de_meta
```

### Nota sobre `WHATSAPP_VERIFY_TOKEN`

A diferencia de los otros valores, **este no lo genera Meta** — lo inventas tú. Sirve únicamente para el "handshake" de verificación cuando configuras la URL del webhook (sección 6). Debe coincidir exactamente entre tu `.env` y lo que escribas en el panel de Meta.

### Confirma que `config/services.php` incluya el `app_secret`

```php
'whatsapp' => [
    'token'        => env('WHATSAPP_TOKEN'),
    'phone_id'     => env('WHATSAPP_PHONE_ID'),
    'to'           => env('WHATSAPP_TO'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret'   => env('WHATSAPP_APP_SECRET'),
],
```

Después de cualquier cambio en `.env`, limpia la caché de configuración:

```bash
php artisan config:clear
```

---

## 5. Exponer el servidor local con ngrok

Meta necesita una URL pública HTTPS para llamar a tu webhook. `localhost` no es accesible desde internet, así que usamos **ngrok** para crear un túnel temporal.

### Instalación

1. Descarga ngrok desde **https://ngrok.com/download**
2. Descomprime en una carpeta, ej. `C:\ngrok`
3. Crea una cuenta gratuita en **https://dashboard.ngrok.com/signup**
4. Copia tu authtoken desde **https://dashboard.ngrok.com/get-started/your-authtoken**
5. Configúralo:
   ```bash
   cd C:\ngrok
   .\ngrok config add-authtoken TU_TOKEN_REAL
   ```

### Levantar el túnel

Con `php artisan serve` corriendo (puerto 8000), en otra terminal:

```bash
cd C:\ngrok
.\ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"
```

Esto te da una URL pública, ej. `https://barista-clone-anemia.ngrok-free.dev`.

> **Nota:** con cuenta gratuita, ngrok puede asignar un dominio fijo que no cambia entre reinicios (a diferencia de lo que se documenta en otros tutoriales). Confirma tu URL cada vez que reinicies revisando la línea `Forwarding` en la consola.

### IMPORTANTE: la bandera `--response-header-add`

Ngrok gratuito muestra una **página de advertencia de seguridad** a cualquier visitante que no parezca un navegador (esto incluye las peticiones automáticas de Meta). Sin la bandera de arriba, **Meta nunca podrá verificar ni llamar tu webhook**, aunque todo lo demás esté bien configurado. Ver sección 8 para más detalle.

### Actualiza el `.env` con la URL de ngrok

```env
APP_URL=https://tu-url-de-ngrok.ngrok-free.dev
```

Y limpia cachés:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Fuerza HTTPS en las URLs generadas por Laravel

Agrega esto en `app/Providers/AppServiceProvider.php`, dentro del método `boot()`:

```php
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    if (str_starts_with(config('app.url'), 'https://')) {
        URL::forceScheme('https');
    }
}
```

Sin esto, Laravel genera los links de CSS/JS con `http://` aunque accedas por `https://`, y el navegador los bloquea por "Mixed Content" (ver sección 8).

---

## 6. Configurar el webhook en Meta

1. Ve a: `https://developers.facebook.com/apps/TU_APP_ID/whatsapp-business/wa-configurations-v2/`
2. Busca la sección **"Configurar webhooks"**
3. Completa:
   - **URL de devolución de llamada:** `https://tu-url-de-ngrok.ngrok-free.dev/v1/channels/whatsapp/webhook`
   - **Token de verificación:** el mismo valor que pusiste en `WHATSAPP_VERIFY_TOKEN`
4. Click en **"Verificar y guardar"**
5. Suscríbete al campo **`messages`** en la lista de "Webhook fields"

### Requisito de código: debe existir la ruta GET

En `routes/web.php` (o `api.php`, según cómo esté organizado tu proyecto):

```php
Route::get('/v1/channels/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/v1/channels/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
```

El método `verify()` debe comparar el `hub_verify_token` recibido contra `config('services.whatsapp.verify_token')` y devolver el `hub_challenge` tal cual si coincide.

---

## 7. Probar el flujo completo

### Paso A: abre la ventana de conversación

WhatsApp Business API solo permite mandar mensajes libres/interactivos (no plantillas) a un número si esa persona **te escribió primero** en las últimas 24 horas. Desde tu celular, mándale cualquier mensaje (ej. "Hola") al número de prueba de Meta.

### Paso B: envía la alerta de prueba

Con 3 terminales corriendo simultáneamente:

**Terminal 1** — servidor:
```bash
php artisan serve
```

**Terminal 2** — worker de colas:
```bash
php artisan queue:work
```

**Terminal 3** — túnel:
```bash
cd C:\ngrok
.\ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"
```

**Terminal 4** — dispara el envío:
```bash
php artisan tinker
```
```php
$whatsapp = app(App\Services\WhatsAppService::class);
$whatsapp->sendAlertWithButtons('tu_numero', [
    'alert_id'   => 1,
    'index_name' => 'IX_Ejemplo',
    'table_name' => 'TablaEjemplo',
    'problem'    => 'Fragmentación del 85% (Crítico)',
    'size_mb'    => 250,
]);
```

Deberías recibir el mensaje con botones en tu celular en segundos.

### Paso C: cierra el ciclo

Toca uno de los botones desde tu WhatsApp real. Esto dispara: Meta → tu webhook (vía ngrok) → validación de firma → `ExecuteMaintenanceJob` en cola → procesamiento por el worker → mensaje de confirmación de vuelta a tu celular.

---

## 8. Problemas comunes y soluciones

### "Mixed Content: ... insecure stylesheet/script"
**Causa:** `APP_URL` en `.env` seguía en `http://localhost` mientras se accedía por `https://` (ngrok).
**Solución:** actualizar `APP_URL` a la URL de ngrok con `https://`, y forzar el esquema HTTPS en `AppServiceProvider` (ver sección 5).

### `cURL error 60: SSL certificate ... unable to get local issuer certificate`
**Causa:** PHP en Windows no trae, por defecto, un paquete de certificados raíz (CA bundle) configurado para verificar conexiones HTTPS salientes.
**Solución:**
1. Descarga `https://curl.se/ca/cacert.pem` y guárdalo en la carpeta de tu PHP (ej. `C:\php85\cacert.pem`). **Verifica que sea un archivo real (~180-300 KB), no una carpeta vacía** — puede pasar si la descarga se hace mal desde PowerShell.
2. En `php.ini`, descomenta y configura:
   ```ini
   curl.cainfo = "C:\php85\cacert.pem"
   openssl.cafile = "C:\php85\cacert.pem"
   ```
3. Confirma con: `php -i | findstr "cainfo cafile"`

### La página se ve en blanco al entrar por la URL de ngrok
Ver "Mixed Content" arriba — casi siempre es la causa.

### `ERR_NGROK_6024` / página de advertencia de ngrok en vez de la respuesta esperada
**Causa:** ngrok gratuito muestra una pantalla de advertencia a visitantes que no parecen navegadores (incluye llamadas automáticas de Meta y `curl`/PowerShell).
**Solución:** levantar ngrok con la bandera:
```bash
.\ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"
```

### `{"error":{"message":"Authentication Error","code":190,"type":"OAuthException"}}`
**Causa:** el token de acceso temporal de la cuenta de prueba **vence cada 24 horas**.
**Solución:** genera un token nuevo en la consola de WhatsApp de Meta y actualízalo en `.env`, luego `php artisan config:clear`.

### El mensaje con botones no llega, pero sí llegó un mensaje de "confirmación de compra"
**Causa:** no existe una ventana de conversación abierta (no le escribiste tú primero al número en las últimas 24h). Meta acepta la petición (te da un `message id`) pero no entrega el mensaje libre/interactivo.
**Solución:** escríbele cualquier mensaje al número de prueba desde tu celular antes de reintentar.

### El webhook nunca recibe nada al tocar los botones
Verifica en este orden:
1. ¿Existe la ruta `GET` para `verify` además del `POST` para `handle`? (`php artisan route:list --path=whatsapp`)
2. ¿La Callback URL en Meta coincide exactamente con tu URL de ngrok actual?
3. ¿Está suscrito el campo `messages` en la configuración del webhook?
4. ¿ngrok sigue corriendo con la bandera `--response-header-add`?
5. Revisa `http://127.0.0.1:4040` (panel de ngrok) para ver si la petición llegó y qué respondió tu servidor.

### Cuidado con carpetas de proyecto duplicadas
Si tienes más de una copia del proyecto en tu PC (por ejemplo, un clon de git en una ruta y una copia descargada en otra), es fácil terminar ejecutando comandos en la carpeta equivocada sin darte cuenta — los síntomas son variables `null`, `.env` "vacío" que en realidad sí tiene datos (en la otra carpeta), etc. Verifica siempre con `pwd` antes de comandos importantes, y considera eliminar copias que ya no necesites.

---

## 9. Checklist rápido antes de cada demo

Como el token de acceso y la ventana de conversación vencen, repite esto **el mismo día** de cualquier demo:

- [ ] Generar un token de acceso nuevo en la consola de Meta y actualizar `WHATSAPP_TOKEN` en `.env`
- [ ] `php artisan config:clear`
- [ ] Escribirle "Hola" al número de prueba desde tu WhatsApp personal
- [ ] Levantar `php artisan serve`
- [ ] Levantar `php artisan queue:work`
- [ ] Levantar `ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"`
- [ ] Confirmar que la URL de ngrok coincide con la configurada como Callback URL en Meta (si cambió, actualizarla ahí también, y en `APP_URL` del `.env`)
- [ ] Probar el envío desde `tinker` antes de la demo en vivo

---

## 10. Limitaciones conocidas (cuenta de prueba)

- **Token de acceso temporal:** vence cada 24 horas. Para producción real se necesita un token permanente (System User Token) y verificación de negocio.
- **Solo 5 números destinatarios:** la cuenta de prueba gratuita limita a 5 números verificados manualmente.
- **App sin publicar:** mientras la app no esté publicada ante Meta, algunos webhooks de producción reales pueden no entregarse de forma completa — para la demo académica esto no es necesario resolverlo, pero es relevante mencionarlo si el proyecto avanza a un entorno real con clientes.
- **ngrok gratuito:** el túnel y su URL dependen de que la terminal quede abierta; se recomienda para pruebas, no para un entorno persistente. En producción real esto se reemplaza por un dominio propio con SSL válido, sin necesidad de ngrok ni de la bandera de "skip browser warning".
