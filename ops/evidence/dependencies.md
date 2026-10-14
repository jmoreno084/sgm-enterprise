# Dependencias auditadas

Fuentes reproducibles y manifiestos: `ops/dependencies-php.json`, `ops/js-dependencies.json`, `public/assets/vendor/manifest.json`, `ops/dependency-sources/` y `ops/patches/`. Los instaladores comprueban hashes y aplican solo parches registrados; los paquetes incluyen licencias. El smoke runtime se registró con el suite.

Repetir instaladores y `bin/check-compatibility.php` después de cambiar los manifiestos. No introducir claves en assets ni compilar dependencias durante producción.
