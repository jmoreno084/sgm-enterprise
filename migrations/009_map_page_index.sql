-- Internal index only. Official source tables remain read-only to the runtime.
CREATE INDEX proyecto_expedientes_page_idx ON sgpm.proyecto_expedientes(proyecto_id,id);
