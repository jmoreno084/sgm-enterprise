# Revision de pruebas pendientes T034–T200 — 2026-10-13

## Resultado de esta ejecucion

`./ops/docker/test-foundation.ps1`: **14 suites PASS, 0 FAIL**, PHP
7.0.33-0ubuntu0.16.04.16; compatibilidad exit 0, 74 archivos PHP revisados.
Salida en [pending-tests-baseline.txt](pending-tests-baseline.txt).
Base PostgreSQL 9.5.25/PostGIS 2.5.5 nueva, temporal y aislada, con seis
migraciones y fixtures. Correo double; no se invoco proveedor real.
No se recreo el ambiente persistente retirado ni se cambio la confianza TLS.

Se corrigio el paso de arrays en Windows PowerShell del runner: argumentos
nombrados `-PhpArguments`/`-DockerArguments` evitan perder las opciones de
contratos/integracion y de fixtures. La salida confirma las 14 suites ejecutadas.

Suites: AuthorizationTest, ConfigurationTest, HttpSecurityTest, SqlScriptTest,
AuthRbacTest, FoundationIntegrityTest, NotificationWorkerTest, RecoveryTest,
UserConcurrencyTest, AuthContractTest, AuthHttpTest, AuthHttpsTest,
EmailContractTest y EmailHttpContractTest.

## Alcance real del plan

El unico tasks.md encontrado define T001–T120. T121–T200 no estan definidas: no se
inventan tareas ni pruebas para ese rango. Dentro de T034–T120 hay 3 tareas
cerradas (T034–T036) y 84 pendientes (T037–T120). Las casillas pendientes
se conservan: aprobar las suites de US1 no certifica las historias posteriores.

T034–T036 conservan la evidencia Playwright anterior de 52 PASS/0 FAIL y
19 capturas en us1.md/us1-browser; no se repitio navegador en este intento.
Las suites HTTPS actuales verifican transporte/HTML/API; no se presentan como
nueva revision visual ni de teclado.

## Dependencias que impiden ejecutar el resto

Los unicos modulos funcionales y rutas implementados son Auth y Usuarios.
Faltan los modulos, rutas y vistas de empresas/proyectos, catastro, auditoria
consultable, vecinas, mapa y reportes. Sus archivos de pruebas tambien faltan.
Ejecutar solo `tests/run.php` no detecta esos archivos ausentes: descubre pruebas
existentes mediante glob. Por eso este inventario acompana al resultado verde.
T110–T119 requieren la aplicacion completa; el chequeo de compatibilidad actual
es parcial respecto de T110 y no permite cerrarla. T118 requiere contrato/muestras
autorizadas y autorizacion explicita para envios reales, que no se han aportado.

Para habilitar la aceptacion pendiente hay que implementar los modulos y crear
sus pruebas en el orden de dependencias del plan. Esta ejecucion verifica solo
las pruebas existentes y no implementa funcionalidades pendientes.

## Inventario por tarea

Los archivos faltantes se cotejaron con el filesystem. Una ruta existente no
implica que su funcionalidad este completa.

