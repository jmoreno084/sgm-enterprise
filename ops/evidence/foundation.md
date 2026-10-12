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
