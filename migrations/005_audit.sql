CREATE TABLE sgpm.auditoria (
 id SERIAL PRIMARY KEY,
 usuario_id INTEGER NULL REFERENCES sgpm.usuario(id) ON DELETE RESTRICT,
 accion VARCHAR(50) NOT NULL CHECK (accion IN ('INSERT','UPDATE','DELETE','LOGIN','LOGOUT')),
 entidad VARCHAR(100) NOT NULL,
 valores_anteriores JSONB NULL,
 valores_nuevos JSONB NULL,
 ip VARCHAR(45) NOT NULL,
 fecha TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()),
 entidad_id INTEGER NULL,
 empresa_id_contexto INTEGER NULL,
 proyecto_id_contexto INTEGER NULL,
 request_id VARCHAR(64) NOT NULL
);
CREATE INDEX auditoria_empresa_fecha_idx ON sgpm.auditoria(empresa_id_contexto, fecha, id);
CREATE INDEX auditoria_fecha_idx ON sgpm.auditoria(fecha, id);
CREATE FUNCTION sgpm.audit_context() RETURNS JSONB
LANGUAGE plpgsql SET search_path = pg_catalog, sgpm AS $$
DECLARE actor TEXT; origin TEXT; request TEXT; system_actor TEXT;
BEGIN
 -- Lectura compatible 9.5: current_setting(name, missing_ok) no se usa.
 BEGIN
  actor := current_setting('sgpm.usuario_id');
  origin := current_setting('sgpm.ip');
  request := current_setting('sgpm.request_id');
  system_actor := current_setting('sgpm.sistema');
 EXCEPTION WHEN undefined_object THEN
  RAISE EXCEPTION 'Contexto de auditoria requerido' USING ERRCODE='23514';
 END;
 IF origin IS NULL OR origin='' OR request IS NULL OR request !~ '^[a-f0-9]{32}$'
    OR (coalesce(actor,'')='' AND coalesce(system_actor,'')<>'1') THEN
  RAISE EXCEPTION 'Contexto de auditoria requerido' USING ERRCODE='23514';
 END IF;
 PERFORM origin::inet;
 RETURN jsonb_build_object('usuario_id',nullif(actor,'')::integer,'ip',origin,'request_id',request);
END;
$$;
CREATE FUNCTION sgpm.audit_master() RETURNS trigger
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
 ELSIF TG_TABLE_NAME='empresa_usuarios' THEN company := (row_value->>'empresa_id')::integer;
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
CREATE FUNCTION sgpm.audit_auth(action TEXT) RETURNS INTEGER
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, sgpm AS $$
DECLARE context JSONB; event_id INTEGER;
BEGIN
 IF action NOT IN ('LOGIN','LOGOUT') THEN RAISE EXCEPTION 'Accion no permitida' USING ERRCODE='23514'; END IF;
 context := sgpm.audit_context();
 IF context->>'usuario_id' IS NULL THEN RAISE EXCEPTION 'Actor requerido' USING ERRCODE='23514'; END IF;
 INSERT INTO sgpm.auditoria(usuario_id,accion,entidad,ip,entidad_id,request_id)
 VALUES((context->>'usuario_id')::integer,action,'usuario',context->>'ip',
 (context->>'usuario_id')::integer,context->>'request_id') RETURNING id INTO event_id;
 RETURN event_id;
END;
$$;
CREATE FUNCTION sgpm.reject_audit_mutation() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog, sgpm AS $$
BEGIN RAISE EXCEPTION 'Auditoria inmutable' USING ERRCODE='42501'; END;
$$;
CREATE TRIGGER auditoria_inmutable BEFORE UPDATE OR DELETE OR TRUNCATE ON sgpm.auditoria
FOR EACH STATEMENT EXECUTE PROCEDURE sgpm.reject_audit_mutation();
CREATE TRIGGER audit_usuario AFTER INSERT OR UPDATE OR DELETE ON sgpm.usuario FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_empresa AFTER INSERT OR UPDATE OR DELETE ON sgpm.empresa FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_empresa_usuarios AFTER INSERT OR UPDATE OR DELETE ON sgpm.empresa_usuarios FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_proyecto AFTER INSERT OR UPDATE OR DELETE ON sgpm.proyecto FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_empresa_vecina AFTER INSERT OR UPDATE OR DELETE ON sgpm.empresa_vecina FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_proyecto_vecinas AFTER INSERT OR UPDATE OR DELETE ON sgpm.proyecto_empresas_vecinas FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
CREATE TRIGGER audit_expedientes AFTER INSERT OR UPDATE OR DELETE ON sgpm.proyecto_expedientes FOR EACH ROW EXECUTE PROCEDURE sgpm.audit_master();
REVOKE ALL ON FUNCTION sgpm.audit_master() FROM PUBLIC;
REVOKE ALL ON FUNCTION sgpm.audit_context() FROM PUBLIC;
REVOKE ALL ON FUNCTION sgpm.audit_auth(TEXT) FROM PUBLIC;
REVOKE ALL ON FUNCTION sgpm.reject_audit_mutation() FROM PUBLIC;
REVOKE ALL ON FUNCTION sgpm.guard_expediente() FROM PUBLIC;

