# Quickstart de validación — SGPM integral

**Fecha de diseño:** 2026-10-11. **Actualización:** 2026-10-13, constitución 5.0.0-SGPM.
Fundamentos y backend de acceso implementados; resultados en `ops/evidence/us1.md`.
Las secciones de módulos posteriores siguen siendo escenarios de aceptación futuros.
No usar estos pasos contra datos productivos.

Referencias: [plan](plan.md), [modelo](data-model.md), [HTTP](contracts/http-api.md),
[correo](contracts/email-service.md), [mapa/exportaciones](contracts/map-and-exports.md).

## Q0 — Preparación del entorno de aceptación

Usar un host de pruebas Ubuntu16.04LTS64bit con Apache2.4.18, PHP7.0.33 y PostgreSQL9.5.25
con PostGIS2.5.5; no aceptar que PHP8 valide compatibilidad7.0. Apache tiene rewrite/ssl/headers,
HTTPS443, redirección301 y HSTS. No instalar ni habilitar SMTP. DocumentRoot apunta a public.

Comprobaciones ejecutables del entorno, sin cambiar datos:

```sh
php -v
php -m
apache2 -v
apache2ctl -M
psql --version
```

Extensiones: PDO/pdo_pgsql, curl, json, mbstring, DOM/XMLWriter, zip, openssl y fileinfo.
La cuenta propietaria de pruebas consulta `SELECT version(), PostGIS_Full_Version();`
para verificar servidor, extensión, PROJ y GEOS reales; psql --version por sí solo no basta.

El implementador entrega config/config.example.php sin secretos; copiar a config/config.php
y cargar valores desde entorno del Apache/CLI. Mantener archivo fuera de public y acceso
restringido. Variables: SGPM_ENV, SGPM_DSN, SGPM_DB_USER, SGPM_DB_PASSWORD, SGPM_BASE_URL,
SGPM_EMAIL_MODE, SGPM_EMAIL_USERKEY, SGPM_EMAIL_APIKEY y claves de cifrado/MAC de outbox.
Correo inicia en modo double; nunca usar credenciales reales para tests. Configurar cron
con el mismo entorno seguro del worker, sin poner secretos en argumentos del proceso.

Crear por administrador DB una base aislada sgpm_acceptance, propietarios/roles separados
y las dos fuentes de fixture. El runtime sgpm_user tiene SELECT únicamente en esas fuentes;
no es dueño ni miembro del dueño. Las migraciones internas no crean/reemplazan fuentes reales.
El runner rechaza ejecutar fixtures destructivas si SGPM_ENV no es test o el nombre DB
no corresponde a una base de pruebas explícitamente configurada.

Comandos implementados (con configuración de pruebas preparada):

```sh
export SGPM_ENV=test
php bin/migrate.php --env=test
php bin/seed-fixtures.php --profile=sgpm-acceptance
php bin/check-compatibility.php
php tests/run.php --integration --contract
```

En Windows, `./ops/docker/test-foundation.ps1` prepara un entorno temporal separado
con las imágenes locales de `mi-app-php` y `mi-postgis`. PHP comparte el espacio de
red del PostgreSQL temporal y usa `127.0.0.1`, sin `host=db`. No utiliza la base
enterprise ni su volumen. Los comandos `--suite=...` de escenarios futuros todavía
no están implementados; el runner los rechaza expresamente.

Migración usa credenciales de migrador separadas; todas las consultas de aplicación y
herramientas PHP acceden por PDO. El CLI emite resumen sin credenciales y código de salida
no cero ante error. El seed actual entrega empresas A/B y cuatro roles. Los conjuntos
de vecinas, expedientes vinculados y rendimiento se completan en sus tareas posteriores.

Compatibilidad valida todos los archivos PHP con php -l del7.0.33, strict_types al inicio,
ausencia de sintaxis posterior y exportaciones smoke de vendor. JS propio y distribuciones
incluidas se analizan contra techo ES2015; el análisis corre fuera de producción. Dependencias
PHP sin declaración strict se adaptan mediante parche reproducible, conservando licencias,
checksums original/adaptado y smoke test antes de aceptación. No se omiten del control.

