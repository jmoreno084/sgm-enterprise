# Revisión visual consolidada

Usar únicamente el entorno Docker desechable y confianza de certificado aceptada
por el usuario. Ejecutar `python tests/browser/integral-playwright.py` y el guion
US2 cuando cambien sus pantallas. No omitir la validación TLS.

Para catastro/ficha, auditoría/detalle, vecinas, mapa, reportes y programación:
comprobar360/768/1440, ausencia de desbordamiento del documento, scroll contenido
en tablas, navegación por teclado/foco visible, etiquetas y mensajes en español,
UTF-8, errores y listas vacías. Revisar las capturas, además de las aserciones.

Prueba de envío inmediato de programación: guardar antes de completar la primera
consulta de sesión debe esperar el tokenCSRF, no devolver403 por inicialización.
Errores de archivos no deben retirar capas ya cargadas. Revisar el rol Geólogo
sin controles de auditoría/exportación; las rutas también deben rechazarse.

Evidencia ejecutada: `ops/evidence/us5-browser.md`, `us6.md`,
`integral-browser/results.json` y capturas. La revisión no certifica por sí sola
proveedores externos ni rendimiento con500/5000 polígonos.
