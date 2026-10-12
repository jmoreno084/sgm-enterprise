CREATE SCHEMA IF NOT EXISTS sgpm;
CREATE TABLE sgpm.usuario (
 id SERIAL PRIMARY KEY,
 correo VARCHAR(255) NOT NULL UNIQUE CHECK (correo = lower(btrim(correo))),
 password_hash VARCHAR(255) NOT NULL,
 numero_documento VARCHAR(50) NOT NULL,
 nombre VARCHAR(255) NOT NULL,
 ultimo_acceso TIMESTAMP NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
 es_administrador BOOLEAN NOT NULL DEFAULT FALSE,
 auth_version INTEGER NOT NULL DEFAULT 1 CHECK (auth_version > 0),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0)
);
CREATE TABLE sgpm.empresa (
 id SERIAL PRIMARY KEY,
 nit VARCHAR(50) NOT NULL UNIQUE,
 razon_social VARCHAR(255) NOT NULL,
 correo VARCHAR(255) NOT NULL,
 telefono VARCHAR(50) NULL,
 direccion VARCHAR(255) NULL,
 ciudad VARCHAR(100) NOT NULL,
 departamento VARCHAR(100) NOT NULL,
 logo_url VARCHAR(255) NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0)
);
CREATE TABLE sgpm.empresa_usuarios (
 id SERIAL PRIMARY KEY,
 empresa_id INTEGER NOT NULL REFERENCES sgpm.empresa(id) ON DELETE RESTRICT,
 usuario_id INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 rol VARCHAR(20) NOT NULL CHECK (rol IN ('GERENTE','GEOLOGO','INVERSIONISTA')),
 estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
 UNIQUE (empresa_id, usuario_id)
);
CREATE INDEX empresa_usuarios_usuario_idx ON sgpm.empresa_usuarios(usuario_id);

