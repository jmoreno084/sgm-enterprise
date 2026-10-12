<?php
declare(strict_types=1);
return function ($assert) {
    $active = array('estado' => 'ACTIVO', 'es_administrador' => false);
    $matrix = array('GERENTE' => array(true,false,true,true), 'INVERSIONISTA' => array(true,false,true,true),
        'GEOLOGO' => array(true,true,false,false));
    foreach ($matrix as $role => $expected) {
        foreach (array('consultar','editar','exportar','auditoria') as $index => $capability) {
            $membership = array('estado' => 'ACTIVO', 'rol' => $role);
            $assert(Sgpm\Auth\Authorization::permits($active, $capability, $membership) === $expected[$index], $role . '/' . $capability);
            $membership['estado'] = 'INACTIVO';
            $assert(!Sgpm\Auth\Authorization::permits($active, $capability, $membership), 'Membresía revocada');
            $assert(!Sgpm\Auth\Authorization::permits($active, $capability), 'Sin membresía');
        }
    }
    foreach (array(true,'t') as $adminFlag) {
        $admin = array('estado' => 'ACTIVO', 'es_administrador' => $adminFlag);
        $assert(Sgpm\Auth\Authorization::permits($admin, 'admin'), 'Administrador global');
        $admin['estado'] = 'INACTIVO';
        $assert(!Sgpm\Auth\Authorization::permits($admin, 'admin'), 'Administrador inactivo');
    }
    $assert(!Sgpm\Auth\Authorization::permits(array('estado'=>'ACTIVO','es_administrador'=>'f'), 'admin'), 'Boolean PostgreSQL false');
};

