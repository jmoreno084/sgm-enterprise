# Dependencias externas pendientes

El usuario confirmó que no dispone de los recursos siguientes y solicitó conservar
estas tareas pendientes. No sustituyen las pruebas locales con fixtures y doble.

* **T092 — Google Maps real:** falta una clave de navegador autorizada y restringida
  al origen local. Las cuatro bases Google no tienen aceptación real demostrada.
  Deben conservar su estado no disponible cuando no hay clave.
* **T118 — conformidad del correo corporativo:** faltan documentación y muestras
  autorizadas del proveedor. El modo permanece `double`; no se inventa un perfil
  positivo ni se infiere entrega a partir de HTTP 200. No se han autorizado envíos
  reales ni un destinatario para realizarlos.

Las tareas permanecen sin marcar en `specs/001-sgpm-integral/tasks.md`. Su ausencia
no impide implementar ni comprobar las funciones independientes del proveedor.
