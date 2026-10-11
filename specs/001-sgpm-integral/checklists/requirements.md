# Specification Quality Checklist: Gestión integral SGPM

**Purpose**: Validar completitud y calidad antes de planificación.
**Created**: 2026-10-11
**Feature**: [spec.md](../spec.md)

## Content Quality

- [ ] Sin detalles de implementación: excepción explícita por contrato técnico suministrado.
- [x] Centrada en valor de usuario y necesidades de negocio.
- [x] Recorridos comprensibles para interesados no técnicos; contrato técnico separado.
- [x] Todas las secciones obligatorias completas.

## Requirement Completeness

- [x] Sin marcadores NEEDS CLARIFICATION: queda AC-03.
- [ ] Requisitos completamente verificables y sin ambigüedad: centroide pendiente; RBAC resuelto.
- [x] Criterios de éxito con umbrales medibles.
- [x] Criterios de éxito expresados sin tecnologías de implementación.
- [x] Escenarios de aceptación definidos para cada capacidad funcional agrupada.
- [x] Casos límite identificados.
- [x] Alcance acotado a los ocho módulos; sincronización externa fuera de alcance.
- [x] Dependencias y supuestos identificados.

## Feature Readiness

- [x] Todos los requisitos tienen aceptación definitiva: falta resolver el contrato espacial.
- [x] Escenarios cubren los flujos principales.
- [ ] Resultados medibles completamente operacionalizados: falta conjunto masivo de referencia.
- [ ] Sin detalles de implementación: contrato de datos y restricciones técnicas conservados por petición expresa.

## Notes

- Revisión 1: detectados conflictos de reutilización RN-04, tipos geométricos y matriz RBAC ausente.
- Revisión 2: AC-01 resuelta con respuesta del usuario: identidad compartida y vínculo N:M
  con color por proyecto; actualizado el contrato de entidades y exclusividad del vínculo.
- AC-02 resuelta por el usuario: Administrador global; Gerente/Inversionista consultan, exportan y consultan auditoría de su empresa; Geólogo consulta y edita proyectos/vecinas de su empresa. Matriz y escenarios incorporados.
- AC-03: «¿Se usa GEOMETRY(Geometry, 4326) con valor restringido a punto [...] o se solicita
  una enmienda que permita GEOMETRY(Point, 4326)?» No se modifica la constitución aquí.
- RNF-06/SC-003: «conjunto masivo de referencia a fijar en planificación»; se conserva
  el umbral de ocho segundos sin declarar validado un volumen no especificado.
- Los rangos RF-02 a RF-08 recibidos no desglosan cada subcódigo; se mantienen agrupados
  y se relacionan con escenarios, sin inventar numeración oficial.
- «Los detalles tecnológicos [...] se conservan porque son requisitos explícitos del
  usuario y de la constitución»: excepción deliberada a los dos ítems genéricos de
  ausencia de implementación. No se elimina información aprobada para marcar la lista.
- No se declara lista para planificación definitiva hasta resolver AC-03.
  Siguiente paso recomendado: speckit-clarify; posteriormente speckit-plan.

- Revisión 3: matriz RBAC aprobada incorporada; permanece una aclaración espacial.


