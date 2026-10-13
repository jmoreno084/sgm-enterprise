# Evidencia local US1 — 2026-10-13

## Entorno y reproducción

Constitución 5.0.0-SGPM. PostgreSQL 9.5.25, PostGIS 2.5.5 y PHP
7.0.33-0ubuntu0.16.04.16. Las verificaciones PHP comparan componentes 7/0/33,
admitiendo el sufijo de empaquetado sin permitir otra versión del lenguaje.

Desde PowerShell: `./ops/docker/test-foundation.ps1`.
El runner reutiliza las imágenes locales de mi-app-php y mi-postgis, con base
sgpm_acceptance independiente en tmpfs, sin puertos publicados ni volumen enterprise.
PHP monta este repositorio en modo lectura y comparte el espacio de red del PostgreSQL
temporal: DSN `127.0.0.1:5432`, conforme a la configuración local vigente.
Los propietarios, migrador, fixtures y runtime son roles separados con claves efímeras.
El runner restaura el entorno del proceso y elimina su configuración y contenedor.

## Resultados observados

- Seis migraciones aplicadas desde cero en la base temporal.
- `tests/run.php --integration --contract`: 12 suites PASS, 0 FAIL.
- `bin/check-compatibility.php`: exit 0, 69 archivos PHP revisados con PHP 7.0.33,
  lint, strict_types y checksums de activos. zip está disponible en la imagen actual.
- `git diff --check`: sin errores de espacios antes de registrar esta evidencia.
- Base y configuración temporales eliminadas; solo quedan los dos contenedores originales.

Suites: AuthorizationTest, ConfigurationTest, HttpSecurityTest, SqlScriptTest,
AuthRbacTest, FoundationIntegrityTest, NotificationWorkerTest, RecoveryTest,
UserConcurrencyTest, AuthContractTest, AuthHttpTest y EmailContractTest.

## Cobertura funcional comprobada

- Auditoría transaccional y rollback real; runtime sin mutación de bitácora ni catastro.
- Login válido e inválido, LOGIN/LOGOUT auditados, DTO sin credenciales, bcrypt costo 10.
- CSRF anónimo, rechazo sin token, rotación de identificador/CSRF y logout 204 por HTTP.
- Cookies Secure, HttpOnly y SameSite=Lax en encabezados; permisos 401/403/404/409/429.
- Membresía revocada y cuenta desactivada afectan la siguiente comprobación de acceso.
- Dos administradores se degradan concurrentemente desde procesos independientes:
  una operación 200 y otra 409; permanece exactamente un administrador activo.
- Recuperación uniforme por HTTP; token de 30 minutos, caducado/reutilizado rechazado,
  cambio de bcrypt y auth_version; límites independientes y snapshots sin secretos.
- AES-256-CBC con HMAC-SHA256: payload alterado rechazado antes de descifrar.
- Worker confirma PROCESANDO antes de transporte, descarta destino inactivo,
  borra payload y no reenvía resultados ambiguos ni trabajo perdido.
- Clasificación de respuesta positiva de fixture, negativa, desconocida, 500 y timeout.
  Transporte sustituido por doble en memoria para verificar URL y cinco parámetros;
  no se invoca el proveedor real. Perfil de fixture no certifica formato corporativo.

## Estado y límites — registro anterior a la validación HTTPS

T020–T033 cerradas. T034/T035 tienen vistas y módulos implementados, pendientes de
revisión visual/teclado en navegador. T036 sigue pendiente: Q1/Q2/Q8 requieren
aceptación HTTPS y doble HTTP verificable del proveedor, además de pruebas locales.
El contrato HTTP actual usa un servidor PHP de pruebas con conexión local HTTP y
cookie explícita; no valida confianza del certificado, HTTPS, redirección 301 o HSTS.

mi-app-php publica solo puerto 80 mediante localhost:8080 y monta otra carpeta.
No se desplegó este repositorio en ese contenedor ni se alteró enterprise.
Falta definir/preparar el entorno HTTPS de aceptación para completar US1.
Se conserva la dependencia de entrega de US1 antes de T037 y siguientes.
No hay extensions.yml ni hooks de implementación registrados.

Las validaciones de vendor PHP, mapas, exportaciones, rendimiento y correo real
pertenecen a tareas posteriores y no se declaran aprobadas por estos resultados.

## Validación HTTPS y doble HTTP — 2026-10-13

Se preparó Apache 2.4.18 temporal con certificado de fixture para localhost.
La CA se entrega explícitamente a cURL; no se deshabilitan VERIFYHOST/VERIFYPEER
ni se instala confianza global. El transporte público de correo conserva la URL fija;
una subclase de pruebas reutiliza la transferencia real contra el doble local.

Resultados de la nueva ejecución:

- 14 suites PASS, 0 FAIL; se añaden AuthHttpsTest y EmailHttpContractTest.
- Compatibilidad PHP: exit 0, 74 archivos con PHP 7.0.33, lint y strict_types.
- AuthHttpsTest: certificado verificado, 301 HTTP→HTTPS, HSTS/nosniff,
  formularios/activos disponibles, rutas privadas inaccesibles, cookie Secure
  usada por el motor de cookies de cURL, login/logout y autorización efectivos.
- EmailHttpContractTest: POST real form-urlencoded, cinco parámetros, acentos y
  caracteres especiales; positivo, negativo, desconocido, vacío, HTTP 500,
  timeout real y respuesta interrumpida. Nombre de host incorrecto rechazado por TLS.
- Se corrigió la simulación de respuesta parcial para forzar el envío antes del
  cierre: sin flush Apache completaba una respuesta válida, sin error de transporte.
- AuthHttpTest restablece la clave de fixture para no alterar pruebas posteriores.
- Acceso desde Windows: `curl.exe --cacert storage/acceptance/server.crt ...`
  devuelve HTTPS 200 y ssl_verify_result=0, sin omitir verificación del certificado.

El modo `-KeepAcceptance` deja una base independiente y un servidor HTTPS de pruebas
en localhost:18080/18443. `storage/acceptance/local.json`, el certificado copiado y
config/config.php están ignorados por Git (comprobado con git check-ignore).
No se modificaron los contenedores preexistentes ni enterprise.
`ops/docker/stop-acceptance.ps1` valida nombres/etiquetas antes de retirar el entorno.

**Bloqueo restante:** no hay navegador controlable en la sesión. El inventario CUA
devuelve navegadores vacíos y la creación en iab/chrome devuelve Browser is not available.
T034/T035 y el cierre integral T036 siguen pendientes de revisión visual y JavaScript;
guion preparado en tests/browser/us1-scenarios.md. No se afirma aceptación visual,
teclado, responsive o conformidad corporativa real. Las tareas siguientes conservan
su dependencia de entrega US1. No hay extensions.yml ni hooks registrados.
