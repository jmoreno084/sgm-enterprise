# Lista de revisión de release

## Código y dependencias

- [ ] Revisar cambios, tareas SpecKit y archivos eliminados deliberadamente; no incluir configuración, credenciales ni datos privados.
- [ ] Fijar versiones, hashes, commits, licencias y parches de vendor; ejecutar instaladores desde archivos verificados.
- [ ] Ejecutar compatibilidad PHP 7.0.33/strict_types, parser ES2015 y suite PHP completa en Docker desechable.
- [ ] Ejecutar Playwright responsive y recorridos de historias en HTTPS con certificado confiable instalado por el usuario.

## Base de datos y seguridad

- [ ] Revisar migraciones y grants con DBA; confirmar PostgreSQL/PostGIS requeridos, backup/restauración y separación de roles.
- [ ] Confirmar que el catastro permanece de solo lectura, que la autorización se revalida en tareas diferidas y que archivos privados no son públicos.
- [ ] Confirmar TLS, redirect, CSP/HSTS, sesiones Secure/HttpOnly/SameSite, límites de carga y ausencia de secretos en logs.
- [ ] Revisar puertas de Google Q5/Q6 y correo T118 con credenciales/documentación autorizadas; ningún envío real sin autorización de destinatario.

## Carga y operación

- [ ] Cumplir Q11 en el perfil declarado con 50 sesiones, tres rondas frías y tres calientes, 500/5000 polígonos íntegros y límites máximos estrictos.
- [ ] Revisar evidencia y fallos conservados en `ops/evidence/`; no ocultar ejecuciones interrumpidas.
- [ ] Revisar cron bajo usuario de servicio, supervisión de workers, caducidad/limpieza, backups y plan de reversión.

## Estado actual

No aprobado para producción: Q11 sigue abierto; Google y proveedor de correo no tienen validación externa; falta una suite final integral tras los cambios de mapa. Ver `ops/evidence/acceptance.md`. Esta lista no ejecuta despliegue ni migración.
