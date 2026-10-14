# US6 — reportes y programación local

La ejecución aislada aprobó **38 suites**, incluyendo ExportsContractTest,
ExportsHttpTest, ExportFormatsTest, ExportAuthorizationTest y
ScheduleDeduplicationTest. Se revisaron213 archivos con PHP7.0.33. La ejecución
posterior de Playwright aprobó83 comprobaciones consolidadas.

* Seis formatos generados y abiertos/verificados: PDF real con fuente UTF-8
  embebida, XLSX OOXML con texto explícito, CSV neutralizado, GeoJSON original,
  KML2.2/clampToGround y Shapefile UTF-8 legible con mapeo DBF y sidecar íntegro.
* Fuentes ausentes permanecen en tabular y se enumeran como omisiones espaciales;
  no se inventan polígonos. XLSX divide texto que supera la capacidad de una celda
  en TEXTOS_LARGOS con referencias explícitas, sin truncamiento silencioso.
* Jobs202, estado, publicación atómica, descarga privada MIME/attachment,
  solicitante o administrador, Geólogo403, ajeno404, no listo409, caducado410.
* La revocación antes de generar y al descargar impide obtener el archivo.
  La cuenta de servicio del worker y Apache debe ser la misma para archivos0600.
* Programación inicialINACTIVO/09:00America/Bogota, destinatarios autorizados,
  versiones, eliminaciónRESTRICT y deduplicación fecha-programa-usuario.
* Outbox con enlace autenticado y doble local, payload eliminado tras procesar,
  mantenimiento de archivos24h y metadatos30d. Sin adjuntos ni envíos reales.
* Playwright comprobó solicitud y descarga CSV, programación desde la UI y
  navegación/controles por rol a360/768/1440.

Evidencia de formatos: `tests/fixtures/exports/manifest.json`; capturas/resultados
en `integral-browser/`. El ejemplo de cron no fue instalado. T118 permanece abierta:
el doble no acredita conformidad ni entrega del proveedor corporativo.
