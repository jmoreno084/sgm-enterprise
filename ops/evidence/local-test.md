# Ambiente persistente local — 2026-10-13

Aplicacion disponible en https://localhost:18443/login, contenedores exclusivos
`sgpm-enterprise-test-1d3f5f93-db` y `sgpm-enterprise-test-1d3f5f93-web`.
Ambos quedan encendidos. Base `sgpm_local_test`, puertos solo loopback.

Imagenes reutilizadas por ID, sin descargar ni alterar contenedores originales:

- PHP: `sha256:b52e92f9b4dfc01d94d7edd5e81fb5740048423cf6c1b222d69b03d2600b6006`.
- PostgreSQL: `sha256:cb985a9bacd8daa29c77afd7a8d582bc56b443b308668e2ce4b7c70dcf5377cc`.

Versiones observadas: PHP 7.0.33-0ubuntu0.16.04.16, Apache 2.4.18,
PostgreSQL 9.5.25, PostGIS 2.5.5. Seis migraciones 001–006 aplicadas y registradas
con checksum. Fixtures A/B y administrador/gerente/geologo/inversionista cargados.

## Verificaciones ejecutadas

- HTTPS con CA explicita, peer y hostname verificados: login 200.
- Smoke `ops/docker/check-local-test.php`: **26 PASS, 0 FAIL**.
  Login valido/invalido, CSRF403, acceso administrador, UTF-8, duplicados409,
  edicion optimista409, membresia GEOLOGO/duplicado/revocacion, aislamiento A/B,
  RBAC403, recuperacion202 uniforme, cambio de clave/token unico400 y logout401.
  Usuarios nuevos del smoke desactivados al terminar; se conserva historial.
- Roles separados: runtime no tiene SELECT en `schema_migrations`; se consultaron
  las seis migraciones con postgres, sin ampliar privilegios runtime.
- `bin/check-compatibility.php`: exit 0, 75 archivos PHP revisados con 7.0.33.
- `git check-ignore` confirma exclusion de metadatos, credenciales, runtime.env
  y clave privada TLS en storage/local-test.
- Ciclo `stop` / `start` completado: antes y despues, **6 usuarios, 2 empresas,
  36 eventos de auditoria y 6 migraciones**. Fixtures no recargadas, sin nuevos
  eventos ni cambios de clave por el arranque. Certificado SHA256 conservado:
  `05936FD577BDFEBCD10804C7E3A7EC4E8FAB5F9A3336649A1F2F323E1A338BBC`.
- `status` al finalizar el ciclo: ambos contenedores running; HTTPS vuelve a 200.

Se corrigieron incompatibilidades del script con arrays/quoting de Windows
PowerShell y el montaje de configuracion sobre un repositorio de solo lectura.
El primer smoke intento consultar migraciones con runtime y detecto la denegacion
correcta; se ajusto la comprobacion al rol adecuado. La ejecucion final aprobo las
26 comprobaciones sin cambiar permisos ni codigo funcional de la aplicacion.

## Acceso y limites

Credenciales: `storage/local-test/credentials.json`; no se publican en evidencia.
Certificado: `storage/local-test/server.crt`, persistente y nuevo. El usuario debe
resolver su confianza en el navegador; no se importo ni se omitieron advertencias.
Esta puesta en marcha se verifico por HTTPS/HTML/API con CA explicita. La revision
visual Playwright previa de US1 sigue documentada en `ops/evidence/us1.md`; no se
declara repetida sobre este nuevo certificado.

Correo `double`, sin credenciales reales, sin worker programado ni envios reales.
No se borraron datos existentes ni se modificaron contenedores originales,
produccion o enterprise. Solo se crearon recursos propios del repositorio.
Guia y comandos: `ops/docker/local-test.md`.

Comprobacion final mediante el comando entregable `local-test.ps1 check`: 26 PASS,
0 FAIL, salida guardada en `ops/evidence/local-test-check.txt`. Tras esta nueva
comprobacion hay 7 usuarios (4 fixtures y 3 usuarios smoke inactivos), 2 empresas,
50 eventos de auditoria y 6 migraciones. HTTPS externo: 200 y ssl_verify_result=0;
HTTP: 301 hacia HTTPS. Solo 18080/18443 publicados en 127.0.0.1, sin puerto DB.
Los tres contenedores originales siguen running con sus fechas de arranque previas.
