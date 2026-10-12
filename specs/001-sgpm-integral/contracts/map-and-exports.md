# Contrato de mapa, importación y exportación

**Fecha:** 2026-10-11. Referencias: [HTTP](http-api.md) y [modelo](../data-model.md).

## GeoJSON del proyecto

GET /api/v1/proyectos/{id}/mapa devuelve FeatureCollection EPSG4326, geometrías oficiales
sin edición ni corrección. Cada Feature conserva un identificador de vínculo y properties:
codigo_expediente, tipo_expediente, filiacion, proyecto_id, proyecto_nombre,
empresa_vecina_id(nullable), empresa_vecina_nombre(nullable), color_hex, patron_indice,
area_ha(nullable), area_origen(OFICIAL/GEODESICA/NO_DISPONIBLE), tooltip.
No incluir datos de otras empresas ni secretos de usuario.

Ejemplo de properties, sin sustituir la geometría real:

```json
{"vinculo_id":10,"codigo_expediente":"ABC-123","tipo_expediente":"TITULO","filiacion":"PROPIO","proyecto_id":2,"proyecto_nombre":"Proyecto Andino","empresa_vecina_id":null,"empresa_vecina_nombre":null,"color_hex":"#007bff","patron_indice":0,"area_ha":125.5,"area_origen":"OFICIAL","tooltip":"Proyecto: Proyecto Andino (ABC-123)"}
```

PROPIO TITULO azul #007bff; PROPIO SOLICITUD verde #28a745; VECINO violeta del vínculo,
independientemente del tipo. Leyenda visible y patrón desde ordinal9. Tooltips desde
properties precargadas, no petición al servidor por hover, aparición<200ms.

bbox opcional: cuatro números minLon,minLat,maxLon,maxLat dentro del rango WGS84;
validar orden y rechazar400 si inválido. Es filtro espacial, no valida topología legal.
No ocultar un límite de resultados: si se divide la respuesta, indicar página/continuación
y no declarar carga completa hasta terminar. No simplificar ni recortar límites legales
silenciosamente. Fuente retirada se enumera en meta.fuentes_no_disponibles y no inventa
geometría; se conservan vínculos y acceso a datos locales.

## Identify, proveedores y herramientas

Identify devuelve los30 atributos canónicos de la fuente más filiación y nombre del
proyecto o vecina. the_geom se representa como GeoJSON, no binario opaco. Fechas YYYY-MM-DD,
NULL explícito sin inventar valores. Si la fuente desapareció, data contiene vínculo y
fuente_disponible=false; atributos oficiales ausentes se marcan no disponibles.

Bases OSM, Google calles/satélite/híbrido/terreno y ESRI satelital/topográfica. Claves de
navegador restringidas por origen; no revelar claves privadas corporativas. Respetar
atribución/licencias del proveedor; fallo de una base no bloquea atributos ni otras bases.
Leaflet.draw restringe edición a marcadores y trazos de trabajo; nunca guarda una edición
sobre the_geom oficial. Medidas cliente son auxiliares, expresadas en m/km y m²/ha/km².
Área oficial usa AREA_HA o cálculo PostGIS geodésico, sin reemplazo por medición visual.
SVG pattern para vecinos; Canvas permitido en capas sin patrón. Carga por lotes mantiene
interacción; sin popup HTML construido con atributos no escapados.

## Importación temporal

POST multipart /api/v1/proyectos/{id}/capas/importar: archivo .zip Shapefile, .geojson/.json
o .kml. Máximo20MiB de entrada,100MiB expandidos,5000 objetos,500000 vértices.
.zip contiene un conjunto .shp/.shx/.dbf y .prj WGS84; .cpg UTF-8 si está presente.
Paths relativos seguros, sin .., absolutos, enlaces o ejecutables. Rechazar antes de
extraer un archivo cuyo total expandido supere el límite; limpiar temporal en éxito/fallo.
Otro CRS o falta de .prj se rechaza400 con instrucción de exportar en WGS84; no se presume
un CRS silenciosamente. KML usa DOM sin DTD/entidades externas ni consultas de red.

Respuesta: FeatureCollection, nombre_capa, avisos[] y crs EPSG4326. El navegador asigna
color/opacidad configurables por capa; opacidad0..1. Las capas no se guardan en catastro ni
en proyectos: son trabajo de sesión. Error413/415/400 no elimina capas ya cargadas.
Esto no ejecuta ST_IsValid, ST_MakeValid ni comprobaciones de traslape oficiales.

## Conjunto exportable

Solo Administrador global o Gerente/Inversionista de la empresa. Exportación toma registros
filtrados del ámbito, metadata de proyecto/filiación, códigos y atributos oficiales
existentes. La consulta y la descarga revalidan membresía actual. Filtros recibidos son
whitelist normalizada, no SQL. Un expediente sin fuente se incluye en tabular con aviso;
en formato espacial queda en manifiesto de omisiones y no se fabrica un polígono.

| Formato | Contrato |
| --- | --- |
| PDF | Informe tabular paginado con empresa/proyecto, filtros, fecha y leyenda; UTF-8 mediante fuente compatible. |
| XLSX | Libro OOXML real .xlsx por ZipArchive/XMLWriter; fechas/números tipados y texto explícito. No HTML renombrado como Excel. |
| CSV | UTF-8, encabezados canónicos, separador coma y comillas dobles; neutralizar celdas textuales que inicien =,+,-,@,tab/CR para impedir fórmulas. |
| GeoJSON | FeatureCollection4326, atributos canónicos, códigos, filiación y geometrías originales disponibles. |
| KML3D | KML2.2 con Polygon/MultiGeometry, atributos en ExtendedData; sin Z fuente usar clampToGround, no alturas ficticias. |
| Shapefile | ZIP .shp/.shx/.dbf/.prj WGS84/.cpg UTF-8, manifest.json y fields.json; geometrías MultiPolygon. |

Nombres DBF: máximo10 caracteres, mapeo fijo y reversible en fields.json (por ejemplo
CODIGO_EXPEDIENTE→EXP_CODE, SOLICITANTES_O_TITULARES→TITULARES). Para los demás atributos
usar F01..F30 según el orden canónico, omitiendo F30 como atributo porque contiene geometría.
Mantener EXP_CODE/TITULARES como aliases reservados del mapeo, sin duplicarlos.
manifest.json documenta CRS, origen, filtros, fecha, cantidad y advertencias. Texto DBF que
exceda capacidad no se trunca silenciosamente: colocar texto completo en attributes.json
clave por EXP_CODE y campo, con aviso y marcador en DBF. CSV/GeoJSON conservan nombres
completos. Un valor no representable como numérico DBF se conserva como texto o sidecar,
según mapeo declarado, nunca pérdida silenciosa.

Archivo generado fuera de public, caducidad24h. Content-Disposition attachment con nombre
seguro y tipo MIME por formato. URLs son autenticadas, sin paths internos ni tokens de
credenciales. Estado LISTO solo después de terminar archivo y publicación atómica.
No archivos parcialmente visibles. Imágenes de mapas base no se copian a PDF sin permiso
del proveedor: el reporte exigido es tabular.
