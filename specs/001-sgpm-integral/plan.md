# Implementation Plan: Gestión integral de proyectos mineros SGPM

**Branch**: `plan` (rama Git observada) | **Date**: 2026-10-11 | **Spec**: [spec.md](spec.md)

**Feature directory**: `specs/001-sgpm-integral`. El helper reporta `001-sgpm-integral`
como identificador de feature; no se renombra ni crea una rama durante esta planificación.
**Input**: Requisitos 4.1.0-SGPM, decisiones de clarify y constitución vigente 4.2.0-SGPM.

## Summary

Diseñar los ocho módulos del SGPM como aplicación web monolítica modular: autenticación
por sesión y RBAC, empresas y proyectos, consulta catastral externa, vecinas compartidas,
visor Leaflet, exportaciones y auditoría. PHP controla autorización y transacciones;
VanillaJS ES2015 controla interacción y Bootstrap4.6 el aspecto responsive.

La identidad vecina se separa de su vínculo por proyecto. Las asociaciones dependen del
catastro de solo lectura y de una restricción única por proyecto/código. Triggers escriben
auditoría transaccional con ámbito empresarial histórico. Un ejecutor CLI local procesa
exportaciones y correo corporativo por HTTPS; no hay SMTP ni servicios distribuidos.

El repositorio contiene actualmente artefactos SDD y scripts, sin aplicación implementada.
Los directorios de código y comandos del quickstart son el objetivo para la fase implement,
no archivos ejecutables que esta fase haya creado. No se genera tasks.md en este comando.

## Technical Context

**Language/Version**: PHP7.0.33 estricto; JavaScript propio ES6/ECMAScript2015, módulos
nativos, sin async/await, optional chaining ni sintaxis posterior; SQL PostgreSQL9.5.24.

**Primary Dependencies**: Apache2.4.18; Bootstrap4.6.2 CSS; Leaflet1.9.4, esri-leaflet,
GoogleMutant, Leaflet.draw, Leaflet.Control.Measure, Turf; tFPDFv1.33 y adaptador
php-shapefile v3.4.1 con artefactos exactos auditados antes de integrarlos. JS distribuido sin
build en producción; XLSX con ZipArchive/XMLWriter, CSV/GeoJSON/KML con PHP nativo.

**Storage**: PostgreSQL9.5.24 y PostGIS2.2.x en 127.0.0.1:5432; acceso exclusivo PDO pgsql.
Fuentes externas de 30 columnas solo SELECT; tablas internas propias en esquema sgpm.
Archivos privados temporales de exportación, logs diarios y sesiones fuera del DocumentRoot.

**Testing**: Lint con binario real PHP7.0.33; runner CLI PHP sin dependencia de PHPUnit
moderno; integración en PostgreSQL9.5.24/PostGIS2.2; pruebas de contrato HTTP, doble de
correo local y navegadores de escritorio/tablet/móvil. Pruebas de carga desde equipo
externo a producción; ninguna herramienta de build es requisito de ejecución del servidor.

**Target Platform**: Un Ubuntu16.04LTS64bit, Apache2.4.18 con mod_rewrite/mod_ssl/mod_headers,
HTTPS443, PHP mediante módulo o FPM. Extensiones pgsql/pdo_pgsql, curl, json, mbstring,
xml/XMLWriter, zip; verificar también openssl y fileinfo para payloads privados y archivos.
No se presupone disponibilidad de estos binarios en el workspace Windows actual.

**Project Type**: Aplicación web SSR con API JSON privada same-origin y frontend modular.

**Performance Goals**: Consultas <3s; mapa de 500 polígonos <5s; masivo de 5000 polígonos
<8s; tooltip local <200ms; 50 usuarios concurrentes; latencia PHP–DB objetivo <1ms.
Conjuntos, complejidad, red y protocolo se fijan en [quickstart.md](quickstart.md).

**Constraints**: Todo script PHP distribuido comienza con strict_types=1, incluido vendor;
las adaptaciones necesarias se conservan como parches reproducibles y se prueban en7.0.
Sin SQL9.6+, frameworks SPA, SMTP ni endpoints topológicos. Sin escritura catastral.
UTF-8 integral, HTTPS/301/HSTS, PDO preparado con tipos, CSRF, escape XSS y bcrypt≥10.

