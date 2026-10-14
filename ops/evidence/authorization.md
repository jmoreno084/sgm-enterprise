# Evidencia de autorización

`tests/contract/RouteAuthorizationMatrixTest.php` recorre el censo de rutas y contrasta Administrador, Geólogo, Gerente e Inversionista, objetos propios/ajenos, entradas inválidas y revocación en listados, mapa/Identify, jobs/descargas y auditoría. Cubre GET y mutaciones con CSRF/versionado; rutas nuevas sin caso explícito hacen fallar el censo. La ejecución dirigida pasó.

`WebSecurityTest.php` también pasó. La matriz cubre plantillas y límites de permisos; la suite completa final queda pendiente.
