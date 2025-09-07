<?php

namespace App\Filters;

use App\Libraries\JwtService;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = $request->getHeaderLine('Authorization');
        if (! $auth || ! str_starts_with($auth, 'Bearer ')) {
            return json_error('Token ausente.', 'auth.missing_token', 401);
        }

        $token = substr($auth, 7);
        try {
            $jwt = new JwtService();
            $payload = $jwt->validateAccessToken($token);

            // carrega o usuário e verifica status
            $userId = (int) ($payload['sub'] ?? 0);
            $user = (new UserModel())->find($userId);
            if (! $user || $user['status'] !== 'ativo') {
                return json_error('Usuário inativo ou inexistente.', 'auth.user_inactive', 401);
            }

            // expõe info para os controllers (jeito simples)
            $_SERVER['AUTH_USER_ID'] = $user['id'];
            $_SERVER['AUTH_ROLE']    = $user['role'];

            return;
        } catch (\Throwable $e) {
            return json_error('Token inválido ou expirado.', 'auth.invalid_token', 401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