**Scale/Scope**: Ocho módulos, cuatro roles; identidad vecina reutilizable N:M.
Fixtures de aceptación: 100000 filas catastrales, 500/5000 polígonos y 50 sesiones.
Estas cifras fijan una prueba de aceptación, no límites comerciales de almacenamiento.

## Constitution Check

*GATE: revisión previa a fase 0 y posterior a fase 1. PASS significa diseño conforme,
no despliegue o rendimiento verificados.*

| Ley / obligación | Evidencia del diseño | Antes | Después |
| --- | --- | --- | --- |
| Ley I: host y versiones | Contexto y quickstart; PHP7.0/Apache2.4.18/PostgreSQL9.5.24/PostGIS2.x | PASS | PASS |
| PDO exclusivo y SQL compatible | Repositorios PDO; sin pgsql2shp ni conexiones alternas | PASS | PASS |
| VanillaJS/Bootstrap4.6 | CSS Bootstrap y módulos ES2015; sin bootstrap.js/jQuery ni build productivo | PASS | PASS |
| Geometrías4326/GiST y enmienda4.2 | Catastro MultiPolygon; centroide Point con GiST | PASS | PASS |
| RN-01 a RN-06 | Empresa única, enlaces N:M, fuentes externas SELECT y existencia por tipo | PASS | PASS |
| RN-07/RN-08 y RF-05.8 | Ordinal por proyecto, color único y patrones desde la novena vecina | PASS | PASS |
| RN-09 a RN-11 | Tooltips locales y Identify de 30 atributos más filiación | PASS | PASS |
| RN-12 | UNIQUE(proyecto_id,codigo_expediente), filiación y FK compuesta | PASS | PASS |
| RN-13 y RNF-24 | AREA_HA o ST_Area(geography)/10000; medición visual separada | PASS | PASS |
| Cuatro roles / bcrypt | Administrador global; demás por membresía, bcrypt costo10 | PASS | PASS |
| Correo LibCurlModel | POST HTTPS, cinco parámetros, timeout10/30, sin SMTP | PASS | PASS |
| Auditoría integral | Triggers, snapshots saneados, contexto histórico y runtime sin mutación | PASS | PASS |
| CSRF/XSS/SQLi/HTTPS y errores | Contratos de middleware, límites, códigos HTTP y logs privados | PASS | PASS |

Sin excepciones constitucionales. La conformidad del proveedor de correo y compatibilidad
de los archivos vendor exactos son puertas de aceptación productiva explícitas. Se pueden
implementar adaptadores y pruebas locales sin afirmar esas verificaciones como realizadas.

## Project Structure

### Documentation (this feature)

```text
specs/001-sgpm-integral/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── http-api.md
│   ├── email-service.md
│   └── map-and-exports.md
└── checklists/requirements.md
```

tasks.md se generará después con speckit-tasks.

### Source Code (repository root)

Estructura propuesta, aún no creada:

```text
public/
├── index.php
└── assets/
    ├── css/
    ├── js/modules/
    └── vendor/
src/
├── Http/                 # Router, controladores, respuestas y middleware
├── Auth/                 # Sesión, CSRF, RBAC y recuperación
├── Modules/
│   ├── Usuarios/
│   ├── Empresas/
│   ├── Proyectos/
│   ├── Catastro/
│   ├── Vecinas/
│   ├── Visor/
│   ├── Reportes/
│   └── Auditoria/
├── Infrastructure/       # PDO, transacciones, LibCurlModel, archivos y reloj
└── Jobs/                 # Outbox, cron y exportaciones
views/                    # HTML escapado y componentes Bootstrap4
config/                   # config.php y ejemplo sin secretos, fuera de public
vendor/                   # PHP dependencias auditadas, empaquetadas fuera de producción
bin/                      # Migración, bootstrap admin, worker y fixtures de prueba
migrations/               # SQL9.5 interno; nunca reemplaza catastro
storage/                  # Sesiones, logos y exportaciones privadas, sin ejecución
logs/                     # sgpm-YYYY-MM-DD.log
ops/                      # Guías/configuración de Apache y cron para fase implement
tests/
├── unit/
├── integration/
├── contract/
├── browser/
├── performance/
└── fixtures/
```