| Tarea | Estado | Archivos citados ausentes / condicion |
| --- | --- | --- |
| T034 | Completada previamente | Evidencia previa US1; regresion PHP actual aprobada. |
| T035 | Completada previamente | Evidencia previa US1; regresion PHP actual aprobada. |
| T036 | Completada previamente | Evidencia previa US1; regresion PHP actual aprobada. |
| T037 | Pendiente; no ejecutada como aceptacion | `tests/contract/ProjectsContractTest.php`, `tests/integration/ProjectFilesTest.php` |
| T038 | Pendiente; no ejecutada como aceptacion | `src/Modules/Empresas/CompanyRepository.php` |
| T039 | Pendiente; no ejecutada como aceptacion | `src/Modules/Empresas/CompanyService.php`, `src/Modules/Empresas/LogoStorage.php` |
| T040 | Pendiente; no ejecutada como aceptacion | `src/Modules/Proyectos/ProjectRepository.php` |
| T041 | Pendiente; no ejecutada como aceptacion | `src/Modules/Proyectos/ProjectService.php` |
| T042 | Pendiente; no ejecutada como aceptacion | `src/Modules/Proyectos/ExpedienteRepository.php`, `src/Modules/Proyectos/ExpedienteService.php` |
| T043 | Pendiente; no ejecutada como aceptacion | `src/Modules/Proyectos/CentroidService.php` |
| T044 | Pendiente; no ejecutada como aceptacion | `src/Modules/Empresas/CompanyController.php`, `src/Http/routes/companies.php` |
| T045 | Pendiente; no ejecutada como aceptacion | `src/Modules/Proyectos/ProjectController.php`, `src/Modules/Proyectos/ExpedienteController.php`, `src/Http/routes/projects.php` |
| T046 | Pendiente; no ejecutada como aceptacion | `views/empresas/index.php`, `views/empresas/form.php`, `public/assets/js/modules/companies.js` |
| T047 | Pendiente; no ejecutada como aceptacion | `views/proyectos/index.php`, `views/proyectos/form.php`, `views/proyectos/expedientes.php`, `public/assets/js/modules/projects.js` |
| T048 | Pendiente; no ejecutada como aceptacion | `tests/integration/ProjectConcurrencyTest.php`, `ops/evidence/us2.md` |
| T049 | Pendiente; no ejecutada como aceptacion | `tests/contract/CadastreContractTest.php` |
| T050 | Pendiente; no ejecutada como aceptacion | `src/Modules/Catastro/CadastreSchema.php`, `bin/check-cadastre.php` |
| T051 | Pendiente; no ejecutada como aceptacion | `src/Modules/Catastro/CadastreRepository.php` |
| T052 | Pendiente; no ejecutada como aceptacion | `src/Modules/Catastro/CadastreController.php`, `src/Http/routes/cadastre.php` |
| T053 | Pendiente; no ejecutada como aceptacion | `views/catastro/index.php`, `views/catastro/detail.php`, `public/assets/js/modules/cadastre.js` |
| T054 | Pendiente; no ejecutada como aceptacion | `tests/integration/CadastreReadOnlyTest.php` |
| T055 | Pendiente; no ejecutada como aceptacion | `tests/contract/CadastreSurfaceTest.php`, `ops/evidence/us3.md` |
| T056 | Pendiente; no ejecutada como aceptacion | `tests/contract/AuditContractTest.php` |
| T057 | Pendiente; no ejecutada como aceptacion | `src/Modules/Auditoria/AuditRepository.php` |
| T058 | Pendiente; no ejecutada como aceptacion | `src/Modules/Auditoria/AuditController.php`, `src/Http/routes/audit.php` |
| T059 | Pendiente; no ejecutada como aceptacion | `src/Modules/Auditoria/AuditAlerts.php`, `src/Jobs/AuditAlertProducer.php` |
| T060 | Pendiente; no ejecutada como aceptacion | `views/auditoria/index.php`, `views/auditoria/detail.php`, `public/assets/js/modules/audit.js` |
| T061 | Pendiente; no ejecutada como aceptacion | `tests/integration/AuditSnapshotsTest.php` |
| T062 | Pendiente; no ejecutada como aceptacion | `tests/integration/AuditRollbackTest.php` |
| T063 | Pendiente; no ejecutada como aceptacion | `ops/evidence/us7.md` |
| T064 | Pendiente; no ejecutada como aceptacion | `tests/contract/NeighborsContractTest.php` |
| T065 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/NeighborRepository.php` |
| T066 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/ProjectNeighborRepository.php` |
| T067 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/NeighborStylePolicy.php` |
| T068 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/ProjectNeighborService.php` |
| T069 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/NeighborService.php` |
| T070 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/ProjectNeighborController.php`, `src/Http/routes/project-neighbors.php` |
| T071 | Pendiente; no ejecutada como aceptacion | `src/Modules/Vecinas/NeighborController.php`, `src/Http/routes/neighbors.php` |
| T072 | Pendiente; no ejecutada como aceptacion | `views/vecinas/project.php`, `public/assets/js/modules/project-neighbors.js` |
| T073 | Pendiente; no ejecutada como aceptacion | `views/vecinas/catalog.php`, `public/assets/js/modules/neighbors.js` |
| T074 | Pendiente; no ejecutada como aceptacion | `tests/integration/NeighborConcurrencyTest.php` |
| T075 | Pendiente; no ejecutada como aceptacion | `ops/evidence/us4.md` |
| T076 | Pendiente; no ejecutada como aceptacion | `tests/contract/MapContractTest.php` |
| T077 | Pendiente; no ejecutada como aceptacion | `ops/map-providers.md` |
| T078 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/MapRepository.php` |
| T079 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/IdentifyService.php`, `src/Modules/Visor/AreaService.php` |
| T080 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/ImportService.php`, `src/Infrastructure/TemporaryFiles.php` |
| T081 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/GeoJsonReader.php`, `src/Modules/Visor/KmlReader.php` |
| T082 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/ShapefileReader.php` |
| T083 | Pendiente; no ejecutada como aceptacion | `src/Modules/Visor/MapController.php`, `src/Modules/Visor/ImportController.php`, `src/Http/routes/map.php` |
| T084 | Pendiente; no ejecutada como aceptacion | `public/assets/js/modules/map.js`, `public/assets/js/modules/map-baselayers.js` |
| T085 | Pendiente; no ejecutada como aceptacion | `public/assets/js/modules/map-styles.js` |
| T086 | Pendiente; no ejecutada como aceptacion | `public/assets/js/modules/map-identify.js`, `views/visor/identify.php` |
| T087 | Pendiente; no ejecutada como aceptacion | `public/assets/js/modules/map-tools.js` |
| T088 | Pendiente; no ejecutada como aceptacion | `public/assets/js/modules/map-imports.js` |
| T089 | Pendiente; no ejecutada como aceptacion | `views/visor/map.php`, `public/assets/css/map.css` |
| T090 | Pendiente; no ejecutada como aceptacion | `tests/browser/map-scenarios.md`, `ops/evidence/us5-browser.md` |
| T091 | Pendiente; no ejecutada como aceptacion | `tests/integration/ImportSecurityTest.php`, `tests/fixtures/imports/manifest.json` |
| T092 | Pendiente; no ejecutada como aceptacion | `ops/evidence/us5.md` |
| T093 | Pendiente; no ejecutada como aceptacion | `tests/contract/ExportsContractTest.php` |
| T094 | Pendiente; no ejecutada como aceptacion | `migrations/007_exports_schedules.sql` |
| T095 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ExportRepository.php` |
| T096 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/CsvExporter.php`, `src/Modules/Reportes/XlsxExporter.php` |
| T097 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/PdfExporter.php` |
| T098 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/GeoJsonExporter.php`, `src/Modules/Reportes/KmlExporter.php` |
| T099 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ShapefileExporter.php` |
| T100 | Pendiente; no ejecutada como aceptacion | `src/Jobs/ExportWorker.php` |
| T101 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ScheduleService.php`, `src/Jobs/ReportScheduler.php`, `bin/scheduler.php` |
| T102 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ExportController.php`, `src/Http/routes/exports.php` |
| T103 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ScheduleController.php`, `src/Http/routes/schedules.php`, `views/reportes/schedules.php` |
| T104 | Pendiente; no ejecutada como aceptacion | `views/reportes/index.php`, `public/assets/js/modules/reports.js` |
| T105 | Pendiente; no ejecutada como aceptacion | `src/Jobs/MaintenanceWorker.php`, `ops/cron/sgpm` |
| T106 | Pendiente; no ejecutada como aceptacion | `src/Modules/Reportes/ReportNotifications.php` |
| T107 | Pendiente; no ejecutada como aceptacion | `tests/integration/ExportFormatsTest.php`, `tests/fixtures/exports/manifest.json` |
| T108 | Pendiente; no ejecutada como aceptacion | `tests/integration/ExportAuthorizationTest.php`, `tests/integration/ScheduleDeduplicationTest.php` |
| T109 | Pendiente; no ejecutada como aceptacion | `ops/evidence/us6.md` |
| T110 | Pendiente; no ejecutada como aceptacion | `ops/evidence/compatibility.md` |
| T111 | Pendiente; no ejecutada como aceptacion | `tests/contract/RouteAuthorizationMatrixTest.php`, `ops/evidence/authorization.md` |
| T112 | Pendiente; no ejecutada como aceptacion | `tests/contract/WebSecurityTest.php`, `ops/evidence/security.md` |
| T113 | Pendiente; no ejecutada como aceptacion | `tests/browser/responsive-scenarios.md`, `ops/evidence/responsive.md` |
| T114 | Pendiente; no ejecutada como aceptacion | `tests/fixtures/performance/manifest.json` |
| T115 | Pendiente; no ejecutada como aceptacion | `tests/performance/run.php`, `tests/browser/performance-scenarios.md` |
| T116 | Pendiente; no ejecutada como aceptacion | `ops/evidence/performance.md` |
| T117 | Pendiente; no ejecutada como aceptacion | `ops/evidence/dependencies.md` |
| T118 | Pendiente; no ejecutada como aceptacion | `ops/evidence/email-conformance.md` |
| T119 | Pendiente; no ejecutada como aceptacion | `ops/evidence/acceptance.md` |
| T120 | Pendiente; no ejecutada como aceptacion | `ops/release-checklist.md`, `ops/deployment.md` |

## Finalizacion

Verificado: ningun contenedor `sgpm.acceptance=true` restante; no quedan
config/config.php ni storage/acceptance/local.json. El entorno temporal fue
retirado automaticamente. `git diff --check` sin errores de espacios.
Las eliminaciones solicitadas del ambiente persistente se conservaron.
