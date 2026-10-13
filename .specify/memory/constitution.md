<!--
Sync Impact Report
Version: 4.2.0-SGPM -> 5.0.0-SGPM.
MAJOR: sustitucion de la version obligatoria PostgreSQL y fijacion exacta de PostGIS.
Principio modificado: Motor Espacial y Base de Datos.
PostgreSQL 9.5.24/PostGIS 2.x -> PostgreSQL 9.5.25/PostGIS 2.5.5.
Motivo: indisponibilidad de versiones anteriores indicada por el responsable.
Autorizacion: instruccion del usuario del 2026-10-12: ahora deben ser estas versiones.
Secciones anadidas/eliminadas: ninguna; registro de enmienda agregado a Governance.
Seguimiento: sincronizar spec, plan, research, data-model, quickstart, tasks,
README, ops/acceptance-environment.md y FoundationIntegrityTest.php.
Conservar evidencia historica; repetir compatibilidad/integracion antes de cerrar T020.
La conexion local y las demas restricciones siguen vigentes.
No se modifican plantillas, codigo, pruebas, contenedores ni datos en este paso.
Placeholders pendientes: ninguno. Informe temporal: retirar antes del commit.
-->

# Sistema de Gestión de Proyectos Mineros (SGPM) Constitution

**CONSTITUCIÓN TÉCNICA DEL SISTEMA**  
**Metodología:** Spec-Driven Development (SDD)  
**Versión:** 5.0.0-SGPM (Actualizada)
**Estado:** INMUTABLE / OBLIGATORIA  
**Fecha de Emisión:** 2026-09-24

Esta Constitución establece las reglas de ingeniería inmutables que gobiernan el desarrollo,
evolución, despliegue y auditoría del SGPM. Su cumplimiento es obligatorio y tiene supremacía
sobre cualquier requerimiento o implementación.

## Core Principles

### Ley I: Fidelidad Estricta al Stack Tecnológico e Infraestructura de Servidor

- La infraestructura DEBE ser mononodo, en un único servidor físico o VPS con
  **Ubuntu 16.04 LTS (Xenial Xerus)** y **Apache/2.4.18**. Apache DEBE proporcionar HTTPS
  nativamente, con certificados SSL/TLS activos y forzados mediante redirección 301 y HSTS.
- El backend DEBE ejecutar estrictamente **PHP 7.0.33**, mediante
  `libapache2-mod-php7.0` o `php7.0-fpm`. Se PROHÍBEN sintaxis y funciones introducidas
  en PHP 7.1 o superior: retornos `void`, tipos nulables `?string`, desestructuración
  simétrica `[$a, $b] = $arr`, `catch (A | B $e)`, `??=`, funciones flecha `fn()` y
  propiedades de clase tipadas. Todo script PHP DEBE iniciar con
  `declare(strict_types=1);` inmediatamente después de la apertura PHP.
- La persistencia DEBE utilizar exclusivamente **PDO PostgreSQL (`pdo_pgsql`)**,
  con sentencias preparadas y binding tipado contra inyección SQL.
- El frontend DEBE utilizar **VanillaJS (ES6 / ECMAScript 2015)** y **Bootstrap 4.6**.
  Se PROHÍBEN frameworks SPA pesados (React, Vue, Angular) y pipelines complejos de
  compilación en producción. JavaScript DEBE ser nativo y modular sin transpilación.
  La interfaz DEBE ser responsive, limpia y adaptada a la cuadrícula, componentes y
  utilidades visuales de Bootstrap 4 (RNF-15, RNF-16).

### Motor Espacial y Base de Datos

- La base de datos DEBE ser **PostgreSQL 9.5.25** con **PostGIS 2.5.5**, coexistente en
  el mismo servidor Ubuntu 16.04, mediante socket UNIX o TCP loopback `127.0.0.1:5432`.
  Se PROHÍBE sintaxis posterior a PostgreSQL 9.5.25; se permite `ON CONFLICT DO UPDATE`.
- Las columnas espaciales DEBEN ser `GEOMETRY(MultiPolygon, 4326)` o
  `GEOMETRY(Geometry, 4326)`. Para centroides de proyectos se permite expresamente
  `GEOMETRY(Point, 4326)`. Todas estas columnas DEBEN disponer de índices espaciales
  **GiST** (RNF-23).
  En las tablas catastrales externas, la columna geométrica oficial es `the_geom`.
