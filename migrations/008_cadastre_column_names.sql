-- Preserve migration004 checksum; support canonical source columns quoted by DBA.
CREATE OR REPLACE FUNCTION sgpm.guard_expediente() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog, sgpm AS $$
DECLARE source_table TEXT; code_column TEXT; column_count INTEGER; present BOOLEAN;
BEGIN
 IF TG_OP='UPDATE' THEN
  IF NEW.codigo_expediente IS DISTINCT FROM OLD.codigo_expediente
     OR NEW.tipo_expediente IS DISTINCT FROM OLD.tipo_expediente
     OR NEW.proyecto_id IS DISTINCT FROM OLD.proyecto_id THEN
   RAISE EXCEPTION 'Identidad de expediente inmutable' USING ERRCODE='23514';
  END IF;
  RETURN NEW;
 END IF;
 IF NEW.tipo_expediente='TITULO' THEN source_table:='public.geo_titulos_mineros';
 ELSIF NEW.tipo_expediente='SOLICITUD' THEN source_table:='public.geo_solicitudes_mineras';
 ELSE RAISE EXCEPTION 'Tipo de expediente inexistente' USING ERRCODE='23514'; END IF;
 SELECT count(*),min(attname::text) INTO column_count,code_column FROM pg_catalog.pg_attribute
 WHERE attrelid=source_table::regclass AND NOT attisdropped AND attnum>0 AND lower(attname)='codigo_expediente';
 IF column_count<>1 THEN RAISE EXCEPTION 'Columna oficial ambigua' USING ERRCODE='23514'; END IF;
 EXECUTE 'SELECT EXISTS(SELECT 1 FROM '||source_table||' WHERE '||quote_ident(code_column)||'=$1)' INTO present USING NEW.codigo_expediente;
 IF NOT present THEN RAISE EXCEPTION 'Expediente inexistente' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END;
$$;
