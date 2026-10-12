-- Solo para una base aislada; ejecutar como sgpm_fixture_owner, nunca runtime.
DO $$ BEGIN
 IF current_database() !~ '^sgpm_[a-z0-9_]*(test|acceptance)$' OR current_user <> 'sgpm_fixture_owner' THEN
  RAISE EXCEPTION 'Base o propietario fixture no autorizado';
 END IF;
END; $$;
CREATE TABLE public.geo_titulos_mineros (
 codigo_expediente VARCHAR(100) UNIQUE NOT NULL,
 modalidad VARCHAR(255),
 titulo_estado VARCHAR(100),
 area_ha NUMERIC(14, 4),
 clasificacion_mineria VARCHAR(100),
 etapa VARCHAR(100),
 solicitantes_o_titulares TEXT,
 minerales TEXT,
 fecha_de_solicitud DATE,
 fecha_de_expedicion DATE,
 fecha_de_aniversario DATE,
 fecha_de_expiracion DATE,
 publicado_en_rucom VARCHAR(50),
 par VARCHAR(100),
 title_type_code VARCHAR(50),
 tenure_status_code VARCHAR(50),
 mining_classification_code VARCHAR(50),
 tenure_stage_code VARCHAR(50),
 centroid_coordinate VARCHAR(100),
 tenure_id VARCHAR(100),
 objectid INTEGER,
 municipios VARCHAR(255),
 departamentos VARCHAR(255),
 minerales_inactivos TEXT,
 tipo_terminacion VARCHAR(100),
 termination_type_code VARCHAR(50),
 active_tenure_status_ind VARCHAR(10),
 active_application_status_ind VARCHAR(10),
 fecha_proceso DATE,
 the_geom public.geometry(MultiPolygon,4326) NOT NULL
);
CREATE INDEX geo_titulos_mineros_geom_gist ON public.geo_titulos_mineros USING GIST(the_geom);
INSERT INTO public.geo_titulos_mineros(codigo_expediente,area_ha,solicitantes_o_titulares,minerales,municipios,departamentos,the_geom) VALUES('T-A',NULL,'Titular prueba','Oro','Bogota','Cundinamarca',public.ST_GeomFromText('MULTIPOLYGON(((-74.1 4.6,-74.09 4.6,-74.09 4.61,-74.1 4.61,-74.1 4.6)))',4326));
CREATE TABLE public.geo_solicitudes_mineras (
 codigo_expediente VARCHAR(100) UNIQUE NOT NULL,
 modalidad VARCHAR(255),
 titulo_estado VARCHAR(100),
 area_ha NUMERIC(14, 4),
 clasificacion_mineria VARCHAR(100),
 etapa VARCHAR(100),
 solicitantes_o_titulares TEXT,
 minerales TEXT,
 fecha_de_solicitud DATE,
 fecha_de_expedicion DATE,
 fecha_de_aniversario DATE,
 fecha_de_expiracion DATE,
 publicado_en_rucom VARCHAR(50),
 par VARCHAR(100),
 title_type_code VARCHAR(50),
 tenure_status_code VARCHAR(50),
 mining_classification_code VARCHAR(50),
 tenure_stage_code VARCHAR(50),
 centroid_coordinate VARCHAR(100),
 tenure_id VARCHAR(100),
 objectid INTEGER,
 municipios VARCHAR(255),
 departamentos VARCHAR(255),
 minerales_inactivos TEXT,
 tipo_terminacion VARCHAR(100),
 termination_type_code VARCHAR(50),
 active_tenure_status_ind VARCHAR(10),
 active_application_status_ind VARCHAR(10),
 fecha_proceso DATE,
 the_geom public.geometry(MultiPolygon,4326) NOT NULL
);
CREATE INDEX geo_solicitudes_mineras_geom_gist ON public.geo_solicitudes_mineras USING GIST(the_geom);
INSERT INTO public.geo_solicitudes_mineras(codigo_expediente,area_ha,solicitantes_o_titulares,minerales,municipios,departamentos,the_geom) VALUES('S-A',NULL,'Titular prueba','Oro','Bogota','Cundinamarca',public.ST_GeomFromText('MULTIPOLYGON(((-74.1 4.6,-74.09 4.6,-74.09 4.61,-74.1 4.61,-74.1 4.6)))',4326));
