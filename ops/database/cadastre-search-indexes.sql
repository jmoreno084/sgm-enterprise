-- Fixture/DBA preparation only. Never called by runtime or internal migrations.
DO $$
DECLARE source_table TEXT; canonical TEXT; physical TEXT; index_name TEXT; ordinal INTEGER;
BEGIN
 IF current_database() !~ '^sgpm_[a-z0-9_]*(test|acceptance)$' OR current_user<>'sgpm_fixture_owner' THEN
  RAISE EXCEPTION 'Solo propietario fixture en base aislada';
 END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_catalog.pg_extension WHERE extname='pg_trgm') THEN
  RAISE EXCEPTION 'DBA debe habilitar pg_trgm antes de preparar índices de carga';
 END IF;
 FOREACH source_table IN ARRAY ARRAY['geo_titulos_mineros','geo_solicitudes_mineras'] LOOP
  ordinal:=0;
  FOREACH canonical IN ARRAY ARRAY['codigo_expediente','solicitantes_o_titulares','municipios','departamentos','minerales'] LOOP
   ordinal:=ordinal+1;
   SELECT attname INTO STRICT physical FROM pg_catalog.pg_attribute WHERE attrelid=('public.'||source_table)::regclass AND attnum>0 AND NOT attisdropped AND lower(attname)=canonical;
   index_name:='sgpm_perf_'||source_table||'_'||ordinal||'_trgm';
   IF NOT EXISTS(SELECT 1 FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname=index_name) THEN
    EXECUTE format('CREATE INDEX %I ON public.%I USING gin (%I public.gin_trgm_ops)',index_name,source_table,physical);
   END IF;
  END LOOP;
  EXECUTE format('ANALYZE public.%I',source_table);
 END LOOP;
END;
$$;
