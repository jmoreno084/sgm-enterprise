# Guía de despliegue controlado

Esta guía prepara una instalación revisable. No autoriza ni ejecuta despliegues o migraciones en producción. La aceptación aún tiene puertas abiertas documentadas en `ops/evidence/acceptance.md`.

## Requisitos

- Runtime compatible fijado: Apache 2.4.18, PHP 7.0.33, PostgreSQL 9.5.25 y PostGIS 2.5.5. Revisar `bin/check-compatibility.php` en el host y sus manifiestos.
- Servir únicamente `public/`; mantener `config/`, `storage/`, `logs/`, `bin/` y `vendor/` fuera del DocumentRoot. Apache base en `ops/apache/sgpm.conf`. Configurar TLS con certificado/clave administrados por el operador y conservar HTTPS; no instalar el certificado temporal de aceptación.
- Crear `config/config.php` fuera de Git a partir de `config/config.example.php`; mismo entorno para Apache/CLI. Correo en `double` hasta validar `config/email-response.example.php` con contrato real. No guardar secretos en el repositorio.
- Separar roles DB migrador, runtime de solo lectura del catastro y propietario de aplicación. El runtime no debe poder modificar las fuentes externas. Revisar grants y roles de las migraciones antes de aplicar.

## Preparación y operación

1. Revisar release, versión PHP exacta, extensiones, permisos privados de `storage/` y `logs/`, límites de carga, zonas horarias y configuración HTTPS.
2. Tomar backup consistente de base y archivos privados; restaurar primero en un entorno aislado y comprobarlo.
3. Ejecutar `php bin/check-compatibility.php`; leer y revisar individualmente las migraciones pendientes. Aplicar solo con CLI y cuenta migradora en una ventana autorizada; nunca correr migraciones desde una petición web.
4. Crear el administrador inicial por el flujo de instalación aprobado y guardar sus credenciales en un almacén local seguro, fuera de Git. Confirmar usuarios/empresas de prueba solo en aceptación.
5. Configurar Apache TLS/redirect/HSTS/CSP, validar rutas privadas y asegurar directorios de sesión/upload. Restringir orígenes Google y límites por cuota si se habilita una clave.
6. Instalar `ops/cron/sgpm` solo después de revisar rutas, usuario de servicio y frecuencia. El ejemplo no se instala automáticamente. Ejecutar scheduler/worker con el mismo usuario que Apache para que publicaciones privadas 0600 sean descargables; alertas y correo continúan en doble hasta la aceptación del proveedor.
7. Validar login, roles A/B, CRUD, catastro de solo lectura, reportes/descarga, auditoría, backups y restauración. Documentar salud y request IDs sin incluir tokens.

## Reversión

Detener workers y cron; conservar logs privados; restaurar conjuntamente backup de base y archivos si el esquema ya recibió escrituras. No borrar volúmenes ni datos para intentar revertir una migración. No editar contenedores originales ni datos del catastro.
