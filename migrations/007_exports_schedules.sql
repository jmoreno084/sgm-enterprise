CREATE TABLE sgpm.programacion_reportes (
 id SERIAL PRIMARY KEY,
 empresa_id INTEGER NOT NULL REFERENCES sgpm.empresa(id) ON DELETE RESTRICT,
 creado_por INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 formato VARCHAR(20) NOT NULL CHECK (formato IN ('PDF','XLSX','CSV','GEOJSON','KML','SHAPEFILE')),
 filtros JSONB NOT NULL CHECK (jsonb_typeof(filtros)='object'),
 hora_local TIME NOT NULL DEFAULT '09:00',
 zona VARCHAR(50) NOT NULL DEFAULT 'America/Bogota' CHECK (zona='America/Bogota'),
 estado VARCHAR(20) NOT NULL DEFAULT 'INACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version>0)
);
CREATE TABLE sgpm.programacion_destinatarios (
 programacion_id INTEGER NOT NULL REFERENCES sgpm.programacion_reportes(id) ON DELETE RESTRICT,
 usuario_id INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 PRIMARY KEY(programacion_id,usuario_id)
);
CREATE TABLE sgpm.exportacion_job (
 id SERIAL PRIMARY KEY,
 usuario_id INTEGER NOT NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 empresa_id INTEGER NOT NULL REFERENCES sgpm.empresa(id) ON DELETE RESTRICT,
 proyecto_id INTEGER NULL REFERENCES sgpm.proyecto(id) ON DELETE RESTRICT,
 formato VARCHAR(20) NOT NULL CHECK (formato IN ('PDF','XLSX','CSV','GEOJSON','KML','SHAPEFILE')),
 filtros JSONB NOT NULL CHECK (jsonb_typeof(filtros)='object'),
 estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' CHECK (estado IN ('PENDIENTE','PROCESANDO','LISTO','FALLIDO','EXPIRADO')),
 archivo_relativo VARCHAR(255) NULL,
 creado_en TIMESTAMP NOT NULL DEFAULT timezone('UTC',now()),
 expira_en TIMESTAMP NOT NULL DEFAULT timezone('UTC',now())+interval '24 hours',
 error_codigo VARCHAR(50) NULL,
 programacion_id INTEGER NULL REFERENCES sgpm.programacion_reportes(id) ON DELETE RESTRICT,
 fecha_programada DATE NULL,
 procesando_en TIMESTAMP NULL,
 UNIQUE(programacion_id,fecha_programada,usuario_id),
 CHECK ((programacion_id IS NULL)=(fecha_programada IS NULL))
);
CREATE INDEX exportacion_pendiente_idx ON sgpm.exportacion_job(estado,id);
CREATE INDEX exportacion_solicitante_idx ON sgpm.exportacion_job(usuario_id,empresa_id,id);
CREATE INDEX programacion_empresa_idx ON sgpm.programacion_reportes(empresa_id,id);
-- Reuse the master audit function; only extend its enterprise scope mapping.
CREATE OR REPLACE FUNCTION sgpm.audit_master() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, sgpm AS $$
DECLARE before_value JSONB; after_value JSONB; row_value JSONB;
 context JSONB; company INTEGER; project INTEGER; entity INTEGER;
BEGIN
 context := sgpm.audit_context();
 IF TG_OP <> 'INSERT' THEN before_value := to_jsonb(OLD) - 'password_hash' - 'auth_version'; END IF;
 IF TG_OP <> 'DELETE' THEN after_value := to_jsonb(NEW) - 'password_hash' - 'auth_version'; END IF;
 row_value := coalesce(after_value,before_value);
 entity := (row_value->>'id')::integer;
 IF TG_TABLE_NAME='empresa' THEN company := entity;
 ELSIF TG_TABLE_NAME IN ('empresa_usuarios','programacion_reportes') THEN company := (row_value->>'empresa_id')::integer;
 ELSIF TG_TABLE_NAME='programacion_destinatarios' THEN
  entity := (row_value->>'programacion_id')::integer;
  SELECT empresa_id INTO STRICT company FROM sgpm.programacion_reportes WHERE id=entity;
 ELSIF TG_TABLE_NAME='proyecto' THEN company := (row_value->>'empresa_id')::integer; project := entity;
 ELSIF TG_TABLE_NAME IN ('proyecto_empresas_vecinas','proyecto_expedientes') THEN
  project := (row_value->>'proyecto_id')::integer;
  SELECT empresa_id INTO STRICT company FROM sgpm.proyecto WHERE id=project;
 END IF;
 INSERT INTO sgpm.auditoria(usuario_id,accion,entidad,valores_anteriores,valores_nuevos,ip,
 entidad_id,empresa_id_contexto,proyecto_id_contexto,request_id)
 VALUES((context->>'usuario_id')::integer,TG_OP,TG_TABLE_NAME,before_value,after_value,
 context->>'ip',entity,company,project,context->>'request_id');
 IF TG_OP='DELETE' THEN RETURN OLD; END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER audit_programacion AFTER INSERT OR UPDATE OR DELETE ON sgpm.programacion_reportes FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_destinatarios AFTER INSERT OR UPDATE OR DELETE ON sgpm.programacion_destinatarios FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
GRANT SELECT,INSERT,UPDATE,DELETE ON sgpm.programacion_reportes,sgpm.programacion_destinatarios,sgpm.exportacion_job TO sgpm_user;
GRANT USAGE,SELECT ON SEQUENCE sgpm.programacion_reportes_id_seq,sgpm.exportacion_job_id_seq TO sgpm_user;
