# Feature Specification: Gestión integral de proyectos mineros SGPM

**Feature Branch**: Sin rama creada; no hay hook de creación configurado.

**Created**: 2026-10-11

**Status**: APROBADO; aclaraciones AC-01, AC-02 y AC-03 resueltas y documentadas. Listo para planificación con las dependencias operativas registradas.

**Versión de requisitos**: 4.1.0-SGPM (Actualizada).

**Input**: Especificaciones formales del usuario para los ocho módulos del SGPM,
con esquema canónico, fuentes catastrales externas de 30 atributos y RNF-01 a RNF-20.
Metodología Spec-Driven Development (SDD). Rige la
[constitución 5.0.0-SGPM](../../.specify/memory/constitution.md).

## Clarifications

### Session 2026-10-11

- Q: ¿Cómo se despachan las notificaciones del SGPM? → A: Exclusivamente por POST HTTPS al servicio corporativo sendEmail.php, mediante LibCurlModel/cURL, sin SMTP local; incluye recuperación, bienvenida, reportes programados y alertas de auditoría.
- Q: ¿Debe el SGPM exponer validación topológica de expedientes oficiales? → A: No; la sincronización externa garantiza la calidad. No hay endpoints de validación, reparación ni edición de límites oficiales. Se mantienen RN-06, RN-12 y RN-13.
- Q: ¿Cuál es el contrato definitivo de las fuentes catastrales? → A: Misma base, 30 campos idénticos, fechas DATE, the_geom MultiPolygon 4326 NOT NULL con GiST y sgpm_user con SELECT exclusivamente sobre ambas tablas.
- Q: ¿Cuál es la infraestructura de ejecución? → A: Un host Ubuntu 16.04 LTS de 64 bits, Apache/2.4.18 con HTTPS en 443, PHP 7.0.33 y PostgreSQL 9.5.25 con PostGIS 2.5.5, por TCP 127.0.0.1:5432.
- Q: ¿Cómo se garantiza RN-12 incluso con concurrencia? → A: Restricción uq_proyecto_expediente UNIQUE (proyecto_id, codigo_expediente), con rechazo de duplicados independientemente del tipo o filiación.

- Q: ¿Qué tipo debe usar el centroide del proyecto para resolver el conflicto con la constitución? → A: B; enmendar la constitución para permitir GEOMETRY(Point, 4326), ratificado en 4.2.0-SGPM con índice GiST.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Acceso y administración de usuarios (Priority: P1)

Como usuario autorizado, necesito acceder con mi correo y contraseña, recuperar el acceso
y operar solo en las empresas y funciones permitidas por mi rol.

**Why this priority**: Protege la información empresarial y atribuye las operaciones.

**Independent Test**: Con usuarios y empresas de prueba, verificar acceso, recuperación,
registro de último acceso y rechazo de operaciones no autorizadas.

**Acceptance Scenarios**:

1. **Given** un usuario activo con credenciales válidas, **When** inicia sesión,
   **Then** accede a sus empresas autorizadas, se actualiza `ultimo_acceso` y se registra LOGIN.
2. **Given** credenciales inválidas o usuario inactivo, **When** intenta acceder,
   **Then** se rechaza el acceso sin mostrar trazas ni conceder permisos.
3. **Given** un usuario con permiso de administración, **When** crea o modifica una cuenta,
   **Then** se validan correo único, documento, nombre y estado y se registra la operación.
4. **Given** una cuenta existente, **When** se solicita recuperar la clave,
   **Then** se envía una instrucción de recuperación por el servicio corporativo;
   una prueba de recuperación válida permite reemplazar la clave y una inválida se rechaza.
5. **Given** un rol sin permiso para una operación o empresa, **When** intenta ejecutarla
   directamente o desde la interfaz, **Then** se rechaza sin cambiar datos.
6. **Given** una sesión abierta, **When** el usuario cierra sesión,
   **Then** deja de autorizar solicitudes y se registra LOGOUT.

### User Story 2 - Empresas y proyectos propios (Priority: P1)

Como gestor autorizado, necesito mantener empresas y sus proyectos y asociar expedientes
oficiales a cada proyecto sin duplicarlos.

**Why this priority**: Constituye la unidad administrativa y territorial del sistema.

**Independent Test**: Crear una empresa y proyecto con catastro de prueba; asociar un
expediente existente y probar una asociación inexistente y otra duplicada.

**Acceptance Scenarios**:

1. **Given** un gestor autorizado, **When** registra una empresa con sus campos obligatorios,
   **Then** queda disponible para proyectos; un NIT repetido produce un conflicto amigable.
2. **Given** una empresa existente, **When** registra un proyecto,
   **Then** este pertenece a una sola empresa y conserva sus datos y autoría.
3. **Given** un código existente en la fuente del tipo indicado, **When** lo asocia como propio,
   **Then** se registra una única vinculación al proyecto con fecha y filiación PROPIO.
4. **Given** un código inexistente en la fuente indicada, **When** intenta asociarlo,
   **Then** la asociación se rechaza sin modificar datos.
