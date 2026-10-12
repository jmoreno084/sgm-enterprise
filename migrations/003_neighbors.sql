CREATE TABLE sgpm.empresa_vecina (
 id SERIAL PRIMARY KEY,
 nombre VARCHAR(255) NOT NULL,
 nit VARCHAR(50) NULL UNIQUE CHECK (nit IS NULL OR btrim(nit) <> ''),
 observaciones TEXT NULL,
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0)
);
CREATE TABLE sgpm.proyecto_empresas_vecinas (
 id SERIAL PRIMARY KEY,
 proyecto_id INTEGER NOT NULL REFERENCES sgpm.proyecto(id) ON DELETE RESTRICT,
 empresa_vecina_id INTEGER NOT NULL REFERENCES sgpm.empresa_vecina(id) ON DELETE RESTRICT,
 color_hex VARCHAR(10) NOT NULL CHECK (color_hex ~ '^#[0-9a-fA-F]{6}$'),
 orden_registro INTEGER NOT NULL CHECK (orden_registro > 0),
 observaciones TEXT NULL,
 patron_indice INTEGER NOT NULL DEFAULT 0 CHECK (patron_indice >= 0),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
 UNIQUE(proyecto_id, empresa_vecina_id),
 UNIQUE(proyecto_id, orden_registro),
 UNIQUE(proyecto_id, color_hex)
);
CREATE INDEX proyecto_vecinas_identidad_idx ON sgpm.proyecto_empresas_vecinas(empresa_vecina_id);

