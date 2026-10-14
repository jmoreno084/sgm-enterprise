# Evidencia US5 — mapa, Identify e importación

Funciones implementadas: búsquedas paginadas y filtros autorizados, GeoJSON con geometrías originales, centroide oficial separado, identificación con 30 atributos, áreas oficiales (incluido cero) y área geodésica en la ficha de detalle. El trazado masivo conserva cada coordenada y evita calcular áreas geodésicas que no se muestran en el mapa. Importación GeoJSON/KML/ZIP Shapefile temporal, sin escrituras al catastro.

La suite limpia registró MapContractTest, MapHttpTest, ImportSecurityTest y VendorSmokeTest PASS; ImportSecurityTest pasó otra vez tras añadir los casos de CRS, symlink y límite de vértices. Playwright integral verificó las tres importaciones, rechazo de archivo corrupto conservando capas, estilos/proyecto, Identify, medición, tooltip <200 ms y ausencia de errores JavaScript. La carga SSD final registró 12/12 muestras y 500/5000 polígonos completos con 50.000/500.000 vértices; ver `performance.md`.

T092 sigue pendiente: no se recibió clave Google restringida ni permiso/datos de proveedor para contrastar Q5/Q6. La prueba confirmó el estado sin clave (capa Google desactivada); no representa validación de Google real ni de las fuentes externas.

Ver `us5-browser.md`, `tests/fixtures/imports/manifest.json` y `tests/integration/ImportSecurityTest.php`.