5. **Given** un expediente ya vinculado, **When** se intenta repetirlo como propio o vecino,
   incluso mediante solicitudes simultáneas, **Then** solo existe una asociación en ese proyecto.
6. **Given** registros existentes y permiso suficiente, **When** consulta, edita o elimina
   empresas o proyectos, **Then** se refleja la operación autorizada, se preserva la
   integridad de relaciones y se registra el cambio; una eliminación que dejaría
   referencias inválidas se rechaza con un mensaje comprensible.

### User Story 3 - Consulta del catastro externo (Priority: P1)

Como analista autorizado, necesito localizar títulos y solicitudes oficiales y consultar
su ficha técnica sin alterar los datos catastrales.

**Why this priority**: La existencia catastral es condición para cualquier asociación.

**Independent Test**: Consultar fuentes de prueba conocidas por cada filtro y comparar
las fichas con sus 30 atributos originales.

**Acceptance Scenarios**:

1. **Given** un catastro sincronizado, **When** busca por código, titular, municipio,
   departamento o mineral, **Then** recibe los registros correspondientes o un estado vacío.
2. **Given** un registro encontrado, **When** abre su ficha, **Then** se presentan sus
   30 atributos, incluidos valores ausentes identificados sin inventar información.
3. **Given** cualquier rol de aplicación, **When** intenta modificar las tablas externas,
   **Then** la operación no está permitida.

### User Story 4 - Competencia y empresas vecinas (Priority: P2)

Como gestor autorizado, necesito registrar competencia, reutilizar empresas vecinas y
asignarles expedientes oficiales para comparar su presencia alrededor del proyecto.

**Why this priority**: Permite interpretar competencia manteniendo pertenencia inequívoca.

**Independent Test**: Preparar proyectos y expedientes; registrar nueve vecinas, asociar
varios expedientes por vecina y reutilizar una vecina en un segundo proyecto.

**Acceptance Scenarios**:

1. **Given** un proyecto, **When** registra una vecina, **Then** recibe automáticamente
   un tono violeta secuencial según el orden de registro en ese proyecto.
2. **Given** varios expedientes de una vecina, **When** se visualizan,
   **Then** todos heredan su color; otras vecinas se distinguen visualmente.
3. **Given** ocho vecinas, **When** agrega la novena y posteriores,
   **Then** se aplican rayados diagonales que preservan la distinción visual.
4. **Given** una vecina existente, **When** se vincula a otro proyecto incluso de otra
   matriz, **Then** se conserva su identidad y la simbología se determina por proyecto (RN-04).
5. **Given** una vinculación VECINO, **When** se guarda, **Then** exige una vecina
   vinculada al mismo proyecto; una vinculación PROPIO no atribuye una vecina.
6. **Given** una vecina registrada, **When** un gestor autorizado consulta, modifica o
   elimina su vínculo, **Then** el cambio no altera vínculos de otros proyectos,
   conserva la integridad y deja auditoría.

### User Story 5 - Exploración cartográfica (Priority: P2)

Como geólogo o consultor autorizado, necesito explorar expedientes propios y vecinos,
identificarlos, medir y superponer archivos geográficos.

**Why this priority**: Convierte los expedientes en información territorial consultable.

**Independent Test**: Usar un proyecto con polígonos y archivos válidos de cada formato;
comparar etiquetas, ficha, mediciones y controles de visualización.

**Acceptance Scenarios**:

1. **Given** un proyecto con geometrías, **When** abre el mapa,
   **Then** puede seleccionar OSM, Google Calles/Satélite/Híbrido/Terreno y
   ESRI Satelital/Topográfica y distinguir simbología azul, verde y violeta.
2. **Given** un polígono propio, **When** pasa el cursor,
   **Then** aparece `Proyecto: {nombre} ({código expediente})` en menos de 200 ms;
   para uno vecino aparece el nombre de su empresa competencia.
3. **Given** un expediente, **When** hace clic, **Then** Identify muestra los atributos
   completos de la fuente y la filiación, incluidos los datos exigidos por RN-11.
4. **Given** herramientas de medición, **When** traza distancias y superficies o coloca
   marcadores, **Then** obtiene mediciones métricas y puede localizar sus marcadores.
5. **Given** archivos Shapefile .zip, GeoJSON y KML válidos, **When** los importa,
   **Then** puede visualizar cada capa y ajustar color y transparencia sin escribir en el catastro.
6. **Given** un expediente con área oficial, **When** consulta su superficie,
   **Then** se usa AREA_HA o una validación geodésica sobre la geometría; no se sustituye
   el área oficial con una medición visual aproximada.

### User Story 6 - Reportes y exportaciones (Priority: P3)

Como usuario autorizado, necesito compartir información tabular y geográfica de mi
ámbito de acceso para análisis e informes.

**Why this priority**: Permite aprovechar fuera del sistema los datos ya consultables.

**Independent Test**: Exportar un conjunto conocido en cada formato y comprobar campos,
registros, geometrías y restricción por empresa.

**Acceptance Scenarios**:

1. **Given** un conjunto autorizado, **When** exporta a PDF, Excel o CSV,
   **Then** el archivo contiene los registros y atributos del conjunto solicitado.
