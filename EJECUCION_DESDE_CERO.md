# Guía de Ejecución de IndexWatch v2 — Desde Cero

> **Versión:** 1.0 — Julio 2026  
> **Objetivo:** Llevar el proyecto desde un equipo vacío hasta la aplicación corriendo localmente con datos de demostración.

---

## Tabla de contenidos

1. [Resumen rápido](#1-resumen-rápido)
2. [Requisitos previos](#2-requisitos-previos)
3. [Paso 0 — Instalar herramientas base](#3-paso-0--instalar-herramientas-base)
4. [Paso 1 — Obtener el código](#4-paso-1--obtener-el-código)
5. [Paso 2 — Instalar dependencias de PHP](#5-paso-2--instalar-dependencias-de-php)
6. [Paso 3 — Instalar dependencias de Node.js](#6-paso-3--instalar-dependencias-de-nodejs)
7. [Paso 4 — Configurar el entorno `.env`](#7-paso-4--configurar-el-entorno-env)
8. [Paso 5 — Crear la base de datos SQLite](#8-paso-5--crear-la-base-de-datos-sqlite)
9. [Paso 6 — Generar la clave de aplicación](#9-paso-6--generar-la-clave-de-aplicación)
10. [Paso 7 — Ejecutar migraciones y semillas](#10-paso-7--ejecutar-migraciones-y-semillas)
11. [Paso 8 — Compilar assets frontend](#11-paso-8--compilar-assets-frontend)
12. [Paso 9 — Levantar la aplicación](#12-paso-9--levantar-la-aplicación)
13. [Paso 10 — Verificar que todo funciona](#13-paso-10--verificar-que-todo-funciona)
14. [Paso 11 — Configuraciones opcionales](#14-paso-11--configuraciones-opcionales)
15. [Solución de problemas comunes](#15-solución-de-problemas-comunes)
16. [Comandos útiles de referencia](#16-comandos-útiles-de-referencia)

---

## 1. Resumen rápido

Si ya tienes todo instalado, estos son los comandos mínimos:

```powershell
# 1. Entrar al proyecto
cd D:\Proyects\indexwatch

# 2. Dependencias
composer install
npm install

# 3. Entorno
Copy-Item .env.example .env
php artisan key:generate

# 4. Base de datos SQLite
New-Item -ItemType File -Path "database\database.sqlite" -Force

# 5. Migraciones + datos de demo
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoDataSeeder

# 6. Assets
npm run build

# 7. Servidor
php artisan serve
```

Luego abre: **http://127.0.0.1:8000**

---

## 2. Requisitos previos

| Tecnología | Versión mínima | Uso |
|------------|----------------|-----|
| PHP | **8.3+** (el proyecto usa `^8.3`) | Backend Laravel |
| Composer | **2.10+** | Dependencias PHP |
| Node.js | **18+** | Dependencias frontend |
| npm | **9+** | Viene con Node.js |
| SQLite | **3.x** | Base de datos local de desarrollo |

> **Nota:** Este stack usa SQLite para desarrollo. Para producción se recomienda PostgreSQL (ver [sección 14](#14-paso-11--configuraciones-opcionales)).

---

## 3. Paso 0 — Instalar herramientas base

### 3.1 Instalar PHP 8.3+ en Windows

1. Descarga PHP para Windows: https://windows.php.net/download/
2. Elige la versión **Zip VS16+ x64 Non Thread Safe** o **Thread Safe**.
3. Descomprime en una carpeta, por ejemplo:
   ```
   C:\php\php-8.5.8-Win32-vs17-x64
   ```
4. Copia `php.ini-development` a `php.ini`.
5. Abre `php.ini` y descomenta (quita el `;` del inicio) estas extensiones:

   ```ini
   extension=curl
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=openssl
   extension=pdo_sqlite
   extension=sqlite3
   extension=xml
   extension=zip
   extension=bcmath
   ```

6. Agrega la carpeta de PHP a la variable de entorno `PATH`:
   - Presiona `Win + R`, escribe `sysdm.cpl` y presiona Enter.
   - Ve a **Opciones avanzadas → Variables de entorno**.
   - En **Variables del sistema**, busca `Path` y edítala.
   - Agrega: `C:\php\php-8.5.8-Win32-vs17-x64`

7. Verifica en una terminal nueva:
   ```powershell
   php -v
   ```
   Debe mostrar `PHP 8.x.x`.

### 3.2 Instalar Composer

1. Descarga el instalador de Windows: https://getcomposer.org/download/
2. Ejecuta `Composer-Setup.exe` y sigue las instrucciones.
3. Verifica:
   ```powershell
   composer --version
   ```

### 3.3 Instalar Node.js

1. Descarga el instalador LTS: https://nodejs.org/
2. Ejecuta el `.msi` y sigue las instrucciones.
3. Verifica:
   ```powershell
   node -v
   npm -v
   ```

---

## 4. Paso 1 — Obtener el código

Si usas Git:

```bash
git clone https://github.com/tu-org/indexwatch.git
cd indexwatch
```

Si ya tienes la carpeta del proyecto, solo ábrela en la terminal:

```powershell
cd D:\Proyects\indexwatch
```

> **Importante:** Todos los comandos siguientes se ejecutan **dentro de la carpeta del proyecto**.

---

## 5. Paso 2 — Instalar dependencias de PHP

```powershell
composer install
```

Este comando lee `composer.json` y descarga Laravel, Sanctum, PHPUnit y el resto de paquetes PHP en la carpeta `vendor/`.

Si ves errores de memoria, ejecuta:

```powershell
composer install --no-dev --optimize-autoloader
```

> **Tiempo estimado:** 2-5 minutos dependiendo de la conexión.

---

## 6. Paso 3 — Instalar dependencias de Node.js

```powershell
npm install
```

Este comando descarga Vite, Tailwind CSS, AlpineJS y demás paquetes frontend en `node_modules/`.

> **Tiempo estimado:** 1-3 minutos.

---

## 7. Paso 4 — Configurar el entorno `.env`

El archivo `.env` guarda la configuración local del proyecto. Nunca lo subas a Git.

### 7.1 Crear el archivo desde el ejemplo

```powershell
Copy-Item .env.example .env
```

### 7.2 Configuración mínima para desarrollo local

Abre `.env` con tu editor favorito y asegúrate de que tenga al menos estos valores:

```env
APP_NAME=IndexWatch
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
```

> **No toques** `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID`, etc., a menos que vayas a probar WhatsApp real (ver sección 14.3).

---

## 8. Paso 5 — Crear la base de datos SQLite

```powershell
New-Item -ItemType File -Path "database\database.sqlite" -Force
```

En Linux/macOS:

```bash
touch database/database.sqlite
```

Esto crea un archivo vacío que Laravel usará como base de datos.

---

## 9. Paso 6 — Generar la clave de aplicación

```powershell
php artisan key:generate
```

Esto genera un valor aleatorio en `APP_KEY` del `.env` y sirve para encriptar sesiones y datos sensibles.

---

## 10. Paso 7 — Ejecutar migraciones y semillas

### 10.1 Crear las tablas

```powershell
php artisan migrate
```

Verás una lista de migraciones ejecutándose: `users`, `servers`, `sql_indexes`, `alerts`, `audit_logs`, etc.

### 10.2 Cargar datos de demostración

El proyecto trae dos seeders importantes:

| Seeder | Qué hace |
|--------|----------|
| `DatabaseSeeder` | Crea el usuario `test@example.com` |
| `DemoDataSeeder` | Crea usuarios con roles, servidor demo, 19 índices, 10 alertas, auditoría, ventanas y contactos |

Ejecuta:

```powershell
php artisan db:seed --class=DatabaseSeeder
php artisan db:seed --class=DemoDataSeeder
```

O, si quieres borrar todo y empezar de cero:

```powershell
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoDataSeeder
```

> **Salida esperada:** verás `=== DEMO DATA READY ===` con los datos creados.

---

## 11. Paso 8 — Compilar assets frontend

Para generar los archivos CSS/JS que usa la interfaz web:

```powershell
npm run build
```

Salida esperada similar a:

```
vite v8.1.4 building client environment for production...
public/build/manifest.json
public/build/assets/app-*.css
public/build/assets/app-*.js
✓ built in 1.42s
```

> **Para desarrollo con recarga automática** usa `npm run dev` en lugar de `npm run build`.

---

## 12. Paso 9 — Levantar la aplicación

### 12.1 Solo el servidor web

```powershell
php artisan serve
```

La aplicación estará disponible en: **http://127.0.0.1:8000**

### 12.2 Servidor + cola + logs + Vite al mismo tiempo (recomendado)

El proyecto incluye un script de Composer que levanta todo:

```powershell
composer dev
```

Esto abre 4 procesos simultáneos:
- Servidor PHP (`php artisan serve`)
- Worker de colas (`php artisan queue:listen`)
- Logger de Laravel (`php artisan pail`)
- Servidor de desarrollo Vite (`npm run dev`)

> Si usas `composer dev`, no necesitas correr `npm run build` aparte.

### 12.3 Levantar el worker de colas por separado

Si usas `php artisan serve` solo, abre otra terminal y ejecuta:

```powershell
php artisan queue:work --queue=scans --tries=3 --timeout=300
```

Esto procesa los escaneos de SQL Server y los jobs de mantenimiento/reportes.

---

## 13. Paso 10 — Verificar que todo funciona

### 13.1 Abrir la aplicación

Navega a **http://127.0.0.1:8000**.

Debería redirigirte a `/login`.

### 13.2 Iniciar sesión

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | `test@example.com` | `admin123` |
| Operator | `operator@indexwatch.test` | `admin123` |
| Viewer | `viewer@indexwatch.test` | `admin123` |

### 13.3 Verificar páginas principales

- **Dashboard:** `/dashboard` → KPIs, alertas, gráfico de anillo
- **Servidores:** `/servers` → tabla con "SQL Server Demo"
- **Índices:** `/indices` → tabla con ~19 índices
- **Centro de Operaciones:** `/actions`
- **Auditoría:** `/audit` → ~12 registros
- **Reportes:** `/reports` → al menos 1 reporte completado
- **Health check:** `/up` → debe devolver HTTP 200

### 13.4 Ejecutar tests

```powershell
php artisan test
```

**Resultado esperado:**

```
{"tool":"phpunit","result":"passed","tests":107,"passed":105,"assertions":239,"duration_ms":3227,"skipped":2}
```

Los 2 tests skipped requieren `pdo_sqlsrv` para conectar con SQL Server real.

---

## 14. Paso 11 — Configuraciones opcionales

### 14.1 Usar PostgreSQL en lugar de SQLite

1. Instala PostgreSQL: https://www.postgresql.org/download/
2. Crea la base de datos y un usuario:

   ```sql
   CREATE USER indexwatch_user WITH PASSWORD 'tu_contraseña_segura';
   CREATE DATABASE indexwatch_db;
   GRANT ALL PRIVILEGES ON DATABASE indexwatch_db TO indexwatch_user;
   ```

3. Edita `.env`:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=indexwatch_db
   DB_USERNAME=indexwatch_user
   DB_PASSWORD=tu_contraseña_segura
   ```

4. Activa la extensión `pdo_pgsql` en `php.ini`:

   ```ini
   extension=pdo_pgsql
   ```

5. Ejecuta migraciones y seeders de nuevo.

### 14.2 Conectar con SQL Server real

1. Asegúrate de tener las extensiones `sqlsrv` y `pdo_sqlsrv` instaladas.
2. Crea un usuario de solo lectura en SQL Server para escaneos DMV.
3. Registra el servidor desde `/servers/create` con host, puerto, base de datos y credenciales.
4. Ejecuta:

   ```powershell
   php artisan indexwatch:scan --server=1 --sync
   ```

### 14.3 Configurar WhatsApp Business API

Para probar notificaciones reales por WhatsApp, sigue la guía completa:

📄 [`GUIA_CONFIGURACION_WHATSAPP.md`](./GUIA_CONFIGURACION_WHATSAPP.md)

Resumen rápido:

1. Crea una app en [Meta for Developers](https://developers.facebook.com/).
2. Agrega el producto WhatsApp y copia:
   - `WHATSAPP_TOKEN`
   - `WHATSAPP_PHONE_ID`
   - `WHATSAPP_APP_SECRET`
3. En `.env` agrega:

   ```env
   WHATSAPP_TOKEN=tu_token
   WHATSAPP_PHONE_ID=tu_phone_id
   WHATSAPP_VERIFY_TOKEN=token_inventado_por_ti
   WHATSAPP_APP_SECRET=tu_app_secret
   ```

4. Expón tu local con [ngrok](https://ngrok.com/):

   ```powershell
   ngrok http 8000 --response-header-add "ngrok-skip-browser-warning:true"
   ```

5. Configura la URL de webhook en Meta:

   ```
   https://tu-url-ngrok.ngrok-free.dev/api/webhook/whatsapp
   ```

---

## 15. Solución de problemas comunes

### Error: `"Could not find driver"` o `"Database file does not exist"`

- Verifica que `extension=pdo_sqlite` esté descomentado en `php.ini`.
- Asegúrate de que el archivo `database/database.sqlite` exista.
- Reinicia la terminal después de cambiar `php.ini`.

### Error: `"No application encryption key has been specified"`

```powershell
php artisan key:generate
```

### Error: `"SQLSTATE[HY000] [2002] Connection refused"` (PostgreSQL)

- Asegúrate de que el servicio de PostgreSQL esté corriendo.
- Verifica host, puerto, usuario y contraseña en `.env`.

### Error: `"Class '...' not found"`

```powershell
composer dump-autoload
```

### Error: CSS/JS no se cargan o la página se ve en blanco

```powershell
npm run build
```

Si usas ngrok, actualiza `APP_URL` a tu URL HTTPS y fuerza el esquema en `AppServiceProvider` (ver [`GUIA_CONFIGURACION_WHATSAPP.md`](./GUIA_CONFIGURACION_WHATSAPP.md)).

### Error: `cURL error 60: SSL certificate problem`

1. Descarga https://curl.se/ca/cacert.pem
2. Guárdalo en la carpeta de PHP (`C:\php\...\cacert.pem`).
3. En `php.ini` configura:

   ```ini
   curl.cainfo = "C:\php\...\cacert.pem"
   openssl.cafile = "C:\php\...\cacert.pem"
   ```

### Error al compilar assets con Tailwind

```powershell
rm -Recurse -Force node_modules
npm install
npm run build
```

---

## 16. Comandos útiles de referencia

| Comando | Descripción |
|---------|-------------|
| `php artisan serve` | Levanta servidor de desarrollo |
| `php artisan queue:work` | Procesa jobs en cola |
| `php artisan migrate` | Ejecuta migraciones pendientes |
| `php artisan migrate:fresh --seed` | Borra BD, recrea tablas y semilla datos |
| `php artisan db:seed --class=DemoDataSeeder` | Carga datos de demostración |
| `php artisan test` | Ejecuta la suite de tests |
| `php artisan route:list` | Lista todas las rutas |
| `php artisan config:clear` | Limpia caché de configuración |
| `php artisan cache:clear` | Limpia caché de aplicación |
| `php artisan view:clear` | Limpia caché de vistas |
| `php artisan indexwatch:scan` | Escanea todos los servidores activos en cola |
| `php artisan indexwatch:scan --sync` | Escanea todos los servidores de forma síncrona |
| `php artisan indexwatch:scan --server=1 --sync` | Escanea solo el servidor con ID 1 |
| `php artisan indexwatch:verify 1` | Verifica conectividad con el servidor 1 |
| `npm run build` | Compila assets para producción |
| `npm run dev` | Compila assets en modo desarrollo con hot reload |
| `composer dev` | Levanta servidor, cola, logs y Vite juntos |

---

## Checklist final

- [ ] PHP 8.3+ instalado con extensiones necesarias
- [ ] Composer instalado
- [ ] Node.js 18+ instalado
- [ ] `.env` creado desde `.env.example`
- [ ] `php artisan key:generate` ejecutado
- [ ] `database/database.sqlite` creado (modo dev)
- [ ] `composer install` completado
- [ ] `npm install` completado
- [ ] `php artisan migrate:fresh --seed` ejecutado
- [ ] `php artisan db:seed --class=DemoDataSeeder` ejecutado
- [ ] `npm run build` o `npm run dev` ejecutado
- [ ] `php artisan serve` o `composer dev` corriendo
- [ ] Login funciona con `test@example.com` / `admin123`
- [ ] `php artisan test` pasa (105 passed, 2 skipped)

---

**¡Listo!** Si completaste todos los pasos, IndexWatch v2 debería estar corriendo en tu máquina local.
