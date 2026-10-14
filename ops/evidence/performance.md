# Evidencia de rendimiento

La suite API utiliza 50 sesiones autenticadas, calentamiento de 120 s, medición de 600 s, pausas de usuario de 1–3 s, mezcla objetivo 60/20/10/5/5 y red emulada a 20 Mbps/RTT 50 ms. El mapa se mide en Playwright con todos los polígonos y vértices; tooltip se mide con un hover real. Resultados guardados sin credenciales ni cookies.

## Resultados

- Perfil principal: tres rondas frías de 50 sesiones, cada una continua durante ~721 s, 0 fallos. Búsqueda máxima 2.743/2.810/2.821 s; Identify 1.582/1.518/1.636 s. Archivos `performance-cold-controlled-{1,2,3}.json`.
- Perfil principal caliente: `performance-warm-1.json` continua (~721.8 s), 0 fallos; búsqueda máxima 3.723 s, Identify 1.874 s. Las rondas calientes 2 y 3 no tienen evidencia guardada.
- Perfil con PostgreSQL sobre el volumen Docker respaldado por SSD: una ronda fría continua (~721.1 s), 0 fallos; búsqueda máxima 2.117 s, Identify 2.026 s. Archivo `performance-ssd-cold-controlled-1.json`. El perfil PostgreSQL incluye índices GIN `pg_trgm` exclusivos de la copia de aceptación.
- Playwright perfil principal, 12 muestras (3 frías y 3 calientes por tamaño): 500 polígonos 2.063–2.127 ms en frío y 930–963 ms caliente; 5.000 polígonos 2.909–3.506 ms en frío y 2.578–3.210 ms caliente. Se conservaron 50.000/500.000 vértices y todos los resultados pasaron sus umbrales; tooltip máximo observado <2 ms.
- Tras optimizar el mapa, Playwright con cliente Windows limitado a 4 CPU/8 GiB y servidor SSD pasó las 12 muestras: 500 polígonos máximo 3.377 ms; 5.000 máximo 7.205 ms, con 500.000 vértices completos y tooltip máximo 4.2 ms. Red 20 Mbps/RTT 50 ms verificada antes/después. Evidencia `performance-ssd-noarea/results.json`.

## Estado

Q11 **en verificación final**. Las tres rondas API frías SSD fueron continuas y pasaron (0 fallos): búsqueda máxima 2.117, 2.912, 2.650 s; Identify 2.026, 2.817, 1.715 s; mapa 2.519, 2.673, 2.798 s. La ronda caliente final 1 también pasó, continua por 721.0 s y 0 fallos: búsqueda máxima 2.736 s, Identify 1.937 s, edición 1.986 s, exportación 1.587 s, mapa 2.846 s. La ronda caliente final 2 pasó continua (21.023 muestras, 0 fallos; búsqueda máxima 2.967 s). La ronda 3 también fue continua con 0 fallos HTTP, pero 31 muestras se ralentizaron conjuntamente por el entorno y superaron 3 s (máximo 6.170 s); permanece no conforme en `performance-ssd-final-warm-3.json`. Solo se repite esa ronda con nuevo archivo `performance-ssd-recheck-warm-3.json`. El mapa SSD de 4 CPU/8 GiB pasa las 12 muestras.

Los resultados interrumpidos y diagnósticos fallidos se conservan con sus nombres en `ops/evidence/`; no se contabilizan como rondas válidas. El fixture y sus límites están en `tests/fixtures/performance/manifest.json`; procedimiento en `tests/browser/performance-scenarios.md`. Ninguna medición autoriza extrapolar a producción.