2. **Given** geometrías autorizadas, **When** exporta a GeoJSON, KML 3D o Shapefile,
   **Then** el archivo es legible por herramientas compatibles y conserva códigos y geometría.
3. **Given** datos fuera de su ámbito, **When** solicita exportarlos,
   **Then** no obtiene esos datos aunque cambie los identificadores de la solicitud.

### User Story 7 - Auditoría forense (Priority: P1)

Como responsable autorizado, necesito reconstruir quién cambió información, cuándo y
cómo, sin que la bitácora pueda alterarse desde la aplicación.

**Why this priority**: Es una obligación transversal de trazabilidad.

**Independent Test**: Ejecutar altas, cambios, bajas y acceso con datos de prueba y
comparar la bitácora con los valores anteriores y posteriores.

**Acceptance Scenarios**:

1. **Given** una inserción, actualización o borrado de entidad maestra, **When** se completa,
   **Then** se conserva un evento con usuario, acción, entidad, snapshots JSON, IP y fecha.
2. **Given** eventos registrados, **When** alguien intenta alterarlos o borrarlos desde
   la aplicación, **Then** se rechaza; solo usuarios autorizados pueden consultarlos.
3. **Given** un error técnico, **When** ocurre, **Then** el usuario recibe un mensaje
   amigable y el incidente se registra en el log diario correspondiente.

### Edge Cases

- Correos o NIT duplicados: rechazar sin crear registros parciales.
- Código presente en ambas fuentes: el tipo determina la fuente; la exclusividad sigue
  siendo por proyecto y código, sin excepción por tipo.
- Expediente propio frente a vecino, o entre dos vecinas: rechazar cualquier duplicado;
  el mismo código puede asociarse a proyectos diferentes.
- Actualización catastral que retire un expediente ya asociado: conservar la asociación
  histórica, señalar fuente no disponible y rechazar nuevas asociaciones inexistentes.
- Si una incidencia externa impide dibujar geometrías, conservar la consulta de atributos y comunicar la incidencia; no validar ni reparar topología en cada petición. El contrato catastral normal exige the_geom NOT NULL.
- AREA_HA ausente: calcular geodésicamente si existe geometría válida; si no, mostrar
  área no disponible. Cero oficial se distingue de valor ausente.
- Archivos corruptos o de formato no admitido: rechazar con mensaje comprensible sin
  afectar capas ya cargadas ni modificar fuentes externas.
- Caída de correo o de proveedores cartográficos: informar el fallo sin mostrar secretos
  ni trazas; las operaciones independientes del proveedor siguen disponibles.
- Color/identidad entre proyectos: el orden y apariencia corresponden al vínculo del
  proyecto, sin alterar la identidad compartida; identidad compartida aprobada por el usuario.
- Más de ocho vecinas: verificar distinción mediante color y patrón, además de herencia.
- Fechas finales anteriores a iniciales: rechazar con validación comprensible.
- Datos nulos de fuentes externas: conservar la ausencia y mostrarla explícitamente.
- No almacenar contraseñas, tokens de recuperación ni claves corporativas en snapshots
  de auditoría o logs; registrar el cambio de credenciales sin divulgar sus valores.

## Requirements *(mandatory)*

### Functional Requirements

Los rangos RF se conservan como fueron suministrados. No se inventa una correspondencia
individual para los subcódigos cuyo detalle no fue aportado; su aceptación se verifica
por las capacidades enumeradas y los escenarios de este documento.

| Referencia | Obligación verificable | Escenarios |
| --- | --- | --- |
| RF-01.1 | Autenticar correo y clave con bcrypt y bloquear acceso inválido/inactivo. | Historia 1, 1–2 |
| RF-01.2 | Crear, consultar, actualizar y eliminar usuarios con correo, documento, nombre y estado; validar correo único e integridad. | Historia 1, 3; historia 2, 6 |
| RF-01.3 | Aplicar los roles Administrador, Gerente, Geólogo e Inversionista y su matriz de permisos. | Historia 1, 5 |
| RF-01.4 | Recuperar clave por correo a través del servicio corporativo sendEmail.php. | Historia 1, 4 |
| RF-01.5 | Registrar ultimo_acceso después de cada login exitoso. | Historia 1, 1 |
| RF-01.6 | Gestionar pertenencia N:M Empresa–Usuario con rol por empresa. | Historia 1, 1 y 5 |
| RF-02.1 a RF-02.5 | CRUD de empresas con los campos canónicos, NIT único y controles de acceso. | Historia 2, 1 y 6 |
| RF-03.1 a RF-03.6 | CRUD de proyectos con sus campos canónicos, empresa única, autoría y asociaciones validadas RN-06/RN-12. | Historia 2, 2–6 |
| RF-04.1 a RF-04.5 | Consulta catastral de solo lectura por código, titular, municipio, departamento y mineral y ficha de 30 atributos. | Historia 3, 1–3 |
| RF-05.1 a RF-05.8 | CRUD de vecinas por proyecto, reutilización RN-04, tono violeta automático RN-07, herencia RN-08 y hatching al superar ocho (RF-05.8). | Historia 4, 1–6 |
| RF-06.1 a RF-06.22 | Visor con bases OSM, GoogleMutant y ESRI; simbología azul/verde/violeta, etiquetas, Identify completo, mediciones métricas y marcadores. | Historia 5, 1–4 y 6 |
| RF-06.12 | Identify muestra atributos completos de la fuente y filiación RN-11. | Historia 5, 3 |
| RF-06.21 / RF-06.22 | Importar Shapefile .zip, GeoJSON y KML con controles de color y opacidad. | Historia 5, 5 |
| RF-07.1 a RF-07.4 | Exportación tabular PDF/Excel/CSV y espacial GeoJSON/KML 3D/Shapefile dentro del ámbito autorizado. | Historia 6, 1–3 |
| RF-08.1 a RF-08.3 | Bitácora inmutable de cambios en entidades maestras con usuario, acción, entidad, snapshots, IP y fecha. | Historia 7, 1–3 |

