# Visor US5 — recorrido de aceptación

Preparar un entorno aislado con `ops/docker/test-foundation.ps1 -KeepAcceptance`.
El usuario acepta el certificado de storage/acceptance/server.crt. Playwright no
omite TLS ni instala confianza automáticamente. No utilizar producción/enterprise.

1. Crear proyecto/vecinas/expedientes de fixture en la base temporal; incluir propios
   TITULO/SOLICITUD, vecinas9/17 y fuentes retiradas. Abrir mapa con cada rol autorizado.
2. Esperar `window.sgpmMapMetrics.complete`; contrastar rendered/source_total con
   el conjunto de referencia y todas sus continuaciones, sin omisiones/simplificación.
3. Bases: OSM/ESRI y Google4 si hay clave autorizada. Fallo de tiles no impide
   lista/Identify/importación. Los dobles comprueban comportamiento, no proveedor real.
4. Leyenda azul/verde/violeta y patronesSVG desde9; todos los expedientes de una
   vecina comparten estilo. Hover local: tooltip correcto, sin fetch, cada muestra<200ms.
5. Abrir Identify por clic y por botón accesible de expediente:30 atributos,
   filiación/nombre, DATE/NULL, áreaOFICIAL0 frente aGEODESICA, fuente retirada explícita.
6. Dibujar líneas/polígonos/marcadores de trabajo con Leaflet.draw; comprobar medidas
   m/km/m²/ha/km² y centroideTurf auxiliar. Herramienta editar/borrar solo trabajo.
7. Importar fixtures GeoJSON/KML y ZIP Shapefile válido con .prj4326/.cpgUTF8.
   Cambiar color/opacidad y retirar capa; no mutar fuentes/proyecto. Error de importación
   conserva capas anteriores. Recorrer controles con teclado en360/768/1440.
8. Probar acceso directo B404, Geólogo sin exportación/auditoría yCSRF403.
9. Guardar latencias/capturas saneadas en ops/evidence/us5-browser y revisar visualmente.
10. Retirar solo el entorno temporal con stop-acceptance.ps1. No afirmar proveedores
    reales ni cierreT092 sin evidencia/claves autorizadas disponibles.
