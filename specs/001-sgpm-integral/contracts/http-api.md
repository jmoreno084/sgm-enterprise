# Contrato HTTP privado — SGPM

**Versión:** diseño1, 2026-10-11. Base `/api/v1`, same-origin HTTPS.
Normativa: [spec](../spec.md), [modelo](../data-model.md), [plan](../plan.md).
No se exponen servicios de edición/validación topológica de expedientes oficiales.

## Convenciones

Sesión cookie SGPMSESSID Secure/HttpOnly/SameSite=Lax, renovada al login; timeout inactivo
30min y absoluto8h. PHP7.0 emite SameSite sin depender de opciones de PHP7.3.
Liberar lock de sesión antes de consultas y archivos; cada petición verifica estado actual,
auth_version y ámbito. No confiar en empresa_id, rol, autor ni color enviados por cliente.

JSON UTF-8, Content-Type application/json. Cuerpo máximo1MiB salvo endpoints multipart.
Solicitud mutante exige X-CSRF-Token; login/recuperación obtienen token anónimo desde
GET /sesion. CSRF se comprueba también en POST de trabajos, importación y logout.
No mutaciones por GET. Same-origin; no habilitar CORS global.

Éxito: {"data":...,"meta":{"request_id":"..."}}. Listas añaden page, page_size, total;
page_size default25 máximo100. Paginación estable por id; orden y filtros con whitelist.
Error: {"error":{"code":"EXPEDIENTE_DUPLICADO","message":"El expediente ya está asociado a este proyecto."},"meta":{"request_id":"..."}}.
Nunca incluir SQL, credenciales, paths internos, stack trace ni cuerpo crudo del proveedor.

| HTTP | Semántica |
| --- | --- |
| 200 / 201 | Consulta/actualización exitosa o creación; Location en201. |
| 202 | Trabajo o recuperación aceptados localmente, no entrega de correo confirmada. |
| 204 | Eliminación/logout sin cuerpo. |
| 400 | Formato, campos, fechas, tipo o fuente inexistente para asociación. |
| 401 | Sesión inexistente, expirada o usuario inactivo. |
| 403 | Sin permiso para acción dentro de ámbito conocido, o CSRF inválido. |
| 404 | Objeto inexistente o fuera de empresa autorizada; respuesta indistinguible. |
| 409 | Código/NIT/correo duplicado, versión obsoleta, dependencia de borrado o última cuenta administradora. |
| 413 / 415 | Archivo demasiado grande o formato no admitido. |
| 429 | Límite temporal de intentos/recuperaciones. |
| 500 / 503 | Error interno amigable / dependencia temporalmente no disponible. |

## Autorización

A=Administrador global. E=Administrador o Geólogo del proyecto/empresa. C=cualquier
rol activo vinculado a la empresa o Administrador. X=Administrador, Gerente o Inversionista
de la empresa. La matriz de spec es autoridad; el rol global no permite modificar auditoría
ni escribir catastro. Ámbito del objeto se resuelve en DB, no desde la URL únicamente.

## Sesión, usuarios y empresas

