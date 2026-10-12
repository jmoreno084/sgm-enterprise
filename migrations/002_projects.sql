CREATE TABLE sgpm.proyecto (
 id SERIAL PRIMARY KEY,
 empresa_id INTEGER NOT NULL REFERENCES sgpm.empresa(id) ON DELETE RESTRICT,
 nombre VARCHAR(255) NOT NULL,
 descripcion TEXT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
 mineral_principal VARCHAR(100) NOT NULL,
 fecha_inicio DATE NULL,
 fecha_fin DATE NULL,
 centroide public.geometry(Point,4326) NULL,
 observaciones TEXT NULL,
 creado_por INTEGER NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 modificado_por INTEGER NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 vecina_secuencia INTEGER NOT NULL DEFAULT 0 CHECK (vecina_secuencia >= 0),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
 CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio)
);
CREATE INDEX proyecto_empresa_idx ON sgpm.proyecto(empresa_id);
CREATE INDEX proyecto_centroide_gist ON sgpm.proyecto USING GIST(centroide);

