<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext();
    $values=require dirname(__DIR__,2).'/config/config.php'; $values['db_user']=getenv('SGPM_FIXTURE_DB_USER'); $values['db_password']=getenv('SGPM_FIXTURE_DB_PASSWORD');
    $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_fixture_owner');
    $changed=array();
    try {
        foreach (array('codigo_expediente'=>'CODIGO_EXPEDIENTE','the_geom'=>'THE_GEOM') as $old=>$new) { $owner->execute('ALTER TABLE public.geo_titulos_mineros RENAME COLUMN '.$old.' TO "'.$new.'"'); $changed[$old]=$new; }
        $p=$f->project(); $file=(new Sgpm\Modules\Proyectos\ExpedienteService($f->db))->save($p['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
        $assert($file['codigo_expediente']==='T-A','Asociación y guardia admiten columna oficial entrecomillada.');
        $project=(new Sgpm\Modules\Proyectos\ProjectRepository($f->db))->find($p['id']);
        $assert($project['centroide']!==null,'Centroide con columna geométrica entrecomillada.');
        $listing=(new Sgpm\Modules\Proyectos\ExpedienteRepository($f->db))->listing($p['id'],array());
        $assert($listing['items'][0]['fuente_disponible'],'Listado conserva fuente disponible.');
        $map=(new Sgpm\Modules\Visor\MapRepository($f->db))->features($p['id'],array(),$f->geo);
        $assert(count($map['features'])===1,'Mapa con columnas físicas entrecomilladas.');
    } finally { foreach (array_reverse($changed,true) as $old=>$new) { $owner->execute('ALTER TABLE public.geo_titulos_mineros RENAME COLUMN "'.$new.'" TO '.$old); } }
};
