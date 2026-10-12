# SGPM
Aplicación mononodo conforme a la constitución 4.2.0-SGPM.

## Ejecución
El único DocumentRoot permitido es `public/`. El entorno de aceptación es Ubuntu
16.04, Apache 2.4.18, PHP 7.0.33, PostgreSQL 9.5.24 y PostGIS 2.2.x.
Copiar `config/config.example.php` a `config/config.php` y configurar las variables
indicadas; no guardar secretos en Git. Apache y CLI deben recibir el mismo entorno.

`php bin/check-compatibility.php` comprueba el runtime exacto y lint.
`php bin/check-compatibility.php --source-only` es diagnóstico estático, no certificación.
`php tests/run.php` ejecuta pruebas unitarias; `--integration` requiere la base aislada.
Las migraciones se ejecutan exclusivamente por CLI con una cuenta migradora separada.
Ver [entorno](ops/acceptance-environment.md) y [tareas](specs/001-sgpm-integral/tasks.md).
No se ha desplegado ni configurado un servidor productivo.

