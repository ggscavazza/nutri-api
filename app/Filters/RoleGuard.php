<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowed = $arguments ?? [];
        $role = $_SERVER['AUTH_ROLE'] ?? null;
        if (! $role || ! in_array($role, $allowed, true)) {
            return json_error('Acesso negado para este perfil.', 'auth.forbidden', 403, ['allowed' => $allowed]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
