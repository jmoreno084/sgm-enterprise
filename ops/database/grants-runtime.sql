GRANT USAGE ON SCHEMA sgpm TO sgpm_user;
GRANT SELECT, INSERT, UPDATE, DELETE ON sgpm.usuario, sgpm.empresa, sgpm.empresa_usuarios,
sgpm.proyecto, sgpm.empresa_vecina, sgpm.proyecto_empresas_vecinas, sgpm.proyecto_expedientes TO sgpm_user;
GRANT USAGE, SELECT ON SEQUENCE sgpm.usuario_id_seq, sgpm.empresa_id_seq,
sgpm.empresa_usuarios_id_seq, sgpm.proyecto_id_seq, sgpm.empresa_vecina_id_seq,
sgpm.proyecto_empresas_vecinas_id_seq, sgpm.proyecto_expedientes_id_seq TO sgpm_user;
REVOKE ALL ON sgpm.auditoria, sgpm.auditoria_id_seq FROM PUBLIC, sgpm_user;
GRANT SELECT ON sgpm.auditoria TO sgpm_user;
GRANT EXECUTE ON FUNCTION sgpm.audit_auth(TEXT) TO sgpm_user;
REVOKE ALL ON sgpm.schema_migrations FROM PUBLIC, sgpm_user;
REVOKE CREATE ON SCHEMA sgpm FROM PUBLIC, sgpm_user;

