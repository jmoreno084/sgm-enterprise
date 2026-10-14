# SGPM
Aplicación mononodo conforme a la constitución 5.0.0-SGPM.

## Ejecución
El único DocumentRoot permitido es `public/`. El entorno de aceptación es Ubuntu
16.04, Apache 2.4.18, PHP 7.0.33, PostgreSQL 9.5.25 y PostGIS 2.5.5.
Copiar `config/config.example.php` a `config/config.php` y configurar las variables
indicadas; no guardar secretos en Git. Apache y CLI deben recibir el mismo entorno.

`php bin/check-compatibility.php` comprueba el runtime exacto y lint.
`php bin/check-compatibility.php --source-only` es diagnóstico estático, no certificación.
`php tests/run.php` ejecuta pruebas unitarias; `--integration` requiere la base aislada.
Las migraciones se ejecutan exclusivamente por CLI con una cuenta migradora separada.
Ver [entorno](ops/acceptance-environment.md) y [tareas](specs/001-sgpm-integral/tasks.md).
No se ha desplegado ni configurado un servidor productivo.

## Validación Docker aislada

Desde PowerShell, con `mi-app-php` y `mi-postgis` disponibles:

```powershell
.\ops\docker\test-foundation.ps1
```

El runner usa las imágenes locales de esos contenedores y monta este repositorio en
modo lectura. Crea PostgreSQL temporal en memoria, sin publicar puertos ni utilizar
el volumen de `enterprise`. PHP comparte su espacio de red y usa `127.0.0.1:5432`;
no se admite `host=db`. Genera credenciales efímeras y roles separados, aplica
migraciones, carga fixtures, ejecuta `tests/run.php --integration --contract` y
verifica compatibilidad PHP. Elimina su contenedor y configuración al terminar.
Requiere que `config/config.php` no exista; no sustituye configuraciones existentes.

Estado y alcance: [fundamentos](ops/evidence/foundation.md) y
[acceso US1](ops/evidence/us1.md). La cobertura por historia y los resultados recientes están en `ops/evidence/acceptance.md`. El mapa a 5000 polígonos pasó 12 muestras bajo el perfil SSD de 4 CPU/8 GiB. Q11 pasó con tres rondas frías y tres calientes SSD, y 12 muestras Playwright de mapa. Las pruebas locales no certifican producción ni el contrato real de correo.

Para revisar las pantallas en HTTPS local, ejecutar:

```powershell
.\ops\docker\test-foundation.ps1 -KeepAcceptance
```

Después de pasar las pruebas, queda disponible `https://localhost:18443/login`,
publicado solo en loopback. Se usa un certificado temporal de pruebas. Para que el navegador Playwright confíe, el usuario lo instaló en el almacén de confianza de su perfil Windows; el runner CLI valida con CA explícita. Las credenciales de fixture y nombres de contenedores quedan en
`storage/acceptance/local.json`, ignorado por Git. Seguir el
[recorrido visual US1](tests/browser/us1-scenarios.md). Para terminar:

```powershell
.\ops\docker\stop-acceptance.ps1
```

Las pruebas automáticas HTTPS validan el certificado con una CA de fixture explícita,
301, HSTS y cookies Secure. El doble de correo usa TLS/HTTP local, nunca el proveedor
real. La revisión visual y el comportamiento JavaScript requieren un navegador.


## Estado, operación y evidencia

- [Evidencia de aceptación](ops/evidence/acceptance.md) y [resultados de carga](ops/evidence/performance.md).
- [Guía de despliegue](ops/deployment.md) y [lista de revisión](ops/release-checklist.md). Son guías; no se ha desplegado ni migrado producción.
- T092 (Google) y T118 (correo corporativo) requieren contrato/credenciales autorizadas. El correo permanece en modo doble y sin envíos reales.
