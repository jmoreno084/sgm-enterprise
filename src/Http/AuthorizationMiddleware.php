<?php
declare(strict_types=1);

namespace Sgpm\Http;

class AuthorizationMiddleware
{
    private $database;
    public function __construct(\Sgpm\Infrastructure\Database $database) { $this->database = $database; }

    public function user(array $session): array
    {
        if (!isset($session['usuario_id'], $session['auth_version'])) {
            throw new HttpException(401, 'NO_AUTENTICADO', 'Inicia sesión para continuar.');
        }
        $row = $this->database->execute('SELECT id, estado, es_administrador, auth_version FROM sgpm.usuario WHERE id=:id',
            array('id' => (int) $session['usuario_id']))->fetch();
        if (!$row || $row['estado'] !== 'ACTIVO' || (int) $row['auth_version'] !== (int) $session['auth_version']) {
            throw new HttpException(401, 'SESION_EXPIRADA', 'Tu sesión expiró. Inicia sesión nuevamente.');
        }
        return $row;
    }

    public function requireCompany(array $user, int $companyId, string $capability): array
    {
        $company = $this->database->execute('SELECT id, estado FROM sgpm.empresa WHERE id=:id',
            array('id' => $companyId))->fetch();
        $membership = $this->database->execute('SELECT rol, estado FROM sgpm.empresa_usuarios WHERE empresa_id=:empresa AND usuario_id=:usuario',
            array('empresa' => $companyId, 'usuario' => (int) $user['id']))->fetch();
        if (!$company || !\Sgpm\Auth\Authorization::permits($user, 'consultar', $membership ?: array())) {
            throw new HttpException(404, 'NO_ENCONTRADO', 'El recurso solicitado no está disponible.');
        }
        if (!\Sgpm\Auth\Authorization::permits($user, $capability, $membership ?: array())) {
            throw new HttpException(403, 'SIN_PERMISO', 'No tienes permiso para realizar esta operación.');
        }
        if ($capability === 'editar' && $company['estado'] !== 'ACTIVO'
            && !\Sgpm\Auth\Authorization::permits($user, 'admin')) {
            throw new HttpException(409, 'EMPRESA_INACTIVA', 'La empresa está inactiva.');
        }
        return $company;
    }
}

