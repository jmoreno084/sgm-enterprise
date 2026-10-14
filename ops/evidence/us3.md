# Evidencia US3 — 2026-10-13

T049–T055 implementadas. CadastreSchema inspecciona30 columnas/tipos/NULL,
clave unica y GiST; nombres quoted se adaptan a aliases canonicos.
CadastreRepository usa PDO preparado, mapa cerrado de fuentes, UNION ALL
con discriminador, cinco filtros y paginacion25/max100. FichaSSR y API con
30 atributos, GeoJSON4326, DATE y NULL explicitos. Consulta accesible desde Inicio.

`ops/docker/test-foundation.ps1`: **21 PASS, 0 FAIL**, 113 PHP compatibles
con7.0.33. Nuevas suites CadastreContractTest, CadastreReadOnlyTest y
CadastreSurfaceTest, junto con18 regresiones US1/US2.

Pruebas: cada filtro; mismo codigo en ambas fuentes no se colapsa; acentos yDATE;
parametros SQLi no ejecutados; sourceausente404; pagina>100400; cuatro
operaciones de escritura/lock por cada fuente denegadas al runtime42501.
Propietario externo retira una fila creada exclusivamente en la base de prueba:
el vinculo queda con fuente_disponible=false y otra asociacion400.
Identificador quoted de modalidad verificado y restaurado por propietariofixture.
HTTPsesion401, lista/ficha200, fichaSSR30 atributos/NULL; POST/PUT/DELETE
sobre fuentes y rutas validar/reparar404. No se escribieron fuentes reales.

Backend/HTML probados por HTTPS con CA explicita. Revision de JavaScript/
responsive del buscador se incluira enT113; no se afirma nueva evidencia
Playwright de US3 en esta ejecucion. Base temporal retirada automaticamente.
