-- SOLO pruebas, por DBA; preparar PostGIS antes de crear fuentes.
DO $$
BEGIN
 IF current_database() !~ '^sgpm_[a-z0-9_]*(test|acceptance)$' THEN
  RAISE EXCEPTION 'Requiere base aislada de pruebas';
 END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='sgpm_fixture_owner') THEN
  CREATE ROLE sgpm_fixture_owner NOLOGIN;
 END IF;
END;
$$;
GRANT USAGE, CREATE ON SCHEMA public TO sgpm_fixture_owner;
-- Conceder membresía en sgpm_fixture_owner solo a la cuenta de fixtures separada.