- El cálculo oficial de hectáreas en Colombia DEBE ejecutarse en PostGIS mediante
  `ST_Area(the_geom::geography) / 10000.0` o transformación oficial MAGNA-SIRGAS
  `ST_Transform(the_geom, 3116)` con conversión de metros cuadrados a hectáreas
  (RN-13, RNF-24).

### Visor Cartográfico Geoespacial

El visor DEBE utilizar **Leaflet 1.9+** y su ecosistema:

- `esri-leaflet`: capas satelital y topográfica de ESRI.
- `GoogleMutant`: capas oficiales de Google Maps de calles, satélite, híbrido y terreno.
- `Leaflet.draw` y `Leaflet.Control.Measure`: medición interactiva de distancias y áreas
  métricas y colocación de marcadores.
- `Turf.js`: cálculo de centroides y análisis geodésico en cliente.

DEBE admitir importación de Shapes (`.zip`), GeoJSON y KML, con control de color y
transparencia (RF-06.21, RF-06.22).

### Manejo de Errores y Validaciones

La UI NUNCA DEBE exponer errores crudos ni stack traces. Todo error técnico DEBE
traducirse a un mensaje amigable, por ejemplo: «La empresa XXX ya ha sido creada».
El backend DEBE retornar siempre códigos HTTP semánticos: 400 para petición inválida,
401 para no autenticado y 409 para conflicto de reserva.

## Leyes de Dominio y Reglas Inmutables de Negocio (RN)

1. **RN-01 — Pertenencia Única:** Cada proyecto DEBE pertenecer a una única empresa
   titular (`empresa_id`).
2. **RN-02 / RN-03 / RN-05 — Multiplicidad de Asociaciones:** Un proyecto PUEDE tener
   múltiples expedientes (títulos y solicitudes) y múltiples empresas vecinas
   (competencia). Cada empresa vecina PUEDE tener múltiples expedientes.
3. **RN-04 — Reutilización de Vecinas:** Una empresa vecina PUEDE vincularse a varios
   proyectos, incluso de diferentes empresas matrices.
4. **RN-06 — Integridad Geográfica y Fuentes Externas:** Un expediente SOLO PUEDE
   asociarse a un proyecto o empresa vecina si existe previamente en
   `geo_titulos_mineros` o `geo_solicitudes_mineras`. Ambas tablas DEBEN residir en la
   misma base de datos y ser pobladas externamente por un proceso independiente de
   sincronización catastral. DEBEN compartir una estructura estandarizada de 30 columnas
   con la geometría almacenada en `the_geom`.
5. **RN-07 / RN-08 / RF-05.5 — Cromatismo de Competencia:** El sistema DEBE asignar
   automáticamente un tono violeta secuencial a cada empresa vecina según su orden de
   registro dentro del proyecto. El color DEBE ser único dentro del proyecto. Todos los
   expedientes de la misma empresa vecina DEBEN heredarlo. Al superar ocho tonos violetas,
   el sistema DEBE alternar patrones de relleno (hatching / rayado diagonal SVG) para
   preservar la distinción visual (RF-05.8).
6. **RN-09 / RN-10 — Etiquetado al Pasar el Cursor:** Un expediente propio DEBE mostrar
   `Proyecto: {nombre} ({código expediente})`. Uno de empresa vecina DEBE mostrar
   `{nombre de la empresa competencia}`.
7. **RN-11 / RF-06.12 — Panel de Identificación (Identify):** El detalle desplegado por
   clic DEBE mostrar siempre los atributos completos de la fuente: código de expediente,
   modalidad, título estado, área en ha, clasificación de minería, etapa, solicitantes
   o titulares, minerales, fechas de solicitud/expedición/aniversario/expiración,
   municipios, departamentos y filiación (propio o nombre de la empresa vecina).
8. **RN-12 — Exclusividad en el Proyecto:** Un expediente SOLO PUEDE asociarse una vez
   dentro del mismo proyecto. Se DEBE impedir que figure simultáneamente como propio
   y de la competencia.
9. **RN-13 — Cálculo Preciso:** El área en hectáreas DEBE tomarse del atributo oficial
   `AREA_HA` o validarse geodésicamente sobre `the_geom`.

## Leyes de Seguridad, Notificaciones y Auditoría

