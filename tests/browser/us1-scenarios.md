# Revisión visual de US1

## Preparación

Ejecutar `./ops/docker/test-foundation.ps1 -KeepAcceptance` en Windows.
Abrir `https://localhost:18443/login`. La contraseña de fixture del administrador
está en `storage/acceptance/local.json`, excluido de Git. Correo: admin@example.invalid.
El certificado es temporal y exclusivamente de pruebas. Su aceptación en un navegador
corresponde al usuario; el agente no debe superar advertencias de seguridad del navegador.
El entorno original y enterprise no se utilizan para estas pruebas.

## Recorrido

1. Abrir login; comprobar etiquetas, orden de foco por Tab, foco visible y acceso
   al enlace Recuperar acceso. Repetir en anchos 360, 768 y 1440.
2. Enviar clave incorrecta; comprobar mensaje amigable, foco en el mensaje y campo
   de contraseña vacío. No deben aparecer errores PHP, SQL o trazas.
3. Ingresar como administrador; comprobar lista de usuarios, carga, paginación y
   formulario de membresías. Los campos deben conservar nombres/documentos con acentos.
4. Crear un usuario de prueba. Repetir su correo con espacios/mayúsculas: comprobar
   mensaje de conflicto 409 sin duplicar el registro.
5. Abrir el mismo usuario en dos pestañas. Guardar una edición y luego la otra:
   comprobar conflicto de versión y que no se sobrescriba la primera edición.
6. Asignar membresía GEOLOGO en Empresa A, editarla y revocarla. Comprobar etiquetas,
   selección de empresa y errores de membresía duplicada. La revocación afecta la
   siguiente petición del usuario, sin conceder acceso a Empresa B.
7. Ingresar como geólogo: usuarios/membresías administrativas deben devolver 403.
   Cerrar sesión y comprobar que `/usuarios` requiere autenticación de nuevo.
8. Solicitar recuperación con correo conocido y desconocido: comprobar el mismo
   mensaje, sin contraseñas o tokens renderizados. Revisar límites de recuperación.
9. Usar un enlace de fixture: el fragmento del token debe desaparecer de la barra
   de dirección; las claves se limpian tras error/envío y el enlace no se reutiliza.
10. Recorrer los controles con teclado; comprobar ausencia de desbordamiento
    horizontal a 360 px, mensajes legibles y estado vacío después de filtrar.

## Estado de ejecución

Pendiente de revisión visual. En esta sesión `cua.getState()` devolvió navegadores
vacíos; crear pestañas en `iab` y `chrome` devolvió Browser is not available.
No se registran capturas, comportamiento JavaScript o teclado como verificados.
Las pruebas cURL/DOM de AuthHttpsTest verifican transporte/HTML, no este recorrido.

## Finalización

Ejecutar `./ops/docker/stop-acceptance.ps1` para retirar solo los contenedores
temporales etiquetados y sus credenciales. La configuración se conserva si fue
modificada después de iniciar la aceptación.
