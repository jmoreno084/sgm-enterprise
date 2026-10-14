<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $values=require dirname(__DIR__,2).'/config/config.php';
    $values['db_user']=getenv('SGPM_FIXTURE_DB_USER'); $values['db_password']=getenv('SGPM_FIXTURE_DB_PASSWORD');
    $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_fixture_owner');
    foreach (array('geo_titulos_mineros','geo_solicitudes_mineras') as $table) {
        foreach (array('UPDATE public.'.$table.' SET codigo_expediente=codigo_expediente WHERE false','DELETE FROM public.'.$table.' WHERE false',
            'INSERT INTO public.'.$table.' SELECT * FROM public.'.$table.' WHERE false','SELECT * FROM public.'.$table.' FOR UPDATE') as $sql) {
            try { $f->db->execute($sql); throw new RuntimeException('Catastro admitio escritura/lock runtime.'); }
            catch (PDOException $error) { $assert($error->getCode()==='42501','Fuente exclusivamente SELECT.'); }
        }
    }
    $code='READ-'.bin2hex(random_bytes(5));
    foreach (array('geo_titulos_mineros','geo_solicitudes_mineras') as $table) {
        $owner->execute('INSERT INTO public.'.$table.'(codigo_expediente,solicitantes_o_titulares,fecha_de_solicitud,the_geom) SELECT :code,:name,:date,the_geom FROM public.'.$table.' LIMIT 1',array('code'=>$code,'name'=>'José Muñoz','date'=>'2026-10-13'));
    }
    try {
        $repository=new Sgpm\Modules\Catastro\CadastreRepository($f->db);
        $assert($repository->listing(array('codigo'=>$code))['total']===2,'UNION ALL conserva mismo codigo en ambas fuentes.');
        $detail=$repository->find('TITULO',$code)['atributos'];
        $assert($detail['SOLICITANTES_O_TITULARES']==='José Muñoz' && $detail['FECHA_DE_SOLICITUD']==='2026-10-13','Acentos y DATE preservados.');
        $p=$f->project(); $other=$f->project(); $files=new Sgpm\Modules\Proyectos\ExpedienteService($f->db);
        $data=array('codigo_expediente'=>$code,'tipo_expediente'=>'TITULO','filiacion'=>'PROPIO');
        $files->save($p['id'],0,$data,$f->admin);
        $owner->execute('DELETE FROM public.geo_titulos_mineros WHERE codigo_expediente=:code',array('code'=>$code));
        $history=(new Sgpm\Modules\Proyectos\ExpedienteRepository($f->db))->listing($p['id'],array());
        $assert($history['total']===1 && !$history['items'][0]['fuente_disponible'],'Vinculo historico permanece con fuente retirada.');
        $f->expect(400,function () use ($files,$other,$data,$f) { $files->save($other['id'],0,$data,$f->admin); });
        $owner->execute('ALTER TABLE public.geo_titulos_mineros RENAME COLUMN modalidad TO "MODALIDAD"');
        try {
            $columns=(new Sgpm\Modules\Catastro\CadastreSchema($f->db))->columns('TITULO');
            $assert($columns['MODALIDAD']==='MODALIDAD','Adaptador reconoce identificadores quoted.');
            (new Sgpm\Modules\Catastro\CadastreRepository($f->db))->find('TITULO','T-A');
        } finally { $owner->execute('ALTER TABLE public.geo_titulos_mineros RENAME COLUMN "MODALIDAD" TO modalidad'); }
    } finally {
        foreach (array('geo_titulos_mineros','geo_solicitudes_mineras') as $table) { $owner->execute('DELETE FROM public.'.$table.' WHERE codigo_expediente=:code',array('code'=>$code)); }
    }
};