### Matriz de permisos aprobada

El Administrador actúa globalmente como superusuario y administra usuarios, empresas y
asignaciones de roles. Los demás roles solo operan sobre empresas con vinculación vigente
en `empresa_usuarios`; la pertenencia no concede acceso a otras empresas.

| Capacidad | Administrador | Gerente | Inversionista | Geólogo |
| --- | --- | --- | --- | --- |
| Administrar usuarios, empresas y asignaciones de roles | Global | No | No | No |
| Consultar proyectos, expedientes y vecinas | Global | Su empresa | Su empresa | Su empresa |
| Crear, actualizar y eliminar proyectos y vínculos de vecinas | Global | No | No | Su empresa |
| Asociar o desvincular expedientes de proyectos y vecinas | Global | No | No | Su empresa |
| Exportar información autorizada | Global | Su empresa | Su empresa | No |
| Consultar auditoría | Global | Su empresa | Su empresa | No |
| Modificar o borrar auditoría desde la aplicación | No | No | No | No |
| Escribir en fuentes catastrales externas desde la aplicación | No | No | No | No |

La edición comprende el mantenimiento de proyectos y vecinas y sus asociaciones.
Exportación y auditoría no se conceden al Geólogo porque no fueron autorizadas.
Los permisos globales respetan la inmutabilidad de auditoría y la lectura exclusiva del
catastro. Solo el Administrador puede cambiar roles y ámbitos de acceso.

Una identidad vecina compartida no permite al Geólogo modificar datos que afecten
proyectos de otras empresas. Puede mantener los vínculos y simbología de su empresa;
los cambios de identidad compartida que afecten otras empresas corresponden al Administrador.
La consulta de identidad compartida no debe revelar vínculos ni expedientes ajenos.

La auditoría por empresa incluye únicamente eventos atribuibles a esa empresa, incluso
después de eliminar una entidad; los eventos globales o sin atribución empresarial
solo son consultables por el Administrador. El diseño debe conservar esa atribución.

**Aceptación adicional (RF-01.3, RF-01.6, RNF-13):**

1. **Given** un Administrador, **When** administra usuarios, empresas, roles o proyectos
   de distintas empresas, **Then** se permite la operación y se audita el cambio.
2. **Given** un Gerente o Inversionista vinculado a la empresa A, **When** consulta
   proyectos/vecinas, exporta o consulta auditoría de A, **Then** se permite; cualquier
   escritura o acceso a datos de la empresa B se rechaza sin divulgar información.
3. **Given** un Geólogo vinculado a A, **When** consulta o mantiene proyectos, vecinas
   y asociaciones de A, **Then** se permite; exportar, consultar auditoría, administrar
   usuarios/empresas o acceder a B se rechaza.
4. **Given** una vecina compartida entre A y B, **When** el Geólogo de A modifica su
   vínculo en A, **Then** B no cambia; un cambio de identidad que afecte B se rechaza.
5. **Given** eventos de A, B y eventos globales, **When** Gerente o Inversionista de A
   consulta auditoría, **Then** solo recibe eventos de A, también por acceso directo.

### Key Entities *(include if feature involves data)*

El esquema siguiente conserva los nombres, tipos, nulabilidad y reglas suministrados.
Es un contrato de datos solicitado por el usuario, no una migración ejecutable.
Las decisiones de consistencia resueltas se registran antes de traducir el contrato al diseño físico.

#### usuario

Cuenta de acceso seguro y trazable.

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador único. |
| correo | VARCHAR(255) | No | Único; login. |
| password_hash | VARCHAR(255) | No | password_hash(..., PASSWORD_BCRYPT), costo ≥ 10. |
| numero_documento | VARCHAR(50) | No | Documento de identidad. |
| nombre | VARCHAR(255) | No | Nombres y apellidos. |
| ultimo_acceso | TIMESTAMP | Sí | Último inicio exitoso. |
| estado | VARCHAR(20) / BOOL | No | ACTIVO / INACTIVO. |

Roles formales: Administrador, Gerente, Geólogo, Inversionista. Relación N:M con empresas
mediante `empresa_usuarios`, con rol asignado por empresa. Su contrato físico se define
en planificación conforme a la matriz de permisos aprobada anterior.

#### empresa

