# Dependencias y proveedores del visor

Distribuciones fijadas: Leaflet1.9.4, Leaflet.draw1.0.4, Leaflet.Control.Measure3.1.0,
GoogleMutant0.13.5, esri-leaflet2.5.3 yTurf6.5.0. Versiones/commits/licencias/integridad
de archivos originales en ops/js-dependencies.json y public/assets/vendor/manifest.json.
Todos los JS distribuidos y propios pasan Acorn con ecmaVersion6 (ES2015), fuera de
producción. No build/transpilación ni jQuery/Bootstrap.js en runtime.

Reproducción: python ops/install-js-dependencies.py. Verificación: ejecutar
ops/check-javascript.cjs con Node de desarrollo; Acorn8.15.0 se incluye solo enops/tools.
Los paquetes npm se verifican con su integridad upstream y SHA256 fijado al primer
ingreso. No se resuelven versiones libres en producción.

Licencias: draw/measure/Turf MIT, esri-leaflet Apache-2.0, GoogleMutant Beerware,
Leaflet BSD-2-Clause. Se conservan avisos de código y archivos upstream.
Leaflet.draw1.0.4 no incluye un LICENSE propio en su tarball; package.json declara
MIT y README/cabeceras originales se conservan, sin atribuirle la licencia ajena de
docs/highlight. No se distribuyen ejemplos ni dependencias de documentación.

APIs primarias consultadas:
- https://leaflet.github.io/Leaflet.draw/docs/leaflet-draw-latest.html
- https://esri.github.io/esri-leaflet/api-reference/layers/basemap-layer.html
- https://gitlab.com/IvanSanchez/Leaflet.GridLayer.GoogleMutant

OSM usa https://tile.openstreetmap.org con atribución visible; respetar su política
https://operations.osmfoundation.org/policies/tiles/ y no realizar descargas masivas
en pruebas de carga. ESRI Imagery/Topographic conserva atribución esri-leaflet y los
términos de https://www.esri.com/en-us/legal/terms/full-master-agreement.
La API basemapLayer usada por la distribución fijada está marcada deprecated por
upstream; su disponibilidad real se verifica enT092 y no se supone garantizada.

Google requiere SGPM_GOOGLE_BROWSER_KEY, clave pública de navegador restringida al
origen autorizado y Maps JavaScript API habilitada, con licencias/billing autorizados.
Se usa únicamente enel SDK oficial HTTPS. Sin clave, opcionesGoogle deshabilitadas
con estado explícito; OSM/ESRI y atributos siguen disponibles. No se sustituyen por
URLs de tiles Google no autorizadas. No exponer USERKEY/APIKEY corporativos.

Patrones: índice0 sinrayado; desde9, índice1+floor((ordinal-9)/8); espaciado SVG
6+2*(índice mod4), grosor1+((índice-1)mod3), diagonal45°. Color/patrón del vínculo
se conservan; hover se resuelve con propiedades cargadas y DOMtextContent.

2026-10-13: el usuario indicó que no dispone de clavesGoogle ni contrato/muestras
de correo. T092/T118 permanecen pendientes externas; ningún doble certifica proveedor real.
