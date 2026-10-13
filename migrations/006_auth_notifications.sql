CREATE TABLE sgpm.recuperacion_claves (
 id SERIAL PRIMARY KEY,
 usuario_id INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 token_hash CHAR(64) NOT NULL UNIQUE CHECK (token_hash ~ '^[a-f0-9]{64}$'),
 creado_en TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()),
 expira_en TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()) + interval '30 minutes',
 consumido_en TIMESTAMP NULL,
 CHECK (expira_en = creado_en + interval '30 minutes')
);
CREATE INDEX recuperacion_usuario_idx ON sgpm.recuperacion_claves(usuario_id, expira_en);
CREATE TABLE sgpm.limites_acceso (
 clave_hash CHAR(64) PRIMARY KEY,
 inicio_ventana TIMESTAMP NOT NULL,
 intentos INTEGER NOT NULL DEFAULT 0 CHECK (intentos >= 0),
 bloqueado_hasta TIMESTAMP NULL
);
CREATE TABLE sgpm.notificaciones_outbox (
 id SERIAL PRIMARY KEY,
 tipo VARCHAR(30) NOT NULL CHECK (tipo IN ('RECUPERACION','BIENVENIDA','REPORTE','ALERTA_AUDITORIA')),
 usuario_destino_id INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 empresa_id_contexto INTEGER NULL,
 payload_cifrado TEXT NOT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' CHECK (estado IN ('PENDIENTE','PROCESANDO','ACEPTADO','FALLIDO','INDETERMINADO')),
 deduplicacion VARCHAR(255) NOT NULL UNIQUE,
 creado_en TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()),
 disponible_en TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()),
 expira_en TIMESTAMP NULL,
 procesado_en TIMESTAMP NULL,
 http_status INTEGER NULL,
 error_codigo VARCHAR(50) NULL
);
CREATE INDEX outbox_pendientes_idx ON sgpm.notificaciones_outbox(estado, disponible_en, id);
GRANT SELECT, INSERT, UPDATE, DELETE ON sgpm.recuperacion_claves, sgpm.limites_acceso, sgpm.notificaciones_outbox TO sgpm_user;
GRANT USAGE, SELECT ON SEQUENCE sgpm.recuperacion_claves_id_seq, sgpm.notificaciones_outbox_id_seq TO sgpm_user;