Persona jurídica titular de proyectos.

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador único. |
| nit | VARCHAR(50) | No | Único. |
| razon_social | VARCHAR(255) | No | Nombre legal. |
| correo | VARCHAR(255) | No | Correo corporativo principal. |
| telefono | VARCHAR(50) | Sí | Contacto. |
| direccion | VARCHAR(255) | Sí | Dirección fiscal/comercial. |
| ciudad | VARCHAR(100) | No | Municipio o ciudad. |
| departamento | VARCHAR(100) | No | Departamento. |
| logo_url | VARCHAR(255) | Sí | Ruta o URL del logotipo. |
| estado | VARCHAR(20) / BOOL | No | ACTIVO / INACTIVO. |

#### Proyecto

Unidad territorial y administrativa; pertenece a una única empresa (RN-01).

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador único. |
| empresa_id | INTEGER FK | No | empresa(id). |
| nombre | VARCHAR(255) | No | Nombre del proyecto. |
| descripcion | TEXT | Sí | Alcance técnico. |
| estado | VARCHAR(20) | No | ACTIVO / INACTIVO. |
| mineral_principal | VARCHAR(100) | No | Mineral objetivo. |
| fecha_inicio | DATE | Sí | Inicio de labores o constitución. |
| fecha_fin | DATE | Sí | Fin estimado o real. |
| centroide | GEOMETRY(Point, 4326) | Sí | Centroide geodésico calculado en PostGIS, permitido por constitución 4.2.0-SGPM; índice GiST obligatorio. |
| observaciones | TEXT | Sí | Notas técnicas. |
| creado_por | INTEGER FK | Sí | usuario(id), creador. |
| modificado_por | INTEGER FK | Sí | usuario(id), último modificador. |

#### Empresa Vecina

Identidad competidora compartida y reutilizable entre proyectos (RN-03, RN-04, RN-05).
**Decisión aprobada por el usuario (2026-10-11):** identidad compartida con vínculo por
proyecto y color por vínculo. Esta decisión reemplaza la vinculación directa del esquema
inicial. El color y el orden pertenecen al proyecto, nunca a la identidad global.

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador compartido de vecina. |
| nombre | VARCHAR(255) | No | Nombre comercial. |
| nit | VARCHAR(50) | Sí | NIT conocido. |
| observaciones | TEXT | Sí | Notas comerciales/jurídicas de la identidad. |

#### proyecto_empresas_vecinas

Entidad de enlace N:M aprobada. Su nombre físico se propone para planificación.

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador del vínculo. |
| proyecto_id | INTEGER FK | No | Proyecto(id). |
| empresa_vecina_id | INTEGER FK | No | Identidad compartida de Empresa Vecina. |
| color_hex | VARCHAR(10) | No | Violeta automático único por proyecto. |
| orden_registro | INTEGER | No | Orden estable de registro dentro del proyecto. |

Una identidad solo puede tener un vínculo por proyecto. Los patrones diagonales SVG
se determinan por vínculo al superar ocho vecinas. Todos sus expedientes dentro del
proyecto heredan color y patrón; vínculos en otros proyectos conservan su propia simbología.
Ejemplos de tonos: `#8a2be2`, `#9370db`, `#ba55d3`. Los patrones complementan, sin eliminar,
la obligación constitucional de color único dentro del proyecto.
La desvinculación afecta solo al proyecto; la edición de la identidad compartida afecta
sus referencias y debe distinguirse explícitamente de la edición del vínculo.

#### proyecto_expedientes

Vinculación de expedientes propios o vecinos con exclusividad RN-12.

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador de vínculo. |
| proyecto_id | INTEGER FK | No | Proyecto. |
| codigo_expediente | VARCHAR(100) | No | Código oficial. |
| tipo_expediente | VARCHAR(20) | No | TITULO o SOLICITUD. |
| filiacion | VARCHAR(20) | No | PROPIO o VECINO. |
| empresa_vecina_id | INTEGER FK | Sí | Empresa Vecina(id) compartida; obligatorio si VECINO y con vínculo al mismo proyecto. |
| fecha_asociacion | TIMESTAMP | No | Fecha de registro. |

Restricción inmutable: `CONSTRAINT uq_proyecto_expediente UNIQUE (proyecto_id, codigo_expediente)`. Un duplicado es una violación de unicidad, no una violación de clave foránea; se traduce a HTTP 409 y mensaje amigable sin exponer la excepción del motor.
El código debe existir en la fuente correspondiente al tipo (RN-06). VECINO requiere una
vecina del mismo proyecto; PROPIO exige ausencia de atribución vecina. Los títulos y
solicitudes de un mismo código tampoco pueden duplicarse dentro del proyecto (RN-12).

#### Auditoría

Registro forense inmutable (RF-08.1, RF-08.2).

