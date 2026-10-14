# Evidencia de compatibilidad

La última comprobación registrada de `bin/check-compatibility.php` revisó 222 archivos PHP y assets ES2015; suites anteriores ejecutaron PHP 7.0.33. Manifiestos de vendor fijan origen, commit, checksum, licencia y parches.

Corrida limpia final de `ops/docker/test-foundation.ps1`: **41 suites PASS, 0 FAIL** y `bin/check-compatibility.php` revisó **222 archivos PHP** bajo PHP 7.0.33. Los tests también comprueban strict_types, scripts SQL PG9.5/PostGIS2.5.5 y parser ES2015. Evidencia: salida del runner en esta sesión.
