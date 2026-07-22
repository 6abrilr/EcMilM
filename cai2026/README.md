# CAI 2026

Aplicación PHP/MySQL sin framework para cronometraje y clasificación en tiempo real.

## Instalación en XAMPP

1. Importe `sql/database.sql` desde phpMyAdmin.
2. Configure las variables `CAI_DB_HOST`, `CAI_DB_NAME`, `CAI_DB_USER`, `CAI_DB_PASS` y `CAI_BASE_URL`. En XAMPP los valores predeterminados funcionan si el proyecto está en `/ea/cai2026`.
3. Cree el primer usuario:
   `C:\xampp\php\php.exe tools\create_admin.php correo@gmail.com "Administrador"`
4. Abra `http://localhost/ea/cai2026/`.

## Hostinger

Suba el contenido de esta carpeta a `public_html`, importe el SQL, configure PHP 8.2 o superior y cargue las variables de base de datos. En producción use `CAI_BASE_URL` vacío si el dominio apunta directamente a esta carpeta y active HTTPS.

## Gmail / Google

Cada usuario inicia con su correo Gmail asociado y una contraseña propia de CAI (la aplicación nunca guarda la contraseña de Gmail). El esquema incluye `google_sub` y la configuración OAuth para habilitar más adelante “Ingresar con Google” con credenciales de Google Cloud. Para un evento real conviene mantener también el acceso local como respaldo ante problemas externos.

## Operación

Los eventos oficiales usan exclusivamente `NOW(6)` de MariaDB. El navegador recibe la hora del servidor, calcula sólo la presentación y vuelve a sincronizar cada 2 segundos. Las acciones quedan registradas en `historial`.

Antes de la competencia: probar HTTPS, zona horaria, dos dispositivos simultáneos, copias de seguridad, recuperación de contraseña y conectividad de contingencia.

