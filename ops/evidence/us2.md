# Evidencia US2 — 2026-10-13

T037–T048 implementadas: CRUD empresarial y proyectos, logos privados PNG/JPEG
hasta 5 MiB, asociaciones por fuente/tipo y centroide Point4326 calculado en3116.
Solo PDO, fuentes SELECT, version optimista y autores de servidor. UI SSR/ES2015
con permisos por rol, filtros, paginacion y mensajes enfocados.

## Verificacion

`ops/docker/test-foundation.ps1`: 18 suites PASS, 0 FAIL; PHP7.0.33,
PostgreSQL9.5.25/PostGIS2.5.5; compatibilidad exit0, 103 archivos PHP.
Nuevas: ProjectsContractTest, ProjectsHttpTest, ProjectFilesTest,
ProjectConcurrencyTest. Se preservan las 14 suites de regresion US1.

- NIT duplicado409, campos canonicos/UTF-8, versiones obsoletas409 y borradoRESTRICT.
- Geologo edita solo A, gerente no escribe, B404, CSRF403, Location201.
- Logo PNG real por multipart y descarga autorizada; contenido falso415, >5MiB413,
  version obsoleta409, empresa ajena404; archivo privado aleatorio sin path recibido.
- Proyectos no aceptan empresa/centroide/autores enviados; fechas invalidas400.
- Fuente inexistente/tipo incorrecto400; mismo codigo en otro proyecto permitido.
- Dos procesos/conexiones simultaneas PROPIO/VECINO:201/409 y una fila.
- Trigger de auditoria fallido en DB de prueba revierte asociacion/centroide/version;
  error interno propagado para500, sin confundirlo con validacion del usuario.
- Centroide PostGIS Point4326 y NULL al retirar todos los expedientes propios.

## Navegador

`python tests/browser/us2-playwright.py`: **30 PASS, 0 FAIL**, 14 capturas en
[us2-browser](us2-browser/results.json). Chromium con validacionTLS predeterminada
tras aceptacion del certificado temporal por el usuario. Empresas/proyectos/
expedientes a360/768/1440, foco visible, etiquetas, mensajes y scroll de tabla
interno sin overflow del documento. Creacion, logo, duplicado, fechas, asociacion,
fuente inexistente, desvinculacion y vacio; controles gerente/geologo y B404;
ninguna excepcion JS no capturada. Capturas revisadas visualmente.

El primer recorrido detecto que reset no limpiaba inputs hidden id/version;
corregido en resource-ui.js y recorrido repetido completo con exito.
No se usa el ambiente persistente eliminado ni se toca enterprise.
Certificado de navegador no instalado automaticamente. El entorno temporal
fue retirado con stop-acceptance; la regresion final retiro su base automaticamente.
Funciones posteriores aUS2 no se declaran completadas por esta evidencia.
