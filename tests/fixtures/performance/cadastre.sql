-- Deterministic fixture only; never a runtime migration or production input.
DO $$ BEGIN
 IF current_database() !~ '^sgpm_[a-z0-9_]*(test|acceptance)$' OR current_user<>'sgpm_fixture_owner' THEN
  RAISE EXCEPTION 'Base y propietario fixture requeridos';
 END IF;
 IF (SELECT count(*) FROM public.geo_titulos_mineros)<>1 OR (SELECT count(*) FROM public.geo_solicitudes_mineras)<>1 THEN
  RAISE EXCEPTION 'Se requieren solo las dos fuentes iniciales; no borrar ni reemplazar datos';
 END IF;
END; $$;
-- Closure reuses the exact first point, avoiding floating-point round-off.
CREATE FUNCTION pg_temp.sgpm_fixture_ring(cx double precision,cy double precision,r double precision,n integer,direction double precision)
RETURNS public.geometry LANGUAGE sql AS $$
 SELECT public.ST_AddPoint(line,public.ST_StartPoint(line)) FROM
 (SELECT public.ST_MakeLine(ARRAY(SELECT public.ST_MakePoint(cx+r*cos(direction*2*pi()*v/n),cy+r*sin(direction*2*pi()*v/n)) FROM generate_series(0,n-1) v)) AS line) rings;
$$;
-- 100 vertices per complex MultiPolygon: outer41 + hole9 + second outer50.
WITH locations AS (
 SELECT i, -75.0+(i%100)*0.045 AS x, 3.5+floor(i/100.0)*0.045 AS y FROM generate_series(1,49999) i
), geometries AS (
 SELECT i,x,y,CASE WHEN i<=5000 THEN public.ST_Multi(public.ST_Collect(
  public.ST_MakePolygon(pg_temp.sgpm_fixture_ring(x,y,0.008,40,1),ARRAY[pg_temp.sgpm_fixture_ring(x,y,0.002,8,-1)]),
  public.ST_MakePolygon(pg_temp.sgpm_fixture_ring(x+0.025,y,0.004,49,1))))
 ELSE public.ST_Multi(public.ST_MakeEnvelope(x,y,x+0.01,y+0.01,4326)) END AS geom FROM locations
)
INSERT INTO public.geo_titulos_mineros(codigo_expediente,modalidad,titulo_estado,area_ha,clasificacion_mineria,etapa,solicitantes_o_titulares,minerales,fecha_de_solicitud,municipios,departamentos,objectid,the_geom)
SELECT 'PERF-T-'||lpad(i::text,5,'0'),'Concesión','ACTIVO',CASE WHEN i%3=0 THEN NULL ELSE 100+i%50 END,'PEQUEÑA','EXPLORACIÓN','Titular Ñ '||(i%500),CASE WHEN i%2=0 THEN 'Oro' ELSE 'Carbón' END,'2000-01-01'::date,'Municipio '||(i%100),'Departamento '||(i%20),i,public.ST_SetSRID(geom,4326) FROM geometries;
INSERT INTO public.geo_solicitudes_mineras(codigo_expediente,modalidad,titulo_estado,area_ha,clasificacion_mineria,etapa,solicitantes_o_titulares,minerales,fecha_de_solicitud,municipios,departamentos,objectid,the_geom)
SELECT 'PERF-S-'||lpad(i::text,5,'0'),'Concesión','SOLICITUD',NULL,'PEQUEÑA','EXPLORACIÓN','Solicitante Ñ '||(i%500),'Oro','2000-01-01'::date,'Municipio '||(i%100),'Departamento '||(i%20),i,public.ST_Multi(public.ST_MakeEnvelope(-75+(i%100)*0.045,3.5+floor(i/100.0)*0.045,-74.99+(i%100)*0.045,3.51+floor(i/100.0)*0.045,4326)) FROM generate_series(1,49999) i;
ANALYZE public.geo_titulos_mineros;
ANALYZE public.geo_solicitudes_mineras;
