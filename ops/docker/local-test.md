# Ambiente Docker persistente de US1

Desde PowerShell, en la raiz del repositorio:

```powershell
./ops/docker/local-test.ps1 start
./ops/docker/local-test.ps1 status
./ops/docker/local-test.ps1 stop
```

`start` crea el ambiente la primera vez y lo inicia las siguientes. Mantiene
los datos existentes, aplica solo migraciones pendientes con checksum y carga
fixtures internas solo cuando no hay usuarios. `stop` detiene exclusivamente
sus dos contenedores; no elimina contenedores, volumenes, datos ni credenciales.
Docker Desktop debe estar iniciado. No requiere descargar ni reconstruir imagenes.

URL: **https://localhost:18443/login**. HTTP localhost:18080 redirige a HTTPS.
Solo se publican puertos en 127.0.0.1; PostgreSQL no se publica en el host.

Las credenciales estan en `storage/local-test/credentials.json`, excluido de Git:
`admin_email` y `password`. La clave inicial tambien sirve para los usuarios
fixture gerente, geologo e inversionista enumerados en `fixture_emails`.
Cambiar claves desde la aplicacion no modifica ese registro de claves iniciales;
un nuevo `start` conserva las claves actuales de la base.

## HTTPS

El certificado local esta en `storage/local-test/server.crt`. Se conserva entre
reinicios y tiene validez de 365 dias desde su generacion. No se instala confianza
automaticamente ni se omite TLS. Para confiar en el desde el navegador Windows,
el usuario puede importarlo en `certmgr.msc` > Entidades de certificacion raiz de
confianza > Certificados > Todas las tareas > Importar, para su usuario actual.
Es un certificado nuevo, distinto del entorno temporal anterior. Tiene CN localhost
y organizacion SGPM local test. El archivo `server.key` es privado y no se comparte.
Si el certificado caduca, se requiere una renovacion explicita; `start` no lo reemplaza.

## Aislamiento y persistencia

Se reutilizan los IDs de imagen de `mi-app-php` y `mi-postgis`, sin modificar esos
contenedores. PHP 7.0.33, Apache 2.4.18, PostgreSQL 9.5.25 y PostGIS 2.5.5.
Los IDs quedan fijados en `storage/local-test/local.json`.

Los nombres incluyen un hash de la ruta del repositorio, y todos los recursos
se validan por etiquetas antes de operarlos. La base exclusiva es `sgpm_local_test`.
Volumenes propios guardan PostgreSQL, sesiones y registros. Certificado, claves,
configuracion y metadatos permanecen en `storage/local-test/`, excluido de Git.
No se modifica `config/config.php` del repositorio: se monta una configuracion
propia dentro del contenedor. Runtime, migrador y propietario de fuentes fixture
usan roles separados. El contenedor web recibe solo credenciales runtime.

Los contenedores tienen `restart=unless-stopped`; tras detenerlos deliberadamente,
usa `start` para encenderlos. El contenedor web comparte la red del PostgreSQL
exclusivo para conservar el DSN local 127.0.0.1 exigido por la aplicacion.
Conserva `local.json` y todos los archivos privados: contienen las claves de la
base y del cifrado del outbox. No se proporciona un comando de borrado/reset.

## Verificacion y alcance

```powershell
./ops/docker/local-test.ps1 check
```

Ejecuta 26 comprobaciones por HTTPS con CA explicita y verificacion de host/peer
activa. Usa una IP loopback exclusiva para no consumir limites del navegador.
Crea un usuario de verificacion, prueba conflictos, membresias, permisos y
recuperacion; al terminar lo desactiva conservando su historial. Cada ejecucion
agrega ese registro de prueba; no limpia ni modifica usuarios anteriores.

Correo siempre en modo `double`, sin APIKEY/USERKEY reales, sin worker programado
y sin envios reales. Bienvenida/recuperacion generan outbox cifrado de prueba;
la prueba de recuperacion obtiene su token solo en memoria. No llegan correos
a los buzones. Los escenarios de entrega real y programacion siguen fuera de US1.

Este ambiente habilita acceso, usuarios, membresias y recuperacion implementados
en US1. Empresas A/B son fixtures; las pantallas y funciones de historias
posteriores aun no estan implementadas. No ejecutar las suites de integracion
generales sobre esta base persistente: usan fixtures mutables; continuar usando
el runner desechable `test-foundation.ps1` para esas suites. Sus puertos
18080/18443 coinciden: detener primero este ambiente si se usa `-KeepAcceptance`.

Evidencia de puesta en marcha: `ops/evidence/local-test.md`.
