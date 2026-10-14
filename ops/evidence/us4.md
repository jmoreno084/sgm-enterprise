# Evidencia US4 — 2026-10-13

T064–T075 implementadas. Identidadcompartida separada del vinculo; NIT conocido
unico/desconocidoNULL; resolverexacto devuelve soloid/nombre/nit. Geologo mantiene
notas y asociaciones propias, identidadglobal soloAdministrador.

`ops/docker/test-foundation.ps1`: **29 PASS, 0 FAIL**, 140 PHPcompatibles7.0.33.
Nuevas NeighborsContractTest, NeighborsHttpTest, NeighborConcurrencyTest.
17 vecinas:coloresunicos, ocho tonosliterales, patrones1 desde9 y2 desde17.
Color/ordinal/patron asignados porservidor, proyectoFORUPDATE, historial decolores
retirados consultado para no reciclarlos.

Dos procesos/conexiones simultaneas creanordinal/color distintos. Baja yalta
posterior conserva secuencia. Agotamiento deespaciodecolor409 revierte identidad
y vinculo, sin huerfanos. Versionobsoleta409.

Reutilizacion A/B conserva identidad y notasindependientes; Geologo A no editaB404
ni identidadglobal403; Gerente no escribe403; duplicadoNIT/vinculo409; selecciones
ambiguas400. ExpedienteVECINO201, repeticionscomoPROPIO409; baja conexpediente409.
EdicionglobalA se refleja ennombre sin alterar estilo porproyecto.
PantallasSSR proyecto/catalogo disponibles viaHTTPS. Render de patrones cartograficos
yrevisionvisual se verificaran enUS5/T113; no se afirman porlas pruebas backend.
No correo real ni cambiosenterprise; entorno temporal retirado automaticamente.