| Método/ruta relativa | Acceso | Entrada y resultado |
| --- | --- | --- |
| GET /sesion | Anónimo o autenticado | CSRF y estado; autenticado incluye usuario mínimo y empresas autorizadas, nunca hash. |
| POST /sesion | Anónimo+CSRF | correo, password;200 sesión renovada;401 inválida/inactiva;429 límite. |
| DELETE /sesion | Autenticado+CSRF |204 e invalidación; audita LOGOUT. |
| POST /recuperaciones | Anónimo+CSRF | correo;202 uniforme exista o no; límite3/15min. |
| POST /recuperaciones/confirmar | Anónimo+CSRF | token, password, confirmacion;200 cambio o400 token inválido/expirado/consumido. |
| GET /usuarios | A | Filtros correo,nombre,estado y paginación. |
| POST /usuarios | A | correo, password, numero_documento, nombre, estado; es_administrador explícito opcional;201 DTO saneado. |
| GET /usuarios/{id} | A | DTO sin hash ni tokens. |
| PUT /usuarios/{id} | A | Campos editables+version; clave solo si se solicita explícitamente;409 última cuenta activa. |
| DELETE /usuarios/{id} | A | version;204 solo sin referencias;409 con historial, ofreciendo desactivación. |
| GET /empresas | Autenticado | A ve todas; otros solo membresías activas; paginado. |
| POST /empresas | A | Campos canónicos obligatorios y opcionales;201. |
| GET /empresas/{id} | C | DTO autorizado. |
| PUT /empresas/{id} | A | Campos canónicos+version; no id recibido ni autor arbitrario. |
| DELETE /empresas/{id} | A | version;204 sin dependencias;409 si hay proyectos/membresías. |
| POST /empresas/{id}/logo | A | multipart archivo PNG/JPEG<=5MiB y CSRF;200 ruta de acceso controlada, no path físico. |
| GET /empresas/{id}/logo | C | Imagen verificada, X-Content-Type-Options:nosniff. |
| GET /empresas/{id}/usuarios | A | Miembros y roles de esa empresa. |
| POST /empresas/{id}/usuarios | A | usuario_id, rol(GERENTE/GEOLOGO/INVERSIONISTA), estado;201 o409 duplicado. |
| PUT /empresas/{id}/usuarios/{vinculo} | A | rol,estado,version;200; invalida permisos en la siguiente solicitud. |
| DELETE /empresas/{id}/usuarios/{vinculo} | A | version;204, audita ámbito antes de borrar. |

Contraseña mínima12 caracteres; bcrypt costo10, rechazo de clave UTF-8 mayor de72bytes
para evitar truncamiento silencioso. Sin reglas artificiales de composición; nunca enviar
contraseñas en correo de bienvenida. No devolver password_hash, token_hash o payloads secretos.

## Proyectos y asociaciones

| Método/ruta | Acceso | Entrada y resultado |
| --- | --- | --- |
| GET /empresas/{empresa}/proyectos | C | nombre,estado,mineral_principal; lista paginada. |
| POST /empresas/{empresa}/proyectos | E | nombre,estado,mineral_principal y opcionales canónicos;201. |
| GET /proyectos/{id} | C | Campos canónicos, version y resumen autorizado de asociaciones. |
| PUT /proyectos/{id} | E | Campos editables+version; no empresa_id/centroide/autor;200 o409. |
| DELETE /proyectos/{id} | E | version;204 sin dependencias,409 con enlaces. |
| GET /proyectos/{id}/expedientes | C | tipo,filiacion,vecina opcionales; paginado. |
| POST /proyectos/{id}/expedientes | E | codigo_expediente,tipo_expediente,filiacion,empresa_vecina_id si VECINO;201. |
| PUT /proyectos/{id}/expedientes/{vinculo} | E | filiacion,empresa_vecina_id,version; mismo código/tipo;200. |
| DELETE /proyectos/{id}/expedientes/{vinculo} | E | version;204 sin alterar fuentes. |

POST de asociación usa fuente cerrada por tipo. Ejemplo:

```json
{"codigo_expediente":"ABC-123","tipo_expediente":"TITULO","filiacion":"VECINO","empresa_vecina_id":9}
```

Éxito incluye fecha_asociacion, version y datos de atribución del vínculo. RN-12 devuelve
409 EXPEDIENTE_DUPLICADO tanto si la petición cambia de filiación como si cambia de tipo.
El cliente no asigna fecha, color ni patrón. Cambiar código requiere desvincular y asociar;
no existe actualización de geometría legal ni de atributos de la fuente.

## Vecinas compartidas y vínculos

| Método/ruta | Acceso | Entrada y resultado |
| --- | --- | --- |
| GET /proyectos/{id}/vecinas | C | Vínculos de ese proyecto con identidad mínima,color,patrón,ordinal,notas,version. |
| POST /proyectos/{id}/vecinas/resolver | E | nit exacto;200 identidad mínima si existe,404 si no; sin vínculos ajenos. |
| POST /proyectos/{id}/vecinas | E | empresa_vecina_id existente o nueva_identidad{nombre,nit?,observaciones?}; notas particulares opcionales;201 vínculo con color asignado. |
| PUT /proyectos/{id}/vecinas/{vecina} | E | observaciones del vínculo,version; no color/ordinal ni identidad global. |
| DELETE /proyectos/{id}/vecinas/{vecina} | E | version;204 o409 si hay expedientes, sin tocar otros proyectos. |
| GET /vecinas | A | Catálogo de identidades globales, filtros y paginación. |
| GET /vecinas/{id} | A | Identidad global y version. |
| PUT /vecinas/{id} | A | nombre,nit,observaciones,version; audita cambio global. |
| DELETE /vecinas/{id} | A | version;204 solo sin vínculos,409 en otro caso. |