## Q1 — Acceso, sesión y recuperación

En HTTPS abrir /login con usuarios de fixture. Login activo correcto devuelve sesión,
actualiza ultimo_acceso y registra LOGIN; inválido/inactivo401 amigable sin trazas.
Omitir o cambiar CSRF en POST→403. Logout→204, cookie anterior pierde autorización y
se registra LOGOUT. Revocar membresía o desactivar usuario afecta la siguiente solicitud.

Recuperación de correo conocido y desconocido→202 con mensaje uniforme. El doble recibe
POST y cinco parámetros. Consumir token válido cambia bcrypt y auth_version; reutilizar,
expirar30min o cambiar token→400. Sesiones anteriores dejan de autorizar. Logs y snapshots
no contienen token ni password/hash. Confirmar límites de login/recuperación429.

## Q2 — RBAC por empresa y administración

Administrador administra usuarios/empresas/membresías y opera en A/B. No puede alterar
bitácora ni catastro, ni desactivar/degradar al último Administrador activo.
Gerente/Inversionista de A consultan proyectos/vecinas, exportan y leen auditoría de A;
operaciones de escritura→403. Geólogo de A mantiene proyectos/vínculos/expedientes y
consulta A; exportación/auditoría/administración de usuarios/empresas→403.
Acceder a IDs de B desde cualquier petición privada de A→404 indistinguible de inexistente.

```sh
php tests/run.php --suite=rbac
```

Probar todas las rutas y acceso directo, incluyendo mapa, Identify, jobs y descargas.
No limitar la prueba a botones ocultos. NIT/correo duplicados→409; fechas finales inválidas→400.
Borrados con dependencias→409; desactivar conserva historial. Actualización con version
obsoleta→409 sin sobrescribir datos.

## Q3 — Fuentes oficiales y existencia RN-06

Verificar ambas fuentes:30 columnas, tipos idénticos, cinco fechas DATE, código único,
the_geom MultiPolygon4326 NOT NULL y GiST. Buscar por los cinco filtros y cotejar ficha
con valores fixture. Acceder con sgpm_user e intentar INSERT/UPDATE/DELETE de prueba dentro
de transacciones revertidas→permission denied. No conceder UPDATE para probar locks.

Asociar código existente en fuente indicada→201; ausente o tipo incorrecto→400 sin vínculo
ni auditoría de una mutación inexistente. Retirar un expediente con el usuario externo
fixture y consultar vínculo→se conserva con fuente_disponible=false; otra nueva asociación
al código retirado se rechaza. No se ejecutan reparaciones/validaciones topológicas por petición.

## Q4 — Exclusividad y edición concurrente

Dos conexiones solicitan simultáneamente el mismo código en A/proyecto1, una PROPIO y
otra VECINO: una201 y otra409, una sola fila. Cambiar TITULO/SOLICITUD no elude la restricción.
El mismo código en proyecto2 sí puede asociarse. Una vecina vinculada solo a proyecto2
no puede atribuirse a proyecto1. Borrar vínculo con expedientes→409.

```sh
php tests/run.php --suite=concurrency
```

Dos altas vecinas concurrentes obtienen ordinal/color distintos. Eliminar un vínculo no
recicla ordinal. Cambio con version obsoleta retorna409. Fallo forzado del trigger de
auditoría en base aislada revierte la mutación, sin registro parcial ni outbox huérfana.

## Q5 — Mapa, Identify, área y bases

Abrir proyecto con500 polígonos conocidos. Verificar base OSM, las cuatro Google y dos ESRI,
atribución, leyenda azul/verde/violeta y controles responsive. Configuración real de claves
es requisito para verificar bases corporativas; el doble no certifica acceso al proveedor.
Hover muestra etiqueta RN-09/10 desde propiedades cargadas en<200ms; no hay fetch por hover.
Clic muestra30 atributos y filiación, incluyendo NULL explícito y fechas.

