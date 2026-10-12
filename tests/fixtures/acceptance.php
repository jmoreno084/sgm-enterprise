<?php
declare(strict_types=1);
return array(
    'companies' => array(
        array('nit'=>'SGPM-TEST-A','razon_social'=>'Empresa A','correo'=>'a@example.invalid','ciudad'=>'Bogotá','departamento'=>'Cundinamarca'),
        array('nit'=>'SGPM-TEST-B','razon_social'=>'Empresa B','correo'=>'b@example.invalid','ciudad'=>'Medellín','departamento'=>'Antioquia')
    ),
    'users' => array(
        array('correo'=>'admin@example.invalid','nombre'=>'Administrador prueba','numero_documento'=>'TEST-ADMIN','admin'=>true),
        array('correo'=>'gerente@example.invalid','nombre'=>'Gerente prueba','numero_documento'=>'TEST-GERENTE','rol'=>'GERENTE'),
        array('correo'=>'geologo@example.invalid','nombre'=>'Geólogo prueba','numero_documento'=>'TEST-GEOLOGO','rol'=>'GEOLOGO'),
        array('correo'=>'inversionista@example.invalid','nombre'=>'Inversionista prueba','numero_documento'=>'TEST-INVERSIONISTA','rol'=>'INVERSIONISTA')
    )
);

