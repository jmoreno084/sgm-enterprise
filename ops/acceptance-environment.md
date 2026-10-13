# Entorno de aceptación
Objetivo obligatorio: Ubuntu 16.04 LTS 64 bits, Apache 2.4.18 (rewrite/ssl/headers),
PHP 7.0.33 (módulo o FPM), PostgreSQL 9.5.25/PostGIS 2.5.5 local:5432.
Extensiones: PDO/pdo_pgsql, curl, json, mbstring, DOM/XMLWriter, zip, openssl, fileinfo.
Comprobar php -v/-m, apache2 -v, apache2ctl -M y SELECT version(), PostGIS_Full_Version().
No sustituir estas evidencias por pruebas en PHP 8.

## Aislamiento y permisos
Preparar sgpm_acceptance como base independiente. SGPM_ENV=test y
SGPM_TEST_DATABASE=sgpm_acceptance deben coincidir con el DSN.
sgpm_owner NOLOGIN es propietario interno; migrador separado con SET ROLE.
sgpm_fixture_owner es propietario externo de las dos fuentes de prueba.
sgpm_user no es miembro de propietarios y solo recibe SELECT sobre fuentes.
Crear fuentes de fixture antes de migraciones; nunca ejecutar fixtures en producción.
Apache/CLI usan la misma configuración fuera de public; chmod 600 config/config.php,
sesiones/logs/exportaciones privados y escribibles solo por la cuenta de servicio.
No incluir secretos en argumentos ni en cron público.

## Variables
SGPM_ENV, SGPM_DSN (pgsql:host=127.0.0.1;port=5432;dbname=sgpm_acceptance),
SGPM_DB_USER, SGPM_DB_PASSWORD, SGPM_BASE_URL (HTTPS), SGPM_EMAIL_MODE=double,
SGPM_EMAIL_USERKEY, SGPM_EMAIL_APIKEY, SGPM_ENCRYPTION_KEY, SGPM_MAC_KEY,
SGPM_TEST_DATABASE. Las dos claves son 32 bytes aleatorios distintos en base64.
El modo real requiere perfil de respuesta verificado y credenciales, no basta HTTP 200.

El migrador usa SGPM_MIGRATION_DB_USER/SGPM_MIGRATION_DB_PASSWORD, separados del
runtime. Las fuentes fixture usan SGPM_FIXTURE_DB_USER/SGPM_FIXTURE_DB_PASSWORD,
cuya cuenta puede asumir sgpm_fixture_owner solo en la base aislada. Aplicar
ops/database/fixture-roles.sql por DBA en esa base antes de crear las fuentes.
La carga interna necesita SGPM_FIXTURE_PASSWORD temporal (12 caracteres y hasta
72 bytes); no hay claves fixture predeterminadas ni credenciales en el repositorio.

Orden: preparar PostGIS/propietario fixture y fuentes; aplicar roles.sql; conceder
CREATE sobre la base y membresía sgpm_owner a la cuenta migradora; migrar con
bin/migrate.php --env=test; aplicar grants-runtime.sql; cargar fixtures internas.
Proteger y retirar el entorno de las cuentas elevadas al finalizar los trabajos.

## Rendimiento y evidencias
Servidor 4 vCPU/8GiB/SSD; cliente 4 núcleos/8GiB; 20Mbps y RTT50ms.
50 sesiones, calentamiento120s y medición600s; mezcla60/20/10/5/5 y pausas1–3s.
Fuentes100000 filas; 500 polígonos/50000 vértices y 5000/500000.
Registrar todas las muestras, máximo y p95; no aprobar incumplimientos ocultos.
Ver quickstart Q0–Q11. No se ha provisionado, desplegado o medido este entorno.


## Runner local Docker - 2026-10-13

Ejecutar ./ops/docker/test-foundation.ps1 desde PowerShell. Usa las imagenes locales
observadas en mi-app-php y mi-postgis y monta este repositorio en modo lectura.
Crea PostgreSQL en tmpfs sin puertos publicados ni volumen persistente; PHP comparte
su espacio de red y conecta por 127.0.0.1:5432, sin cambiar el DSN permitido.
Prepara sgpm_acceptance, propietarios separados, migrador y runtime; las credenciales
son efimeras y no se escriben en el repositorio. config/config.php debe estar ausente;
el runner crea una copia temporal del ejemplo y la retira al finalizar.
Ejecuta migraciones, fixtures, contratos/integracion y compatibilidad. No despliega
la aplicacion productiva ni certifica revision visual o conformidad de correo real.

## HTTPS local revisable

El runner valida Apache HTTPS con CA de fixture explicita, 301, HSTS y sesiones
Secure, sin desactivar la validacion TLS. El doble de correo realiza POST real
solo contra localhost. -KeepAcceptance mantiene el entorno en
https://localhost:18443/login; no instala confianza global del certificado.
Credenciales efimeras: storage/acceptance/local.json (ignorado por Git).
Retirar con ./ops/docker/stop-acceptance.ps1. La revision visual requiere navegador;
ver tests/browser/us1-scenarios.md. TLS productivo y correo real no se certifican aqui.