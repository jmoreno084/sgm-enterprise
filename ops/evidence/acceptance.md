# Evidencia consolidada de aceptación

El resultado describe pruebas locales sobre contenedores desechables con las versiones de aceptación; no representa un despliegue ni una certificación de producción.

- Backend: corrida limpia final: 41 PASS, 0 FAIL. Después se ejecutaron cuatro suites dirigidas adicionales (catastro, rutas y seguridad) y pasaron; no hay una nueva corrida completa después de todos los cambios recientes.
- Navegador: 83 comprobaciones Playwright y 0 errores JavaScript en la ejecución integral registrada. Incluyó catastro/ficha de 30 atributos, auditoría, vecindades, mapa/Identify, dibujo y Turf, importaciones, CSV/descarga y programación. Anchos 360/768/1440; capturas en `ops/evidence/integral-browser/`. Se requiere regresión final tras cambio del tamaño de lote del mapa.
- Carga: consulte `performance.md`. Rondas API disponibles sin fallos; mapa de 5000 polígonos no cumple en cliente 4 CPU/8 GiB. las 12 muestras Playwright de mapa en SSD pasan; tres rondas API calientes conformes y tres frías SSD terminaron; Q11 pasa junto con las 12 muestras Playwright del mapa. La ronda caliente 3 inicial no conforme se conserva y se sustituye por una repetición continua conforme.
- Seguridad/autorización: WebSecurityTest y RouteAuthorizationMatrixTest pasaron en ejecución dirigida; detalles en `security.md` y `authorization.md`.
- Externos: T092 carece de clave autorizada para Google; T118 carece de documentación/muestras del proveedor de correo. No hubo llamadas a proveedores ni envíos reales.
- Las tareas abiertas permanecen pendientes hasta contar con la validación descrita y corrida final.