| Campo | Tipo | Nulo | Regla |
| --- | --- | --- | --- |
| id | SERIAL PK | No | Identificador de evento. |
| usuario_id | INTEGER FK | Sí | usuario(id), ejecutor. |
| accion | VARCHAR(50) | No | INSERT, UPDATE, DELETE, LOGIN, LOGOUT. |
| entidad | VARCHAR(100) | No | Entidad afectada. |
| valores_anteriores | JSON / JSONB | Sí | Snapshot anterior, sin secretos. |
| valores_nuevos | JSON / JSONB | Sí | Snapshot posterior, sin secretos. |
| ip | VARCHAR(45) | No | IPv4/IPv6 de origen. |
| fecha | TIMESTAMP | No | Marca temporal exacta, DEFAULT NOW(). |

Los valores ausentes en INSERT/DELETE se representan coherentemente; no se inventa un
usuario cuando no existe identidad autenticada.

#### Fuentes catastrales externas

`geo_titulos_mineros` y `geo_solicitudes_mineras` residen en la misma base PostgreSQL
9.5.25; un proceso independiente las sincroniza externamente. La aplicación tiene acceso
de solo lectura. Ambas comparten exactamente estos 30 campos estandarizados:

| N.º | Campo | Tipo y regla |
| --- | --- | --- |
| 1 | CODIGO_EXPEDIENTE | VARCHAR(100) UNIQUE NOT NULL |
| 2 | MODALIDAD | VARCHAR(255) |
| 3 | TITULO_ESTADO | VARCHAR(100) |
| 4 | AREA_HA | NUMERIC(14, 4) |
| 5 | CLASIFICACION_MINERIA | VARCHAR(100) |
| 6 | ETAPA | VARCHAR(100) |
| 7 | SOLICITANTES_O_TITULARES | TEXT |
| 8 | MINERALES | TEXT |
| 9 | FECHA_DE_SOLICITUD | DATE |
| 10 | FECHA_DE_EXPEDICION | DATE |
| 11 | FECHA_DE_ANIVERSARIO | DATE |
| 12 | FECHA_DE_EXPIRACION | DATE |
| 13 | PUBLICADO_EN_RUCOM | VARCHAR(50) |
| 14 | PAR | VARCHAR(100) |
| 15 | TITLE_TYPE_CODE | VARCHAR(50) |
| 16 | TENURE_STATUS_CODE | VARCHAR(50) |
| 17 | MINING_CLASSIFICATION_CODE | VARCHAR(50) |
| 18 | TENURE_STAGE_CODE | VARCHAR(50) |
| 19 | CENTROID_COORDINATE | VARCHAR(100) |
| 20 | TENURE_ID | VARCHAR(100) |
| 21 | OBJECTID | INTEGER |
| 22 | MUNICIPIOS | VARCHAR(255) |
| 23 | DEPARTAMENTOS | VARCHAR(255) |
| 24 | MINERALES_INACTIVOS | TEXT |
| 25 | TIPO_TERMINACION | VARCHAR(100) |
| 26 | TERMINATION_TYPE_CODE | VARCHAR(50) |
| 27 | ACTIVE_TENURE_STATUS_IND | VARCHAR(10) |
| 28 | ACTIVE_APPLICATION_STATUS_IND | VARCHAR(10) |
| 29 | FECHA_PROCESO | DATE |
| 30 | the_geom | GEOMETRY(MultiPolygon, 4326) NOT NULL, índice espacial GiST |

CODIGO_EXPEDIENTE y the_geom son NOT NULL; los otros 28 campos admiten NULL conforme al contrato SQL aclarado. Las cinco fechas catastrales son DATE.

### Requerimientos no funcionales y restricciones obligatorias

Los detalles tecnológicos de esta sección y del contrato de datos se conservan porque
son requisitos explícitos del usuario y de la constitución. No constituyen decisiones
nuevas de implementación.

| Código | Requisito | Criterio de aceptación |
| --- | --- | --- |
| RNF-01 | Compatibilidad backend | PHP 7.0.33 estricto; cero características PHP 7.1+. |
| RNF-02 | Base de datos | PostgreSQL 9.5.25 con PostGIS 2.5.5. |
| RNF-03 | Servidor y SO | Host único Ubuntu 16.04 LTS de 64 bits, Apache/2.4.18 y HTTPS nativo en 443. |
| RNF-04 | Codificación | UTF-8 integral en base de datos, JSON y vistas. |
| RNF-05 | Consultas | Consultas estándar en menos de 3 segundos. |
| RNF-06 | Capas masivas | Renderizado en menos de 8 segundos; referencia de planificación: 5000 polígonos y hasta 500000 vértices, protocolo en quickstart.md. |
| RNF-07 | Mapa | Hasta 500 polígonos cargan en menos de 5 segundos. |
| RNF-08 | Concurrencia | Al menos 50 usuarios concurrentes. |
| RNF-09 | Tooltips | Aparición en menos de 200 ms. |
| RNF-10 | Credenciales | bcrypt mediante password_hash(), costo ≥ 10. |
| RNF-11 | Seguridad web | Tokens CSRF, protección XSS y SQLi mediante PDO. |
| RNF-12 | Transporte | HTTPS forzado con certificado SSL/TLS en Apache 2.4. |
| RNF-13 | RBAC | Acceso estricto según matriz de cuatro roles aprobada; Administrador global y demás roles limitados a su empresa. |
| RNF-14 | Notificaciones | POST HTTPS a https://sigmin.automatizzar.com/admin_emails/sendEmail.php mediante LibCurlModel; sin SMTP local. |
| RNF-15 | Responsividad | Operación en escritorio, tablet y móvil. |
| RNF-16 | Marco visual | Bootstrap 4.6. |
| RNF-17 | Errores | Alertas claras sin trazas de código fuente. |
| RNF-18 | Incidentes | Logs diarios en logs/sgpm-YYYY-MM-DD.log. |
| RNF-19 | Optimización espacial | Índices GiST obligatorios sobre the_geom. |
| RNF-20 | Referencia espacial | WGS84 EPSG:4326 para visualización; elipsoide para cálculo métrico. |

