<?php
declare(strict_types=1);

namespace Sgpm\Auth;

class Authorization
{
    public static function permits(array $user, string $capability, array $membership = array()): bool
    {
        if (!isset($user['estado']) || $user['estado'] !== 'ACTIVO') { return false; }
        if (!empty($user['es_administrador']) && $user['es_administrador'] !== 'f') {
            return in_array($capability, array('admin', 'consultar', 'editar', 'exportar', 'auditoria'), true);
        }
        if (!isset($membership['estado'], $membership['rol']) || $membership['estado'] !== 'ACTIVO') { return false; }
        $roles = array('GERENTE', 'GEOLOGO', 'INVERSIONISTA');
        if (!in_array($membership['rol'], $roles, true)) { return false; }
        if ($capability === 'consultar') { return true; }
        if ($capability === 'editar') { return $membership['rol'] === 'GEOLOGO'; }
        if ($capability === 'exportar' || $capability === 'auditoria') {
            return in_array($membership['rol'], array('GERENTE', 'INVERSIONISTA'), true);
        }
        return false;
    }
}

