<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class Mutation
{
    public static function run(Database $db,array $context,callable $work)
    {
        try { return (new Transaction($db))->run($context,$work); }
        catch (\PDOException $error) {
            if ($error->getCode()==='23505') {
                $duplicate=strpos($error->getMessage(),'uq_proyecto_expediente')!==false;
                throw new \Sgpm\Http\HttpException(409,$duplicate?'EXPEDIENTE_DUPLICADO':'REGISTRO_DUPLICADO',
                    $duplicate?'El expediente ya está asociado a este proyecto.':'Ya existe un registro con esos datos.');
            }
            if ($error->getCode()==='23503') { throw new \Sgpm\Http\HttpException(409,'REGISTRO_CON_DEPENDENCIAS','El registro tiene datos relacionados. Desactívalo para conservar el historial.'); }
            if (in_array($error->getCode(),array('22007','22008'),true) || ($error->getCode()==='23514' && strpos($error->getMessage(),'Expediente inexistente')!==false)) { throw new \Sgpm\Http\HttpException(400,'CAMPOS_INVALIDOS','Revisa los campos y la existencia del expediente en su fuente.'); }
            throw $error;
        }
    }
    public static function version(array $row,array $data)
    {
        if ((int)$row['version']!==\Sgpm\Http\Request::version($data)) { throw new \Sgpm\Http\HttpException(409,'VERSION_OBSOLETA','El registro fue modificado. Actualiza la página.'); }
    }
}
