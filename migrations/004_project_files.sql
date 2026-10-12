CREATE TABLE sgpm.proyecto_expedientes (
 id SERIAL PRIMARY KEY,
 proyecto_id INTEGER NOT NULL REFERENCES sgpm.proyecto(id) ON DELETE RESTRICT,
 codigo_expediente VARCHAR(100) NOT NULL,
 tipo_expediente VARCHAR(20) NOT NULL CHECK (tipo_expediente IN ('TITULO','SOLICITUD')),
 filiacion VARCHAR(20) NOT NULL CHECK (filiacion IN ('PROPIO','VECINO')),
 empresa_vecina_id INTEGER NULL REFERENCES sgpm.empresa_vecina(id) ON DELETE RESTRICT,
 fecha_asociacion TIMESTAMP NOT NULL DEFAULT timezone('UTC', now()),
 version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
 CONSTRAINT uq_proyecto_expediente UNIQUE(proyecto_id, codigo_expediente),
 CHECK ((filiacion='PROPIO' AND empresa_vecina_id IS NULL) OR
        (filiacion='VECINO' AND empresa_vecina_id IS NOT NULL)),
 FOREIGN KEY(proyecto_id, empresa_vecina_id)
 REFERENCES sgpm.proyecto_empresas_vecinas(proyecto_id, empresa_vecina_id) ON DELETE RESTRICT
);
CREATE FUNCTION sgpm.guard_expediente() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog, sgpm AS $$
BEGIN
 IF TG_OP = 'UPDATE' THEN
  IF NEW.codigo_expediente IS DISTINCT FROM OLD.codigo_expediente
     OR NEW.tipo_expediente IS DISTINCT FROM OLD.tipo_expediente
     OR NEW.proyecto_id IS DISTINCT FROM OLD.proyecto_id THEN
   RAISE EXCEPTION 'Identidad de expediente inmutable' USING ERRCODE='23514';
  END IF;
  RETURN NEW;
 END IF;
 IF NEW.tipo_expediente = 'TITULO' THEN
  IF NOT EXISTS (SELECT 1 FROM public.geo_titulos_mineros WHERE codigo_expediente=NEW.codigo_expediente) THEN
   RAISE EXCEPTION 'Expediente inexistente' USING ERRCODE='23514';
  END IF;
 ELSIF NEW.tipo_expediente = 'SOLICITUD' THEN
  IF NOT EXISTS (SELECT 1 FROM public.geo_solicitudes_mineras WHERE codigo_expediente=NEW.codigo_expediente) THEN
   RAISE EXCEPTION 'Expediente inexistente' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER guard_expediente BEFORE INSERT OR UPDATE ON sgpm.proyecto_expedientes
FOR EACH ROW EXECUTE PROCEDURE sgpm.guard_expediente();
CREATE INDEX proyecto_expedientes_vecina_idx ON sgpm.proyecto_expedientes(proyecto_id, empresa_vecina_id);

