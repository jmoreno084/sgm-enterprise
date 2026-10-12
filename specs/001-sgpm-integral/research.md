# Investigación y decisiones — SGPM integral

**Fecha:** 2026-10-11. **Constitución:** 4.2.0-SGPM. **Requisitos de origen:** 4.1.0-SGPM.
La investigación verifica alternativas dentro del stack obligatorio; no autoriza cambiarlo.
Se consultaron fuentes oficiales y se revisó la especificación local. No se invocó el
servicio real de correo ni se probó el servidor de destino.

## R01 — Arquitectura y entrega

**Decisión:** Monolito modular PHP 7.0.33, Apache/2.4.18 y PostgreSQL 9.5.24/PostGIS 2.2.x
sobre el único host Ubuntu 16.04 de 64 bits. Controlador frontal y HTML servido por PHP;
interactividad mediante módulos nativos ES2015 sin transpilación. Dependencias estáticas
preconstruidas, versionadas por checksum en el paquete de entrega. Composer, si se usa
para preparar vendor, se ejecuta fuera de producción; no resuelve versiones libremente.

**Razón:** Mantiene el stack y permite revisar cada módulo sin procesos SPA ni nodos extra.
**Alternativas:** React/Vue, microservicios y compilación en producción descartados por Ley I.
PHP 7.1 incorpora características que no se permiten: ni iterable ni visibilidad de
constantes de clase, además de las prohibiciones ya enumeradas.
[Guía oficial de migración PHP 7.1](https://www.php.net/manual/en/migration71.new-features.php).

## R02 — Dependencias y formatos

**Decisión:** Leaflet 1.9.4; CSS Bootstrap 4.6.2 y comportamiento propio VanillaJS.
No cargar bootstrap.js, jQuery ni Popper: los plugins JavaScript oficiales de Bootstrap
requieren jQuery, mientras sus estilos pueden utilizarse independientemente.
[Bootstrap 4.6](https://getbootstrap.com/docs/4.6/),
[distribuciones Leaflet](https://leafletjs.com/download.html).

Leaflet.draw1.0.4, Leaflet.Control.Measure, GoogleMutant, esri-leaflet y Turf son obligatorios.
Usar sus distribuciones de navegador sin build en producción. El manifiesto de entrega
debe fijar tag/commit, licencia, checksum, dependencias transitivas y verificación ES2015.
No se afirma que las versiones más recientes cumplan ES2015 ni se adoptan por defecto.
Leaflet.draw declara soporte Leaflet 1.0.0+.
[Leaflet.draw](https://github.com/Leaflet/Leaflet.draw), [manifiesto1.0.4](https://raw.githubusercontent.com/Leaflet/Leaflet.draw/v1.0.4/package.json),
[GoogleMutant](https://gitlab.com/IvanSanchez/Leaflet.GridLayer.GoogleMutant),
[esri-leaflet](https://developers.arcgis.com/esri-leaflet/).

**Decisión Excel:** XLSX nativo con ZipArchive/XMLWriter; celdas textuales explícitas y
campos numéricos solo donde el modelo lo permita. CSV con neutralización de fórmulas.
**Alternativa:** PHPExcel 1.8.2 declara compatibilidad PHP7 pero no se incorpora una
biblioteca completa para exportar un libro tabular simple.
[ZipArchive](https://www.php.net/manual/en/class.ziparchive.php),
[XMLWriter](https://www.php.net/manual/en/book.xmlwriter.php),
[manifiesto PHPExcel 1.8.2](https://raw.githubusercontent.com/PHPOffice/PHPExcel/1.8.2/composer.json).

**Decisión PDF:** Adaptador tFPDF v1.33 con fuente UTF-8 redistribuible y su licencia.
El manifiesto no exige PHP moderno, pero eso no prueba ejecución: el paquete exacto
debe pasar lint y exportación de nombres acentuados sobre PHP 7.0.33 antes de integrarse.
**Alternativa:** FPDF convencional obliga a conversiones de codificación y puede perder texto.
[Manifiesto tFPDF v1.33](https://raw.githubusercontent.com/Setasign/tFPDF/v1.33/composer.json),
[FAQ FPDF](https://www.fpdf.org/en/FAQ.php).

**Decisión Shapefile:** Adaptador Reader/Writer gasparesganga/php-shapefile v3.4.1;
su manifiesto versionado declara PHP >=5.4. Congelar tag/commit y verificar su manifiesto,
licencia, sintaxis y smoke test en PHP7.0 antes de usarlo. Alimentarlo con datos obtenidos
por PDO, nunca con una conexión paralela a PostgreSQL. ZIP por ZipArchive.
**Alternativa rechazada:** pgsql2shp conecta directamente a la base y violaría PDO exclusivo.
[Manifiesto upstream](https://raw.githubusercontent.com/gasparesganga/php-shapefile/v3.4.1/composer.json),
[herramientas PostGIS 2.2](https://postgis.net/docs/manual-2.2/using_postgis_dbmanagement.html).
La compatibilidad final de estos paquetes es una comprobación de implementación exigida,
no una licencia para introducir sintaxis moderna ni una verificación ya ejecutada.

## R03 — Sesión y RBAC

**Decisión:** usuario.es_administrador global y empresa_usuarios con rol por empresa.
Sesión contiene identidad y nonce, no una copia permanente de permisos. Releer usuario
activo y membresía vigente en cada petición, incluyendo trabajos diferidos. Toda consulta
privada y escritura se filtra por empresa en servidor. Un administrador funcional nunca
es superusuario PostgreSQL. El Geólogo no exporta ni consulta auditoría.
**Alternativas:** empresa_id recibido como autorización, ocultar botones como control o
permisos persistidos en navegador se descartan por aislamiento insuficiente.

CSRF con random_bytes y hash_equals. Recuperación con token aleatorio de 32 bytes,
hash SHA-256 almacenado, duración 30 minutos y consumo transaccional único. Claves con
bcrypt costo explícito 10 y password_verify. Rotar sesión al autenticar y al cambiar clave.
Cookies Secure/HttpOnly y SameSite=Lax mediante encabezado compatible con PHP7.0;
no usar la firma de opciones de cookie añadida en versiones posteriores.
[random_bytes](https://www.php.net/manual/en/function.random-bytes.php),
[password_hash](https://www.php.net/manual/en/function.password-hash.php),
[hash_equals](https://www.php.net/manual/en/function.hash-equals.php).

## R04 — RN-06 y RN-12 bajo concurrencia

**Decisión:** Inserción condicionada por existencia en la fuente indicada en una sola
sentencia preparada, con tabla elegida por mapa cerrado TITULO/SOLICITUD. Si no hay fila
fuente, no se inserta vínculo. La condición se evalúa en la instantánea de la sentencia.
Un borrado posterior del sincronizador produce una asociación histórica sin fuente actual,
no una reparación o borrado automático. La unicidad proyecto/código es una restricción,
no un chequeo previo susceptible a carreras. SQLSTATE 23505 de la restricción indicada
se traduce a 409. Una excepción exige rollback antes de continuar.
**Alternativas:** FKs a dos tablas opcionales y CHECK con subconsulta no expresan el contrato.
No bloquear filas catastrales FOR SHARE/UPDATE: PostgreSQL9.5 exige privilegio UPDATE
para esos bloqueos y sgpm_user solo tiene SELECT en las fuentes.
[Restricciones PostgreSQL9.5](https://www.postgresql.org/docs/9.5/ddl-constraints.html),
[GRANT PostgreSQL9.5](https://www.postgresql.org/docs/9.5/sql-grant.html),
[INSERT PostgreSQL9.5](https://www.postgresql.org/docs/9.5/sql-insert.html).

## R05 — Vecinas, color y edición concurrente

**Decisión:** Identidad compartida empresa_vecina y enlace proyecto_empresas_vecinas con
ordinal monotónico, color único y patrón por vínculo. Bloquear el proyecto propio FOR UPDATE
al asignar ordinal/color; contador persistente evita reutilizar ordinals eliminados.
Ocho tonos iniciales, luego tonos violetas adicionales deterministas que no colisionen
con los usados, más rayado diagonal SVG desde el ordinal nueve. No reciclar colores.
No usar una paleta de ocho colores repetida porque la constitución exige unicidad de color.

El Geólogo crea identidades nuevas o reutiliza por NIT conocido, pero solo modifica
identidad existente si no afecta empresas ajenas; el diseño adopta la regla más simple:
identidad existente editable solo por Administrador; notas del proyecto van al vínculo.
Ediciones de entidades propias usan version de fila y conflicto 409 para evitar pérdida
silenciosa de cambios. Roles y membresías se revalidan en cada solicitud.
[Bloqueos PostgreSQL9.5](https://www.postgresql.org/docs/9.5/explicit-locking.html).

## R06 — Auditoría inmutable y ámbito histórico

**Decisión:** Triggers de entidades maestras insertan snapshots saneados en la misma
transacción. Propietario sgpm_owner distinto de sgpm_user; runtime sin propiedad, DDL,
TRIGGER ni UPDATE/DELETE/TRUNCATE/INSERT directo de auditoría. Funciones SECURITY DEFINER
con search_path fijo, nombres calificados y EXECUTE revocado de PUBLIC. LOGIN/LOGOUT
mediante función append de esquema y campos cerrados. Contexto usuario/IP/empresa fijado
por el servidor dentro de la transacción. Si falla el registro, falla el cambio.

Añadir empresa_id_contexto y proyecto_id_contexto históricos, sin FK al objeto mutable,
para conservar filtros tras eliminaciones. usuario_id mantiene FK RESTRICT: usuarios con
historial se desactivan. No usar ON DELETE SET NULL sobre eventos existentes. Cambios de
identidad vecina y usuario son globales; cambios de vínculos y membresías tienen ámbito
empresarial. Nunca guardar hash de clave, tokens ni APIKEY/USERKEY en snapshots o logs.
La protección impide mutación desde la aplicación, no pretende impedir al propietario DBA
alterar físicamente su base. **Alternativa:** log solo de controladores deja rutas de omisión.
[Triggers PostgreSQL9.5](https://www.postgresql.org/docs/9.5/trigger-definition.html),
[CREATE FUNCTION PostgreSQL9.5](https://www.postgresql.org/docs/9.5/sql-createfunction.html).

## R07 — Correo corporativo y trabajos locales

**Decisión:** Conservar clase y método obligatorios curl_download(url, params): string,
POST form-urlencoded y TLS verificado (peer y hostname). Añadir lectura del HTTP status
antes de cerrar el handle; el adaptador devuelve clasificación separada conservando el
retorno string obligatorio. Timeout de conexión 10s y total 30s. No usar SMTP ni seguir
redirecciones a hosts arbitrarios con credenciales.
[curl_getinfo](https://www.php.net/manual/en/function.curl-getinfo.php).

No existe un contrato documentado de respuesta del proveedor en los materiales recibidos.
Decisión de interfaz: estados PENDIENTE, ACEPTADO, FALLIDO e INDETERMINADO; HTTP 2xx no
significa entrega. Solo un cuerpo positivo reconocido por un perfil de respuesta verificado
permite ACEPTADO. Sin perfil, respuesta desconocida o timeout queda INDETERMINADO y no
se reintenta automáticamente; 4xx/5xx o error previo al despacho queda FALLIDO cuando
se puede demostrar rechazo. No inventar JSON ni prometer ENTREGADO. La integración real
requiere muestras anonimizadas o documentación del proveedor antes de aceptación productiva.
Puede implementarse y validarse el contrato local con doble HTTP sin esa dependencia.

Outbox PostgreSQL y ejecutor CLI por cron en el mismo host: no bloquea peticiones hasta
30s. Bienvenida y recuperación generan evento tras commit; el token solo vive cifrado
mientras está pendiente de despacho. Reportes: Administrador configura horario diario
09:00 America/Bogota por defecto (desactivado hasta elegir destinatarios y empresa).
Gerente/Inversionista/Administrador pueden recibir reportes que podrían exportar en ese
instante; no se envían a direcciones libres de terceros. Alertas de auditoría al Administrador
sobre cambios de roles, desactivaciones y borrados maestros, agrupadas por minuto.
No alertar por cambios en la propia outbox: evita recursión.

## R08 — Geometría, medidas e importación

**Decisión:** Catastro MultiPolygon4326 inmutable, fechas DATE; centroides Point4326 con
GiST y cálculo PostGIS usando proyección métrica equivalente sobre geometrías propias.
Área oficial AREA_HA, o ST_Area(the_geom::geography)/10000.0. No añadir endpoint de
validación, reparación o edición de límites ni ST_IsValid/ST_MakeValid por petición.
Turf provee análisis/centroides visuales; no reemplaza el centroide oficial persistido.
ST_Area geográfica en PostGIS2.2 puede usar GeographicLib si PROJ >=4.9.0; comprobar
versiones instaladas sin suponer que la distribución reúne ese requisito.
[ST_Area PostGIS2.2](https://postgis.net/docs/manual-2.2/ST_Area.html).

Importación de capas de trabajo por parser temporal de formato, sin persistencia catastral:
GeoJSON vía JSON, KML vía DOM/XML sin entidades externas y ZIP Shapefile por Reader.
Límites propuestos: 20MiB comprimidos, 100MiB expandidos, 5000 objetos/500000 vértices.
Se acepta .prj WGS84/EPSG4326; se rechaza otro CRS con instrucción de convertir el archivo,
sin transformar ni corregir límites oficiales. Capas y color/opacidad viven en el navegador.
**Alternativa:** aceptar archivos sin límite o tratar importación como escritura catastral.

## R09 — Rendimiento y aceptación reproducible

**Decisión:** Conjunto estándar 500 polígonos, hasta 50000 vértices; masivo 5000 polígonos,
hasta 500000 vértices, fuentes con 100000 registros totales. Fixtures deterministas y
manifiesto de tamaños/checksums; no usar solo cuadrados triviales. Servidor de referencia
4 vCPU, 8GiB RAM, SSD; cliente 4 núcleos, 8GiB, navegador moderno con módulos ES nativos;
red 20Mbps, RTT50ms. Verificar también sobre servidor real y registrar diferencias.

50 sesiones durante 10 minutos después de 2 minutos de calentamiento: 60% búsqueda,
20% mapa, 10% Identify, 5% edición, 5% solicitud de exportación; pausa 1–3s por usuario.
Medir todas las muestras, máximo y p95; límites estrictos sobre solicitudes válidas del
conjunto, no aprobar solo por p95. Mapa <=500 <5s, masivo <8s, búsquedas <3s y etiquetas
locales <200ms. Exportación es trabajo asincrónico y su petición entra en consulta estándar,
pero tiempo de generación se registra aparte sin inventar un SLA funcional.

Reducir datos a bbox, paginar, separar ficha/geom, compresión HTTP y liberar bloqueo de
sesión PHP antes de consulta larga. Tooltip se resuelve desde propiedades ya cargadas.
SVG para patrones, Canvas para capas sin patrón y agrupación de inserciones sin bloquear UI.
No simplificar o truncar polígonos silenciosamente para aprobar tiempos. La medición
real queda para implementación: este documento define el experimento, no declara métricas logradas.

## Resultado de fase 0

Decisiones de diseño resueltas. La selección de archivos exactos de dependencias y la
conformidad real del correo son verificaciones externas de implementación/aceptación,
registradas explícitamente, no supuestos de compatibilidad o entrega ya cumplidos.