Comparar AREA_HA con fixture; caso sin área oficial usa cálculo geográfico PostGIS/10000.
Mediciones auxiliares se muestran métricas y no sustituyen valor oficial. El centroide
persistido es Point4326 con GiST y no campo editable desde HTTP. Leaflet.draw no permite
editar límites oficiales. Ningún POST/PUT de fuente geográfica es accesible.

## Q6 — Vecinas, patrones e importación

Crear nueve y luego17 vecinas en A/proyecto1. Colores únicos, orden estable y patrón
SVG desde novena; expedientes de la misma vecina heredan apariencia. Reutilizar identidad
en B/proyecto2 conserva identidad y permite otra simbología. Geólogo A cambia notas/color
asignado por nuevas altas en su vínculo sin alterar B; modificar identidad global→403.

Importar un archivo válido por formato con ZIP completo y .prj4326. Capa temporal se
visualiza con color/opacidad. Archivo corrupto, DTD/entidad externa, ZIP traversal,
expansión>100MiB, entrada>20MiB o CRS distinto se rechaza sin afectar capas previas.
No se escriben fuentes ni geometrías del proyecto. Temporales se limpian al terminar.

## Q7 — Reportes y exportación

Por Gerente/Inversionista A y Administrador: solicitar cada uno de PDF/XLSX/CSV/GeoJSON/KML/
Shapefile→202. Ejecutar worker de aceptación, esperar LISTO y descargar200.

```sh
php bin/worker.php --once --env=test
php tests/run.php --suite=exports
```

Abrir PDF con acentos, XLSX como libro OOXML real, CSV sin ejecución de fórmulas,
GeoJSON/KML con geometrías y Shapefile ZIP completo con manifiesto y mapeo DBF.
KML sin Z usa terreno y aviso, no altitudes inventadas. Probar texto largo DBF con sidecar.
Comparar códigos, filas y ámbito con consulta original. Geólogo→403 en solicitud.
Revocar membresía antes de generar o descargar impide obtener archivo. URL de job ajeno→404.
Caducidad24h→410 y limpieza de archivo; nunca descargar un archivo parcial.

## Q8 — Correo y programación local

```sh
php tests/run.php --suite=email-contract
php bin/scheduler.php --once --env=test
php bin/worker.php --once --env=test
```

Doble responde positivo conocido, negativo, desconocido,500 y timeout: clasificaciones
según contrato. Desconocido/timeout no produce éxito ni reenvío automático. Recuperación
expirada no se despacha. Bienvenida no incluye password. Alertas de roles/desactivación/
borrado se agrupan por minuto, no se recursan por actualizar outbox.

Administrador habilita reporte diario09:00 America/Bogota para A con destinatarios X.
Dos ejecuciones del scheduler en la misma fecha generan un reporte por destinatario,
sin duplicados. Revocar acceso evita su despacho; el enlace exige autenticación vigente.
Antes de aceptar correo real, incorporar perfil verificado del proveedor y evidencia de
POST con destinatario autorizado. No declarar ENTREGADO solo por un HTTP200.

## Q9 — Auditoría forense y consulta histórica

Crear/editar/borrar entidades maestras y comparar usuario, acción, entidad, snapshots,
IP, fecha, request_id y ámbito histórico. Conservar campos obligatorios aun tras eliminar
el objeto. A/gerente ve solo A; B y eventos globales quedan excluidos; A/geólogo→403.
Administrador consulta global. Intentos UPDATE/DELETE/TRUNCATE/INSERT directo del runtime
sobre auditoría fallan. No existe endpoint de mutación de bitácora.

```sh
php tests/run.php --suite=audit
```