**Structure Decision**: Un solo paquete desplegable, DocumentRoot public. Controladores
orquestan servicios; repositorios concentran PDO y filtros; triggers resguardan invariantes.
Interfaz recibe únicamente DTO autorizados. Utilizar carga de clases simple compatible7.0,
sin contenedor o framework obligatorio. Eliminar dependencias no usadas del paquete.

## Diseño y secuencia de entrega

1. Fundamentos: estructura, configuración segura, PDO, migraciones internas, control de
   errores, sesión/CSRF, roles, auditoría y usuario Administrador inicial por CLI.
2. Usuarios/empresas/membresías y recuperación/bienvenida por outbox; integraciones con
   dobles verificables. No esperar la cartografía para probar acceso y aislamiento.
3. Proyectos y consulta catastral, asociaciones RN-06/RN-12, identidad vecina/enlaces,
   colores y control de cambios concurrentes.
4. Visor, proveedores, tooltips/Identify, área oficial/mediciones, capas de trabajo.
5. Reportes tabulares/espaciales y jobs, permisos al ejecutar y descargar, auditoría
   consultable por empresa, reportes programados y alertas corporativas.
6. Validación integral: compatibilidad real del runtime y vendor, pruebas de aislamiento,
   rollback/auditoría/concurrencia, archivos, carga y configuración en host de aceptación.

El posterior tasks debe desglosar estas entregas por historia y dependencias, manteniendo
los rangos RF oficiales sin inventar subcódigos no definidos.

## Matriz de trazabilidad del diseño

| Requisitos | Diseño | Validación |
| --- | --- | --- |
| RF-01.1–01.6, RNF-10/13 | Auth, Usuarios, empresa_usuarios, recuperación/outbox | Quickstart Q1/Q2/Q8 |
| RF-02.1–02.5 | empresa, RBAC global y logos privados | Q2 y Q10 |
| RF-03.1–03.6, RN-01/06/12 | proyecto, asociaciones, transacciones y restricciones | Q3/Q4 |
| RF-04.1–04.5, RNF-19/20 | Catastro SELECT, adaptador 30 atributos, índices | Q3/Q5 |
| RF-05.1–05.8, RN-04/07/08 | Identidad/vínculo, contador, color/patrón | Q4/Q6 |
| RF-06.1–06.22, RN-09/10/11/13 | Contrato mapa, capas, Identify y medidas | Q5/Q6/Q10 |
| RF-07.1–07.4 | Exportación y programación con reautorización | Q7/Q8 |
| RF-08.1–08.3 | Triggers, función append y contexto empresarial histórico | Q9 |
| RNF-01–04/11/12/14–18 | Runtime, middleware, logs y contratos | Q0/Q1/Q8/Q10 |
| RNF-05–09 | Protocolo medido y fixtures realistas | Q11 |

## Complexity Tracking

Sin violaciones constitucionales. La outbox y los triggers se justifican por efectos
externos de hasta30s y auditoría atómica. Son componentes del mismo proceso/host y base,
no servicios adicionales. Se evita RLS complejo para toda la aplicación: filtros de ámbito
obligatorios y pruebas de acceso directo verifican el contrato RBAC definido.

## Riesgos y condiciones de aceptación

- Las versiones son obligatorias; preparar un paquete reproducible y comprobarlo sobre
  los binarios reales. Esta fase no actualiza SO, PHP o base ni certifica soporte upstream.
- Correo: sin muestra contractual verificada no se marca ACEPTADO/ENTREGADO. Bloquea la
  aceptación de despacho real, no el diseño de la integración con doble.
- Proveedores de mapas: claves, licencias y conectividad se verifican en aceptación;
  fallos no deben inutilizar OSM ni la consulta de atributos.
- KML3D no inventa elevaciones: sin Z se usa clampToGround y se informa su limitación.
- Shapefile limita nombres DBF: contrato fija un manifiesto reversible y conserva los
  nombres completos en exportaciones GeoJSON/KML/tabulares.
- Rendimiento depende de geometría, red y equipo: se mide, no se da por logrado.

**Resultado fase1:** diseño y contratos completos; siguiente comando speckit-tasks.
