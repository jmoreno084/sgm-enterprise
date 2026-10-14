# Evidencia de seguridad web

`WebSecurityTest.php` pasó dirigida: CSRF inválido, escape de salida, SQL parametrizado, rutas privadas, errores sin traza/secretos y encabezados CSP, HSTS, nosniff y frame-deny. HTTPS local se verifica con CA de fixture y validación explícita; redirect HTTP y cookie Secure forman parte de la suite. No se desactivó validación TLS.

Playwright usa la confianza local aprobada por el usuario. Certificados temporales no se importan/eliminan automáticamente. Repetir suite completa antes del cierre.
