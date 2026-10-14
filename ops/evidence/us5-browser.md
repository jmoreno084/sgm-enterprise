# US5 — aceptación HTTPS local

Ejecución real: `python tests/browser/integral-playwright.py`, entorno Docker
desechable, PHP7.0.33/PostgreSQL9.5.25/PostGIS2.5.5. Certificado aceptado manualmente
por el usuario; ninguna opción para omitir TLS. Resultado consolidado: **83
comprobaciones aprobadas, 0 fallidas, 0 errores JavaScript**.

Evidencia: `integral-browser/results.json`, `map-metrics.json` y capturas PNG a
360/768/1440. Se revisaron visualmente mapa móvil, programación móvil y ficha
catastral de escritorio/tablet. Capturas con atributos XSS muestran texto literal.

Se comprobaron dos geometrías originales completas, Canvas propio y SVG con patrón
de la vecina17, Identify accesible con30 atributos, NULL explícito, tooltip local sin
HTTP, trazos de línea/polígono/marcador, medidas m/km/m²/ha/km² y centroide visual
separado del persistido. En esta muestra el tooltip tardó1.2ms y la carga129.1ms.
Son mediciones funcionales de dos objetos: **no certifican Q11**.

GeoJSON/KML/Shapefile ZIP fueron importados en la UI. Color y opacidad fueron
modificados; un archivo corrupto devolvió400 conservando las tres capas previas;
retirarlas no altera el catastro. Se probaron roles y acceso ajeno404.

Las peticiones a proveedores externos se bloquearon deliberadamente para comprobar
que su fallo no impide consultar atributos. Esto no acredita OSM/ESRI/Google reales.
Las cuatro opciones Google permanecen deshabilitadas sin clave. T092 sigue pendiente
por instrucción del usuario; véase `external-gates.md`.

Durante la revisión se corrigió un fallo observado de inicialización CSRF: guardar
inmediatamente al abrir un formulario ahora espera obtener el token de sesión.
El guion también se corrigió para usar listas de definición, coordenadas de dibujo
fuera de la barra y pausas de interacción. No se cambiaron los umbrales de aceptación.
