# Parches de dependencias
No modificar vendor manualmente. Guardar cada cambio como diff unificado en este
directorio, registrar sha256 del artefacto original y del resultado en el manifiesto,
aplicar antes de empaquetar y comprobar todos los PHP con PHP 7.0.33.
Incluye insertar declare(strict_types=1) inmediatamente después de cada apertura PHP
cuando falte; comprobar efectos funcionales con smoke PDF UTF-8 y Shapefile.
Licencias upstream se conservan. Ninguna dependencia PHP está aún integrada o certificada.

