# Pastelería Dulce Naranja

Aplicación web multicapa en PHP 8 + MariaDB para catálogo, autenticación, administración de productos, carrito, pedidos, puntos y recompensas.

## Seguridad del primer administrador

No existe ningún usuario administrativo predeterminado. La creación del primer ADMIN requiere tres condiciones simultáneas:

1. `setup_completed = 0` en `system_settings`.
2. No existe ningún usuario con rol `ADMINISTRADOR`.
3. Se proporciona el código aleatorio local generado en `storage/install.key` mediante `php bin/generate-install-key.php`.

Al crear el administrador, el proceso usa una transacción, establece `setup_completed = 1` y elimina `storage/install.key`. Si posteriormente se borran todos los administradores, el registro inicial **no se reabre** automáticamente.

## Requisitos

- PHP 8.1 o superior con PDO MySQL y Fileinfo.
- MariaDB 10.5+ / MySQL 8+.
- Navegador moderno.

## Instalación

1. Copia `.env.example` a `.env`.
2. Edita las credenciales de MariaDB en `.env`.
3. Importa `database/schema.sql`.
4. Genera el código de instalación:

```bash
php bin/generate-install-key.php
```

5. Inicia el servidor desde la raíz:

```bash
php -S localhost:8080 -t public public/router.php
```

6. Abre `http://localhost:8080/setup-admin` y usa el código generado para crear el primer administrador.
7. Después del primer administrador, `/register` crea exclusivamente CLIENTES.

En Windows también puedes ejecutar:

```powershell
powershell -ExecutionPolicy Bypass -File .\bin\setup.ps1
```

## Funcionalidades incluidas

- Arquitectura por Controllers, Services, Repositories, Middlewares, Helpers y Views.
- Login y registro con contraseñas `password_hash()` / `password_verify()`.
- Configuración segura del primer administrador.
- Roles ADMINISTRADOR y CLIENTE.
- Sesiones con cookie HttpOnly + SameSite Lax y regeneración de ID al login.
- CSRF en operaciones POST.
- PDO con consultas preparadas.
- Gestión administrativa de categorías, productos e imágenes.
- Gestión de usuarios y creación de nuevos ADMINISTRADORES únicamente desde una sesión administrativa.
- Gestión de estados de pedidos, reportes básicos y configuración del ratio global de puntos por USD.
- Validación MIME de JPG, PNG y WEBP; nombre aleatorio del archivo.
- Catálogo responsive, búsqueda y filtros por categoría.
- Carrito por sesión.
- Checkout autenticado con control de stock dentro de transacción.
- Puntos ganados por producto y registro inmutable de movimientos.
- Recompensas configurables por puntos y porcentaje de descuento.
- Perfil con historial de puntos y pedidos.
- Dashboard administrativo con métricas.
- Interfaz pastel naranja responsive.

## Estructura

```text
public/                 Front controller, CSS y JS
src/Controllers/        Capa de presentación/control
src/Services/           Reglas de negocio
src/Repositories/       Acceso a datos
src/Middlewares/        Autenticación y autorización
src/Helpers/            CSRF, sesión, vistas, setup
src/Config/             Base de datos
views/                  Vistas PHP sin SQL
database/schema.sql     Esquema relacional
storage/uploads/        Imágenes de productos
bin/                    Utilidades de instalación
```

## Nota de producción

En producción coloca `SESSION_SECURE=true`, sirve la aplicación con HTTPS, configura permisos mínimos para `storage/`, deshabilita `display_errors`, usa un usuario de base de datos con privilegios limitados y ejecuta detrás de Apache/Nginx en lugar del servidor integrado de PHP.
