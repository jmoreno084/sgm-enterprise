-- Ejecutar por DBA en la base correcta ANTES de migrar, sin credenciales en este archivo.
-- No ejecutar automaticamente desde el servidor web.
DO $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='sgpm_owner') THEN CREATE ROLE sgpm_owner NOLOGIN; END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='sgpm_user') THEN CREATE ROLE sgpm_user LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT; END IF;
END;
$$;
REVOKE sgpm_owner FROM sgpm_user;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO sgpm_user, sgpm_owner;
REVOKE ALL ON public.geo_titulos_mineros, public.geo_solicitudes_mineras FROM PUBLIC, sgpm_user;
GRANT SELECT ON public.geo_titulos_mineros, public.geo_solicitudes_mineras TO sgpm_user, sgpm_owner;
-- Aplicar grants-runtime.sql DESPUES de migrar.
-- sgpm_owner necesita CREATE en la base para crear su esquema:
-- GRANT CREATE ON DATABASE <nombre_base_revisado> TO sgpm_owner;
-- Conceder membresia en sgpm_owner solo a la cuenta migradora separada.