La constitución también exige las obligaciones referenciadas como RNF-23 y RNF-24:
índices espaciales y cálculo oficial geodésico. Su ausencia en la tabla de entrada
RNF-01–20 no elimina esas obligaciones ni autoriza renumerarlas.
El envío corporativo se realiza con LibCurlModel/cURL y USERKEY, APIKEY, email, asunto,
mensaje; el contrato no incluye valores secretos. Todo PHP inicia con strict_types=1.

**Contrato de notificaciones aclarado:**

Todas las notificaciones (recuperación de contraseña, bienvenida, reportes programados y
alertas de auditoría) utilizan POST al endpoint corporativo. No se instala ni gestiona SMTP
local. USERKEY y APIKEY se obtienen de variables de entorno Apache/Ubuntu y se exponen
como constantes seguras en config/config.php; nunca se incorporan valores al repositorio.
La periodicidad de reportes y los disparadores de alertas se concretarán en planificación,
sin conceder acceso a datos fuera de la matriz RBAC aprobada.

Código de referencia obligatorio de invocación (documentación, no código ejecutado):

```php
<?php
declare(strict_types=1);

$url = "https://sigmin.automatizzar.com/admin_emails/sendEmail.php";
$connCurl = new LibCurlModel;
$resultado = $connCurl->curl_download($url, array(
    "USERKEY" => $USERKEY,
    "APIKEY"  => $APIKEY,
    "email"   => $emailDestino,
    "asunto"  => $asunto,
    "mensaje" => $mensaje
));
```

Modelo de referencia obligatorio compatible con PHP 7.0.33:

```php
<?php
declare(strict_types=1);

class LibCurlModel {
    /**
     * Ejecuta una petición HTTP POST mediante cURL compatible con PHP 7.0.33
     * @param string $url
     * @param array $params
     * @return string
     */
    public function curl_download(string $url, array $params = []): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SGPM-System-Ubuntu16/1.0');

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error en LibCurlModel: " . $error);
        }
        curl_close($ch);
        return (string)$response;
    }
}
```

La excepción técnica queda encapsulada por el backend y no se presenta al usuario.
El retorno de una cadena no demuestra entrega del correo: la planificación debe precisar
la interpretación de estados HTTP y respuesta de negocio del servicio, no aportada aún.
No se invoca el servicio real en esta fase documental.

**Consumo espacial sin validación redundante:**

La sincronización externa de catastro ANM captura, sanea y depura las geometrías.
El SGPM consume y orquesta datos; no expone endpoints de ST_IsValid, ST_MakeValid ni
comprobación manual de traslapes, ni permite modificar límites legales oficiales.
Esto no elimina la validación de existencia RN-06, exclusividad RN-12 ni cálculo geodésico
RN-13. La importación de capas de trabajo y las herramientas de medición siguen disponibles.
La aplicación no ejecuta validaciones topológicas redundantes en cada petición.

**Fuentes externas y despliegue aclarados:**

sgpm_user dispone exclusivamente de SELECT sobre geo_titulos_mineros y
geo_solicitudes_mineras. El sincronizador usa un acceso independiente. Ambas fuentes
conservan los 30 campos descritos; permiten consulta unificada mediante UNION ALL,
preservando la identificación de la fuente y tipo de expediente. Ambas disponen de
índice USING GIST (the_geom).

Apache/2.4.18 tiene mod_rewrite, mod_ssl y mod_headers activos, certificado válido y
HTTPS nativo en 443, además de redirección 301/HSTS constitucional. PHP 7.0.33 dispone
de php7.0-pgsql, php7.0-curl, php7.0-json, php7.0-mbstring, php7.0-xml y php7.0-zip.
PostgreSQL 9.5.25 y PostGIS 2.5.5 escuchan en 127.0.0.1:5432 dentro del mismo
host Ubuntu 16.04 LTS de 64 bits. La latencia PHP–PostgreSQL objetivo es menor de 1 ms;
se verifica en el host de aceptación y no se presume garantizada por la topología.

**Aceptación de las decisiones aclaradas:**

- Una notificación de cada categoría utiliza POST corporativo y los cinco parámetros
  obligatorios, sin SMTP; ante fallo o timeout no se muestra éxito de entrega ni trazas.
- Ningún rol puede alterar límites oficiales ni escribir en las fuentes externas; una
  petición de consulta no ejecuta reparación o validación topológica redundante.