No aceptar más de uno de empresa_vecina_id/nueva_identidad. NIT duplicado retorna409 con
opción de reutilización sin listar las asociaciones privadas. La transacción de creación
nueva+vínculo completa ambas operaciones o ninguna.

## Catastro, mapa e importación

| Método/ruta | Acceso | Entrada y resultado |
| --- | --- | --- |
| GET /catastro | Autenticado activo | tipo,codigo,titular,municipio,departamento,mineral; lista oficial paginada con fuente, sin filiaciones privadas. |
| GET /catastro/{tipo}/{codigo} | Autenticado activo |30 atributos oficiales; geometría en GeoJSON, fechas ISO y valores ausentes null. |
| GET /proyectos/{id}/mapa | C | bbox opcional, tipo/filiacion; GeoJSON autorizado; contrato mapa separado. |
| GET /proyectos/{id}/identify/{vinculo} | C |30 atributos más filiación y vecina/nombre proyecto; fuente ausente se informa sin datos inventados. |
| POST /proyectos/{id}/capas/importar | C+CSRF | multipart archivo;200 FeatureCollection temporal con avisos/CRS y límites documentados. |

La importación es parsing y visualización de una capa de trabajo, no endpoint de validación
topológica. No persistir capa o modificar catastro. Rechazar límites de ZIP antes de expandir;
prohibir path traversal, symlinks, archivos ejecutables y XML con DTD/entidades externas.
Los atributos externos se escapan al mostrarse, incluso en popups y nombres de archivos.

## Exportación, programación y auditoría

| Método/ruta | Acceso | Entrada y resultado |
| --- | --- | --- |
| POST /empresas/{id}/exportaciones | X | formato,proyecto_id?,filtros;202 job_id y URL de estado. |
| GET /exportaciones/{id} | Solicitante X o A |200 estado y caducidad;404 ajeno/ámbito oculto. |
| GET /exportaciones/{id}/archivo | Solicitante X o A |200 descarga solo LISTO y acceso vigente;409 aún no listo;410 expirado. |
| GET /empresas/{id}/programaciones | A | Programaciones de esa empresa. |
| POST /empresas/{id}/programaciones | A | formato,filtros,hora_local,destinatarios(usuario_id[]),estado;201. |
| PUT /programaciones/{id} | A | Mismos campos+version;200. |
| DELETE /programaciones/{id} | A | version;204 sin jobs dependientes, o409 y opción de desactivar. |
| GET /empresas/{id}/auditoria | X | fecha_desde/hasta,accion,entidad,usuario_id; paginación estable por fecha,id. |
| GET /auditoria | A | Consulta global, incluidos eventos sin ámbito empresarial. |

No rutas PUT/DELETE/POST públicas para auditoría ni URL pública de cron. Worker CLI
revalida permisos antes de obtener datos, generar archivo, enviar enlace y descargarlo.
La programación es diaria por empresa y la configura Administrador; destinatarios deben
ser usuarios activos con permiso X en esa empresa. En el cuerpo mensaje se envía un enlace
autenticado a la descarga, no un enlace público ni credenciales. No se inventa soporte
para adjuntos en un contrato corporativo de solo cinco parámetros.

## UI y accesibilidad funcional

Páginas /login, /recuperar, /usuarios, /empresas, /empresas/{id}/proyectos,
/proyectos/{id}, /proyectos/{id}/mapa y /empresas/{id}/auditoria. Router servidor;
no URL privada accesible por ocultación de botones únicamente. Estados explícitos de
cargando, vacío, sin permiso y error. Navegación por teclado, etiquetas de formularios y
foco visible; mensajes asociados a campos y estado de petición. Bootstrap responsive en
360/768/1440px. Geólogo no ve controles de exportación/auditoría; el servidor también los niega.
