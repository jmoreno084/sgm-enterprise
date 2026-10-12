# Contrato de integración corporativa de correo

**Fecha:** 2026-10-11. Interfaz obligatoria, no servicio invocado durante plan.

## Petición

POST https://sigmin.automatizzar.com/admin_emails/sendEmail.php mediante
LibCurlModel::curl_download(string $url, array $params=[]): string en PHP7.0.33.
Content-Type application/x-www-form-urlencoded, generado con http_build_query.

| Parámetro | Origen | Regla |
| --- | --- | --- |
| USERKEY | Entorno y constante segura de config/config.php | Obligatorio; no respuesta, snapshots ni logs. |
| APIKEY | Entorno y constante segura de config/config.php | Obligatorio; no respuesta, snapshots ni logs. |
| email | Correo actual de usuario destinatario autorizado | Obligatorio; no usar dirección arbitraria recibida del navegador. |
| asunto | Plantilla por tipo de notificación | Obligatorio; texto sin CR/LF. |
| mensaje | Plantilla con valores escapados | Obligatorio; sin password ni claves corporativas; recuperación incluye enlace de un solo uso. |

URL fija de configuración; no URL elegida por usuario, no SMTP ni redirección a otro host.
CURLOPT_POST y RETURNTRANSFER true; SSL_VERIFYPEER true y SSL_VERIFYHOST2.
CONNECTTIMEOUT10, TIMEOUT30 y USERAGENT SGPM-System-Ubuntu16/1.0. Verificar CA instalada.
Mantener firma y retorno string de la referencia del usuario; registrar HTTP status con
curl_getinfo antes de cerrar handle para que el adaptador pueda clasificar el resultado.

## Respuesta y estados locales

| Observación | Clasificación | Conducta |
| --- | --- | --- |
| 2xx y cuerpo positivo según perfil verificado | ACEPTADO | Proveedor aceptó petición; no afirmar entrega al buzón. |
| 2xx con cuerpo desconocido, vacío o sin perfil | INDETERMINADO | No mostrar éxito de despacho; revisión de integración. |
| Rechazo explícito 4xx/5xx o cuerpo negativo verificado | FALLIDO | Registrar código saneado; no imprimir cuerpo crudo. |
| Timeout/error con posible transmisión o worker interrumpido | INDETERMINADO | No reenvío automático sin idempotencia documentada. |
| Fallo demostrablemente anterior al despacho | FALLIDO | Registrar incidente local y permitir nueva solicitud explícita. |

**Dependencia externa:** El formato real del cuerpo, indicador positivo y semántica de
idempotencia no fueron aportados. No se inventan propiedades JSON. El perfil de respuesta
se incorpora desde documentación o muestras anonimizadas verificadas antes de aceptación
productiva. Hasta entonces el modo real queda deshabilitado por defecto y las pruebas
usan un doble. Este requisito es de conformidad del proveedor, no una duda de diseño.

## Outbox y disparadores

Alta de usuario, solicitud de recuperación, reporte programado y alerta de auditoría
insertan outbox en la transacción local; ejecución de cURL ocurre después del commit.
Recuperación responde202 uniforme y token expira30min. Bienvenida no incluye clave.
Reportes envían enlace privado con reautenticación, no adjunto no contemplado por el endpoint.
Alertas al Administrador global: cambios de roles, desactivaciones y borrados maestros,
agrupadas por minuto, con resumen saneado. El propio despacho no genera una nueva alerta.

Payload cifrado temporal con clave fuera de la base; borrado al completar/expirar.
request_id y outbox_id permiten seguimiento sin registrar secretos. Sesión del usuario
no permanece bloqueada durante cURL. El proceso worker vive en el mismo servidor por cron.

## Pruebas contractuales

Doble HTTP verificable sin USERKEY/APIKEY reales: comprobar método, codificación y cinco
parámetros; cuerpo positivo de fixture, negativo, desconocido, HTTP500, timeout y cierre
inesperado. El doble positivo prueba el perfil de fixture, nunca confirma contrato real.
Probar ausencia de reenvío en resultado ambiguo y clasificación sin fugas de trazas.
Prueba real posterior controlada con documentación del proveedor, destinatario autorizado
y verificación de aceptación; entrega al buzón se verifica aparte y no se infiere de2xx.
