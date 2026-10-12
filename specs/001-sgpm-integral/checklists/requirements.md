# Specification Quality Checklist: Gestión integral SGPM

**Purpose**: Validar completitud y calidad para planificación y generación de tareas.
**Created**: 2026-10-11
**Feature**: [spec.md](../spec.md)
**Revisión vigente**: Planificación 2026-10-11; constitución 4.2.0-SGPM.

## Content Quality

- [ ] Sin detalles de implementación: excepción explícita por contrato técnico suministrado.
- [x] Centrada en valor de usuario y necesidades de negocio.
- [x] Recorridos comprensibles para interesados no técnicos; contrato técnico separado.
- [x] Todas las secciones obligatorias completas.

## Requirement Completeness

- [x] Sin marcadores NEEDS CLARIFICATION: AC-01, AC-02 y AC-03 resueltas.
- [x] Requisitos verificables y sin ambigüedades bloqueantes; dependencias de conformidad externa documentadas.
- [x] Criterios de éxito con umbrales medibles.
- [x] Criterios de éxito expresados sin tecnologías de implementación.
- [x] Escenarios de aceptación definidos para cada capacidad funcional agrupada.
- [x] Casos límite identificados.
- [x] Alcance acotado a los ocho módulos; sincronización externa fuera de alcance.
- [x] Dependencias y supuestos identificados.

## Feature Readiness

- [x] Todos los requisitos tienen criterios de aceptación, incluido el contrato espacial ratificado.
- [x] Escenarios cubren los flujos principales.
- [x] Protocolo medible definido: conjunto estándar/masivo, equipo, red, complejidad y50 usuarios.
- [ ] Sin detalles de implementación: contrato de datos y restricciones técnicas conservados por petición expresa.

## Notes

- Historial: AC-01 identidad vecina compartida y vínculo con color por proyecto; AC-02
  Administrador global y matriz empresarial aprobada; AC-03 opción B ratificada en
  constitución4.2.0-SGPM para permitir Point4326 con GiST. No quedan conflictos pendientes.
- Se corrigieron dos textos obsoletos del spec que todavía describían AC-03/fechas como
  pendientes; el contrato catastral aclarado fija cinco fechas DATE y the_geom NOT NULL.
- Resultado12/16→14/16. Los dos ítems no marcados son excepciones deliberadas de formato:
  el usuario suministró un contrato técnico obligatorio; no se elimina para cumplir una
  regla genérica de ausencia de detalles técnicos. No son incumplimientos constitucionales.
- RNF-06/SC-003: referencia5000 polígonos/500000 vértices y protocolo en quickstart.md,
  con50 sesiones, tamaños de datos y entorno definidos. No se afirman mediciones ejecutadas.
- Rangos RF recibidos sin desglose individual permanecen agrupados y trazados a capacidades,
  escenarios y artefactos; no se inventa numeración oficial de subrequisitos.
- Conformidad de correo real requiere perfil verificado de respuesta; compatibilidad de
  vendor requiere lint y smoke sobre PHP7.0.33. Son verificaciones de implementación y
  aceptación, explicitadas en research.md y contratos, no supuestos ya satisfechos.
- Listo para speckit-tasks. No se ha implementado aplicación ni desplegado durante plan.
