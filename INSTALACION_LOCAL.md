# Guía de Instalación Local - IndexWatch

## Requisitos Previos
- **PHP 8.3 o superior**
- **Composer** (gestor de dependencias de PHP)
- **Node.js y npm** (para assets frontend)
- **PostgreSQL** (versión 12 o superior recomendada)
- **Extensiones PHP necesarias**:
  - `pdo_pgsql` (para PostgreSQL)
  - `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `ctype`, `json`, `tokenizer`

---

## Paso 1: Instalar y Configurar PostgreSQL

### Instalación en Windows
1. Descarga PostgreSQL desde [postgresql.org](https://www.postgresql.org/download/windows/)
2. Ejecuta el instalador y sigue las instrucciones:
   - Establece una contraseña para el usuario `postgres` (guárdala, la necesitarás después)
   - Usa el puerto por defecto `5432`
   - Selecciona los componentes: PostgreSQL Server, pgAdmin (opcional, útil para gestionar la BD)
3. Añade el directorio `bin` de PostgreSQL a tu PATH del sistema (ej: `C:\Program Files\PostgreSQL\16\bin`)

### Instalación en macOS
Usa Homebrew:
```bash
brew install postgresql@16
brew services start postgresql@16
```

### Instalación en Linux (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

---

## Paso 2: Crear la Base de Datos en PostgreSQL

### 1. Conectarse a PostgreSQL
En Windows (usa el terminal):
```bash
psql -U postgres
```
Ingresa la contraseña que estableciste durante la instalación.

En macOS/Linux:
```bash
sudo -u postgres psql
```

### 2. Crear la base de datos y un usuario (opcional, pero recomendado)
Ejecuta estos comandos en la consola de psql:
```sql
-- Crea un usuario (reemplaza 'indexwatch_user' y 'tu_contraseña_segura')
CREATE USER indexwatch_user WITH PASSWORD 'tu_contraseña_segura';

-- Crea la base de datos
CREATE DATABASE indexwatch_db;

-- Asigna privilegios al usuario
GRANT ALL PRIVILEGES ON DATABASE indexwatch_db TO indexwatch_user;

-- Sal de psql
\q
```

---

## Paso 3: Configurar el Proyecto

### 1. Clonar o navegar al directorio del proyecto
```bash
cd "c:\Users\LENOVO\Documents\UNIVERSIDAD\CICLO VII\ADB\3RA UNIDAD\indexwatch"
```

### 2. Instalar dependencias de PHP
```bash
composer install
```

### 3. Configurar el archivo de entorno
Copia el archivo `.env.example` a `.env`:
```bash
cp .env.example .env
```
O en PowerShell:
```powershell
Copy-Item .env.example .env
```

### 4. Generar la clave de la aplicación
```bash
php artisan key:generate
```

---

## Paso 4: Configurar la Conexión a PostgreSQL
Edita tu archivo `.env` y modifica las siguientes líneas para configurar la conexión a PostgreSQL:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=indexwatch_db  # Nombre de la base de datos que creaste
DB_USERNAME=indexwatch_user  # Usuario que creaste
DB_PASSWORD=tu_contraseña_segura  # Contraseña del usuario
```

---

## Paso 5: Ejecutar Migraciones y Seeders
Ejecuta las migraciones para crear las tablas en la base de datos:
```bash
php artisan migrate
```

Si hay seeders (para datos de prueba), puedes ejecutarlos con:
```bash
php artisan db:seed
```
O si hay un `DemoSeeder` (como se ve en la estructura del proyecto):
```bash
php artisan db:seed --class=DemoSeeder
```

---

## Paso 6: Instalar Dependencias de Frontend
```bash
npm install
```

---

## Paso 7: Compilar Assets
Para compilar los assets una vez:
```bash
npm run build
```

Para desarrollo (con recarga automática):
```bash
npm run dev
```

---

## Paso 8: Levantar el Servidor de Desarrollo

### Método 1: Usar el script de Composer (recomendado)
Este comando levanta el servidor de PHP, la cola de jobs, el logger y Vite al mismo tiempo:
```bash
composer dev
```

### Método 2: Levantar solo el servidor de PHP
Si solo necesitas el servidor web:
```bash
php artisan serve
```

Luego abre tu navegador y visita: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Paso 9: Crear un Usuario (si es necesario, la seed por defecto crea un user)
Si el sistema requiere autenticación (usa Laravel Breeze), puedes crear un usuario usando Tinker:
```bash
php artisan tinker
```
Luego ejecuta:
```php
App\Models\User::create([
    'name' => 'Tu Nombre',
    'email' => 'tu@email.com',
    'password' => bcrypt('tu_contraseña'),
]);
```
O usa las rutas de registro de Breeze (si están disponibles).

---

## Problemas Comunes y Soluciones

### Error: "SQLSTATE[HY000] [2002] Connection refused" (PostgreSQL)
- Asegúrate de que el servicio de PostgreSQL esté corriendo
- Verifica que las credenciales en `.env` sean correctas
- Verifica que el puerto `5432` esté abierto y que PostgreSQL esté escuchando en él

### Error: "SQLSTATE[08006] [7] FATAL: password authentication failed for user"
- Asegúrate de que el nombre de usuario y la contraseña en `.env` sean correctos
- Si usaste el usuario `postgres`, recuerda usar la contraseña que estableciste durante la instalación

### Error: "SQLSTATE[3D000] [7] FATAL: database \"...\" does not exist"
- Asegúrate de haber creado la base de datos en PostgreSQL antes de ejecutar las migraciones

### Error: "Class '...' not found"
Ejecuta:
```bash
composer dump-autoload
```

### Error con assets (CSS/JS no se cargan)
Asegúrate de haber ejecutado `npm run build` o `npm run dev`.

---

## Comandos Útiles
- Limpiar caché de configuración: `php artisan config:clear`
- Limpiar caché de rutas: `php artisan route:clear`
- Limpiar caché de vistas: `php artisan view:clear`
- Limpiar toda la caché: `php artisan cache:clear`
- Ejecutar tests: `php artisan test`
- Ver rutas disponibles: `php artisan route:list`