Usuario con historial no puede borrarse, sí desactivarse. INSERT tiene snapshot anterior
NULL y DELETE posterior NULL. Mutación sin contexto de servidor o fallo de auditoría
revierte el cambio completo. Escanear snapshots/logs en busca de secretos fixture; cero hallazgos.

## Q10 — Transporte, interfaz, errores y archivos

HTTP redirige301 a HTTPS; certificado válido y HSTS. Intentos CSRF, XSS en nombres/
atributos/archivos y filtros SQLi se rechazan o muestran como texto sin ejecutar.
Provocar error500 y verificar alerta amigable con request_id y log privado del día,
sin stack trace al usuario. Config/storage/logs/vendor/bin no son servidos por Apache.
Logo válido PNG/JPEG pasa, archivo ejecutable/SVG no permitido y entrada>5MiB se rechazan.
No interpretar un logo como script ni permitir path de almacenamiento recibido del cliente.

Completar recorridos por teclado y con anchos360/768/1440, etiquetas y foco visible.
JSON/vistas/archivos preservan UTF-8; verificar Geólogo, municipios acentuados y nombres
largos. Usar los recorridos y resultados de este documento como guion de prueba navegador.

## Q11 — Protocolo de rendimiento

Referencia reproducible: servidor4vCPU/8GiB/SSD, cliente4núcleos/8GiB, navegador con módulos
ES nativos,20Mbps/RTT50ms. Registrar versión exacta, SO, CPU y límites PHP/Apache. Además
medir en host real si difiere. Fixtures versionadas:100000 registros oficiales totales;
mapa500 polígonos/≤50000 vértices y masivo5000/≤500000 vértices, sin simplificación oculta.
Guardar manifest de cantidad, distribución de vértices y checksum. Polígonos con huecos y
multiparte, fuentes de atributos completas; no aprobar solo usando rectángulos triviales.

50 usuarios de sesiones distintas, calentamiento2min y medición10min, pausas1–3s. Mezcla
60% búsqueda,20% mapa,10%Identify,5%edición,5%solicitud de exportación. La escritura usa
usuarios autorizados; lectura/exportación usan sus roles correctos. Generación de archivos
se mide aparte de la aceptación202 de su solicitud; no cuenta como consulta estándar.
Tres ejecuciones con caché fría/controlada y tres calientes, reportadas por separado.

```sh
php bin/seed-fixtures.php --profile=sgpm-performance
php tests/performance/run.php --users=50 --warmup=120 --duration=600
```

El runner de carga se ejecuta fuera del servidor objetivo; medición de dibujo/tooltip es
instrumentación de navegador, no tiempo PHP. Registrar solicitudes válidas, fallos, máximos
y p95; un p95 bueno no oculta muestras que incumplen umbrales estrictos de aceptación.
No usar la degradación de un proveedor externo como aprobación de carga de datos oficiales.

| Métrica | Resultado exigido |
| --- | --- |
| Consultas estándar, incluye Identify | Cada muestra válida<3s. |
| Mapa500 completo, del clic a última geometría dibujada | Cada carga<5s. |
| Capa5000 completa | Cada carga<8s. |
| Tooltip de propiedades cargadas | Cada muestra<200ms. |
| Concurrencia |50 sesiones sin fugas ni errores de integridad; mantiene umbrales. |
| Latencia local PHP–DB | Objetivo<1ms medido sin confundirlo con consulta geoespacial completa. |

Si un umbral no pasa, optimizar, repetir el caso afectado y registrar evidencia. No
reducir conjunto, omitir polígonos ni cambiar requisitos para declarar aprobación.

## Evidencia y salida

Cada suite genera exit code, resumen, versión de entorno y request_ids sin secretos.
Conservar reporte de compatibilidad, matriz RBAC, concurrencia, snapshots saneados,
lectura de30 atributos, archivos de muestra y resultados de carga. La aceptación productiva
requiere además perfil real de correo y claves/proveedores autorizados. Este quickstart
no registra pruebas ejecutadas: especifica cómo reproducirlas cuando exista la aplicación.