- Las dos fuentes tienen 30 columnas con tipos coincidentes, cinco fechas DATE,
  the_geom NOT NULL y GiST; sgpm_user no puede INSERT, UPDATE ni DELETE en ellas.
- Dos asociaciones simultáneas del mismo código y proyecto dejan un único vínculo y
  la otra solicitud recibe conflicto 409; cambiar tipo o filiación no elude la unicidad.
- Se verifica el host único, módulos, extensiones y versiones y se mide la latencia local.

### Aclaraciones de consistencia resueltas

- **AC-01 — Vecinas (RESUELTA, 2026-10-11):** El usuario aprueba identidad compartida y vínculo N:M por proyecto con color por vínculo; el contrato anterior queda reemplazado por las dos entidades descritas.
- **AC-02 — RBAC (RESUELTA, 2026-10-11):** Administrador global con permisos de superusuario y administración de asignaciones; Gerente e Inversionista consultan proyectos y vecinas, exportan y consultan auditoría solo de su empresa; Geólogo consulta y edita proyectos y vecinas solo de su empresa.
- **AC-03 — Centroide (RESUELTA, 2026-10-11):** El usuario selecciona B: enmienda constitucional ratificada a 4.2.0-SGPM para permitir GEOMETRY(Point, 4326) en centroides de proyectos con GiST obligatorio.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Todas las consultas estándar del conjunto de aceptación entregan resultados
  en menos de 3 segundos desde la solicitud hasta la presentación al usuario.
- **SC-002**: Cada carga de hasta 500 polígonos del conjunto de aceptación queda visible
  y utilizable en menos de 5 segundos.
- **SC-003**: Cada capa masiva del conjunto de referencia queda renderizada en menos de
  8 segundos; referencia fijada en planificación: 5000 polígonos y hasta 500000 vértices, con protocolo reproducible en quickstart.md.
- **SC-004**: El sistema atiende al menos 50 usuarios concurrentes manteniendo los
  umbrales de consulta y visualización en el entorno de aceptación documentado.
- **SC-005**: Todas las etiquetas de expedientes de prueba aparecen en menos de 200 ms
  desde que el cursor entra al elemento.
- **SC-006**: El 100 % de intentos de asociación inexistente o duplicada, incluidos los
  simultáneos, se rechaza; ningún proyecto queda con duplicados por código.
- **SC-007**: El 100 % de cambios de entidades maestras de prueba queda trazado con
  los campos obligatorios; ningún intento de modificar la bitácora desde la aplicación prospera.
- **SC-008**: El 100 % de pruebas de acceso fuera del ámbito de rol/empresa se rechaza,
  incluidas consultas directas y exportaciones.
- **SC-009**: Todas las fichas de prueba permiten inspeccionar los 30 atributos de la
  fuente y la filiación; todas las vecinas, incluidas la novena y posteriores, se distinguen.
- **SC-010**: Los tres formatos tabulares y los tres formatos geográficos de exportación
  producen archivos legibles con el conjunto autorizado; los tres formatos importables
  se visualizan con color y transparencia ajustables.
- **SC-011**: Los recorridos de acceso, consulta, identificación y exportación se completan
  en escritorio, tablet y móvil, sin exponer trazas en los errores provocados.

## Assumptions

- Se crea una única especificación integral para los ocho módulos, tal como se solicita.
  No se implementa código, sincronización catastral ni despliegue en esta fase.
- Los esquemas y tecnologías explícitos se preservan como contratos obligatorios aunque
  la plantilla normalmente evite detalles de implementación.
- El catálogo externo ya existe y su sincronización pertenece a un proceso independiente.
  Su disponibilidad, credenciales de lectura y esquema real son dependencias del sistema.
- El servicio corporativo de correo y los proveedores de cartografía requieren conectividad
  y credenciales/licencias válidas cuando corresponda; no se inventan valores de claves.
- Para estado de usuario/empresa se adopta provisionalmente VARCHAR(20) con ACTIVO/INACTIVO,
  una de las alternativas dadas. Se preserva la alternativa original en las tablas.
- La planificación fija nombres físicos, JSONB interno y fechas catastrales DATE conforme al contrato aclarado.
- Se asume provisionalmente azul para títulos propios, verde para solicitudes propias
  y violeta para expedientes vecinos; la leyenda debe hacer explícita la correspondencia.
- No se dispone de elevaciones: KML 3D no implica inventar alturas. Sin altitud fuente,
  se asume representación a nivel del terreno y se documenta esa limitación en la exportación.
- La importación agrega capas de trabajo; no constituye ingreso de expedientes oficiales
  ni autoriza escribir en las tablas externas. No se presupone persistencia de capas importadas.
- El protocolo de equipo cliente, red, complejidad geométrica, volumen masivo y mezcla concurrente queda fijado en [quickstart.md](quickstart.md); definir el protocolo no implica que RNF-06 haya pasado una prueba real.
- El borrado no puede dejar referencias inválidas; por defecto se rechaza si hay dependencias.
  La desactivación conserva historial. No se presupone borrado en cascada de auditoría.
- El conflicto espacial quedó resuelto por AC-03 y constitución 4.2.0-SGPM; no quedan aclaraciones bloqueantes.