1. **Autenticación y RBAC (RF-01.3):** El acceso DEBE controlarse según los cuatro roles
   formales: `Administrador`, `Gerente`, `Geólogo`, `Inversionista`. Las contraseñas
   DEBEN almacenarse mediante `password_hash($pass, PASSWORD_BCRYPT)` con costo
   mayor o igual a 10 (RNF-10).
2. **Servicio Centralizado de Notificaciones por Correo:** El despacho de correos
   transaccionales DEBE delegarse al endpoint corporativo seguro
   `https://sigmin.automatizzar.com/admin_emails/sendEmail.php`. La invocación DEBE
   realizarse mediante `LibCurlModel` con cURL en PHP 7.0.33 y los parámetros
   obligatorios `USERKEY`, `APIKEY`, `email`, `asunto` y `mensaje`.
3. **Auditoría Forense Integral (RF-08.1 / RF-08.2 / RF-08.3):** Toda inserción,
   actualización o borrado en entidades maestras DEBE registrar `usuario_id`, `accion`,
   `entidad`, `valores_anteriores` (JSON), `valores_nuevos` (JSON), `ip` y `fecha`.
4. **Protección Web (RNF-11 / RNF-12):** Se DEBEN aplicar Apache 2.4 con HTTPS forzado,
   tokens de sesión contra CSRF, consultas parametrizadas con PDO contra SQLi y
   saneamiento en VanillaJS contra XSS.

## Governance

Esta Constitución prevalece sobre especificaciones, planes, tareas e implementaciones.
Sus leyes son inmutables y obligatorias durante la vigencia de esta versión. Ningún
requerimiento o excepción local PUEDE dispensar su cumplimiento.

Toda especificación, plan, revisión de cambios y revisión de despliegue DEBE verificar
el stack, las reglas RN y las referencias RF/RNF aplicables. Los incumplimientos DEBEN
corregirse antes de aprobar el artefacto o desplegarlo.

Toda propuesta de enmienda DEBE documentar motivo, leyes afectadas, impacto y plan de
migración. Solo la aprobación explícita del responsable del proyecto PUEDE ratificar
una nueva versión; hasta entonces permanece íntegramente vigente la versión actual.

El versionado DEBE seguir MAJOR.MINOR.PATCH con sufijo `-SGPM`: MAJOR para eliminación
 o redefinición incompatible de leyes; MINOR para nuevas leyes o ampliaciones materiales;
PATCH para aclaraciones sin cambio normativo. Toda enmienda ratificada DEBE actualizar
versión y fecha de modificación, preservando la fecha de ratificación original.
La versión inicial registrada es la `4.1.0-SGPM` proporcionada por el usuario.

**Enmienda ratificada — 2026-10-11:** El responsable del proyecto aprueba la opción B
para AC-03: permitir GEOMETRY(Point, 4326) en centroides de proyectos. La versión
4.2.0-SGPM amplía de forma compatible los tipos permitidos. No cambia las geometrías
MultiPolygon catastrales ni la obligación de índices GiST. Las migraciones futuras DEBEN
usar el contrato puntual para Proyecto.centroide; esta enmienda documental no ejecuta
cambios de base de datos. La ratificación original permanece en 2026-09-24.
**Enmienda ratificada — 2026-10-12:** El responsable del proyecto establece
PostgreSQL 9.5.25 y PostGIS 2.5.5 por problemas de disponibilidad de las versiones
anteriores. La version 5.0.0-SGPM sustituye la exigencia de PostgreSQL 9.5.24 y fija
PostGIS 2.5.5 como version exacta. Se conserva la prohibicion de SQL posterior a
PostgreSQL 9.5 y el acceso exclusivo mediante PDO. Esta enmienda no modifica PHP,
Apache, sistema operativo, conexion local, tipos espaciales, GiST ni reglas de negocio.

La migracion documental DEBE sincronizar especificacion, plan, investigacion, modelo,
quickstart, tareas y guias de aceptacion con estas versiones. Las validaciones de runtime
DEBEN comprobar PostgreSQL 9.5.25 y PostGIS 2.5.5. El codigo existente DEBE volver a
validarse; esta enmienda no certifica compatibilidad ni cierra tareas pendientes.
Las evidencias anteriores DEBEN conservarse como historicas y complementarse con
resultados nuevos. Los cambios de infraestructura DEBEN preservar los datos existentes
y ejecutar fixtures unicamente en una base aislada de pruebas.

**Version**: 5.0.0-SGPM | **Ratified**: 2026-09-24 | **Last Amended**: 2026-10-12

