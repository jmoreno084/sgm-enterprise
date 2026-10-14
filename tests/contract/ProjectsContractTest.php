<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext();
    $companies=new Sgpm\Modules\Empresas\CompanyService($f->db);
    $fields=array('nit'=>'TEST-'.bin2hex(random_bytes(5)),'razon_social'=>'Minería Bogotá','correo'=>'prueba@example.invalid','ciudad'=>'Bogotá','departamento'=>'Cundinamarca','estado'=>'ACTIVO');
    $f->expect(403,function () use ($companies,$f,$fields) { $companies->save(0,$fields,$f->geo); });
    $company=$companies->save(0,$fields,$f->admin);
    $assert($company['razon_social']===$fields['razon_social'],'Empresa conserva UTF-8.');
    $f->expect(409,function () use ($companies,$f,$fields) { $companies->save(0,$fields,$f->admin); });
    $f->expect(404,function () use ($companies,$f,$company) { $companies->find($company['id'],$f->geo); });
    $f->expect(409,function () use ($companies,$f,$company) { $companies->save($company['id'],array('version'=>99,'ciudad'=>'Otra'),$f->admin); });
    $projects=new Sgpm\Modules\Proyectos\ProjectService($f->db);
    $project=$f->project();
    $assert($project['creado_por']===$f->admin['usuario_id'] && $project['centroide']===null,'Autor de servidor y centroide NULL.');
    $f->expect(403,function () use ($projects,$f) { $projects->save($f->a,0,array('nombre'=>'No permitido','mineral_principal'=>'Oro'),$f->manager); });
    $f->expect(404,function () use ($projects,$f) { $projects->save($f->b,0,array('nombre'=>'Ajeno','mineral_principal'=>'Oro'),$f->geo); });
    foreach (array(array('empresa_id'=>$f->b),array('centroide'=>array(0,0)),array('creado_por'=>1)) as $forbidden) {
        $f->expect(400,function () use ($projects,$f,$project,$forbidden) { $projects->save($f->a,$project['id'],array_merge(array('version'=>1),$forbidden),$f->admin); });
    }
    $f->expect(400,function () use ($projects,$f,$project) { $projects->save($f->a,$project['id'],array('version'=>1,'fecha_inicio'=>'2026-10-10','fecha_fin'=>'2026-10-09'),$f->admin); });
    $updated=$projects->save($f->a,$project['id'],array('version'=>1,'nombre'=>'Editado Ñ'),$f->geo);
    $assert($updated['version']===2 && $updated['modificado_por']===$f->geo['usuario_id'],'Edicion y autor.');
    $f->expect(409,function () use ($projects,$f,$project) { $projects->save($f->a,$project['id'],array('version'=>1,'nombre'=>'Obsoleto'),$f->admin); });
    $f->expect(409,function () use ($companies,$f) { $companies->delete($f->a,1,$f->admin); });
    $companies->delete($company['id'],1,$f->admin);
};
