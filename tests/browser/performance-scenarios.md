# Q11 — instrumentación de navegador

Preparar100000 fuentes con `bin/seed-fixtures.php --external --profile=sgpm-performance`
como propietario fixture y después el mismo perfil interno como runtime. Ambas fases
rechazan bases ajenas a test/acceptance y datos ya presentes; no reemplazan ni borran.
Preparar50 sesiones nativas fuera del intervalo mediante
`tests/fixtures/performance/sessions.php` en el entorno desechable. Los tokens quedan
en storage/acceptance ignorado porGit y nunca deben copiarse a evidencia pública.

Cliente separado del servidor: `php tests/performance/run.php --users=50
--warmup=120 --duration=600 --round=cold-1 --network-profile=PERFIL_VERIFICADO`.
Usar `--resolve=IP` solo para resolver el hostname del certificado al servidor objetivo;
CA explícita con `--ca=...`, sin omitir verificación de peer/host.
Ejecutar tres frías/controladas y tres calientes, con pausas1–3s y mezcla60/20/10/5/5.
Registrar qué cachés se vacían/controlan; no vaciar cachés del host ni afectar servicios
ajenos. El argumento network-profile documenta el perfil, no lo configura ni acredita.

En Chromium, cargar cada proyecto500/5000 en contextos nuevos, con perfil de red
20Mbps/RTT50ms mediante CDP; medir desde navegación/clic hasta
`window.sgpmMapMetrics.complete`, rendered==source_total==500/5000 y recuento real de
vértices. Registrar cada muestra de load_ms y tooltip_ms, además de máximos/p95.
Cache fría/controlada: contexto nuevo y Network.setCacheDisabled(true); caliente:
repetir página y mantener caché. Documentar separadamente el estado de caché PostgreSQL.

Umbrales estrictos: cada consulta/Identify<3s, mapa500<5s,5000<8s, tooltip<200ms,
50 sesiones sin fugas/errores. La aceptación202 de exportación no mide generación;
generar y cronometrar cada formato aparte. Medir RTT PHP–DB separadamente.
Conservar fallos y máximos: p95 no elimina incumplimientos.

Intervalos cortos solo con `--diagnostic`: sirven para depurar el runner y nunca
completan T116. Si falta alguna condición o ejecución, registrarla pendiente.
