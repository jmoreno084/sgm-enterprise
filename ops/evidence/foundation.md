# Evidencia local de fundamentos — 2026-10-12

## Alcance
T001–T019 implementadas como archivos revisables. T020 permanece pendiente:
la fase de fundamentos no ha superado su puerta de integración.
No se han iniciado T021–T120 ni ejecutado migraciones contra ninguna base.

## Resultados observados
- `php tests/run.php`: 4 suites PASS, 0 FAIL sobre PHP 8.1.25.
  Cubren matriz de roles, configuración/aislamiento, router/CSRF/conflicto de versión
  y división de scripts SQL con funciones y literales.
- `php bin/check-compatibility.php --source-only`: 31 PHP con lint y strict_types,
  diagnóstico estático conforme; checksums de 10 archivos de activos conformes.
  Este resultado NO certifica PHP 7.0.33 ni ejecución SQL/PostGIS.
- `php tests/run.php --integration`: 4 PASS, 1 FAIL. FoundationIntegrityTest se
  detuvo antes de conectarse: requiere PHP 7.0.33; disponible PHP 8.1.25.
- `php bin/check-compatibility.php`: exit 1 por runtime distinto de PHP 7.0.33.
- Runtime auxiliar encontrado: D:/xampp/php/php.exe, PHP 7.4.11 (con -n).
  Instalación PostgreSQL 14 encontrada; no es el PostgreSQL 9.5.24 requerido.
- `git diff --check`: sin errores de espacios.

## No verificado
Ejecución PHP 7.0.33, migraciones PostgreSQL 9.5.24/PostGIS 2.2.x, restricciones
y privilegios efectivos, auditoría/rollback real, HTTPS en Apache 2.4.18, pruebas
de navegador y rendimiento. Los paquetes PHP archivados tienen hashes/licencias
registrados, pero no están instalados ni certificados; runtime_verified=false.
No se invocó correo corporativo ni se desplegó o modificó un servicio existente.

## Reanudación
Preparar el entorno de ops/acceptance-environment.md. Con propietario fixture
separado, crear las fuentes con `php bin/seed-fixtures.php --external`.
Aplicar roles/grants mediante DBA y migraciones mediante cuenta migradora separada.
Ejecutar `php bin/check-compatibility.php` y `php tests/run.php --integration`.
Cerrar T020 únicamente con resultados conformes; después continuar con T021.

Las dos excepciones de requirements.md fueron aprobadas por el usuario; no se
modificaron sus casillas. No hay extensions.yml ni hooks posteriores registrados.

## Diagnostico Docker al reanudar implement - 2026-10-12

Comprobaciones de solo lectura:
- mi-app-php: PHP 7.0.33-0ubuntu0.16.04.16 y Apache 2.4.18 (Ubuntu).
- pdo_pgsql, curl, mbstring, XMLWriter, openssl y fileinfo disponibles; zip ausente.
- /var/www/html monta D:/Docker_Proyectos/sgm-enterprise/app, no este repositorio.
  /var/www/html/tests/run.php no existe en el contenedor.
- mi-postgis: SHOW server_version devuelve 9.5.25; postgis_lib_version() devuelve 2.5.5.
  T020 exige PostgreSQL 9.5.24/PostGIS 2.2.x.
- La aplicacion lee SGPM_*, no DB_*. Configuration solo admite loopback/socket;
  host=db seria rechazado por la validacion vigente.

T020 no se ejecuto en los contenedores: faltan montaje y entorno conforme.
No se modificaron contenedores, roles, fuentes ni datos. T020 sigue pendiente;
T021-T120 siguen bloqueadas por fundamentos. No hay extensions.yml.
Preparar un entorno independiente con versiones del plan, zip, repositorio montado,
DocumentRoot public/ y configuracion SGPM_* con base aislada y roles separados.
Resolver la compatibilidad de host=db con constitucion/configuracion antes de usarlo.
No reutilizar el directorio de datos existente para cambiar versiones del motor.

## Reanudacion verificada - 2026-10-13

El diagnostico anterior se conserva como historico. Con la constitucion 5.0.0-SGPM,
PostgreSQL 9.5.25 y PostGIS 2.5.5 son las versiones requeridas. El runtime actual tiene
zip; el bloqueo anterior por esa extension ya no se reproduce. Se reconocen los
componentes numericos PHP 7.0.33 sin rechazar el sufijo de distribucion Ubuntu.

ops/docker/test-foundation.ps1 aplica migraciones y pruebas en una base temporal
aislada en memoria. PHP monta este repositorio y comparte loopback con PostgreSQL.
FoundationIntegrityTest pasa: T020 completada, con privilegios, restricciones,
auditoria y rollback efectivos. enterprise y sus contenedores no se modificaron.
La configuracion y los contenedores temporales se eliminaron tras las pruebas.
Resultados ampliados de US1: ops/evidence/us1.md.
