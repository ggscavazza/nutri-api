<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\PasswordResetModel;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use CodeIgniter\Controller;
use Config\Services;

use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/login',
        summary: 'Login com email e senha',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/LoginResponse')),
            new OA\Response(response: 401, description: 'Credenciais inválidas', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function login()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return json_error('E-mail e senha são obrigatórios.', 'auth.missing_fields', 422);
        }

        $users = new UserModel();
        $user = $users->where('email', $email)->first();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return json_error('E-mail ou senha inválidos.', 'auth.invalid_credentials', 401);
        }
        if ($user['status'] !== 'ativo') {
            return json_error('Usuário inativo.', 'auth.user_inactive', 401);
        }

        $jwt = new JwtService();
        $access  = $jwt->generateAccessToken($user, 900); // 15 min
        $refresh = $jwt->generateRefreshToken();

        // salva refresh hash
        $rtModel = new RefreshTokenModel();
        $rtModel->insert([
            'user_id'    => $user['id'],
            'token'      => $jwt->hashToken($refresh),
            'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30), // 30 dias
        ]);

        return json_ok([
            'access_token'  => $access,
            'expires_in'    => 900,
            'refresh_token' => $refresh,
            'user'          => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/auth/refresh',
        summary: 'Renova access token usando refresh token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RefreshRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'access_token', type: 'string'),
                    new OA\Property(property: 'expires_in', type: 'integer', example: 900),
                    new OA\Property(property: 'refresh_token', type: 'string')
                ],
                type: 'object'
            )),
            new OA\Response(response: 401, description: 'Refresh inválido/expirado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    
    public function refresh()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $refresh = (string)($data['refresh_token'] ?? '');
        if ($refresh === '') {
            return json_error('Refresh token ausente.', 'auth.missing_refresh', 400);
        }

        $jwt = new JwtService();
        $hash = $jwt->hashToken($refresh);

        $rtModel = new RefreshTokenModel();
        $row = $rtModel->where('token', $hash)
                       ->where('revoked_at', null)
                       ->first();

        if (! $row) {
            return json_error('Refresh token inválido.', 'auth.invalid_refresh', 401);
        }
        if (strtotime($row['expires_at']) < time()) {
            return json_error('Refresh token expirado.', 'auth.expired_refresh', 401);
        }

        $user = (new UserModel())->find((int)$row['user_id']);
        if (! $user || $user['status'] !== 'ativo') {
            return json_error('Usuário inativo.', 'auth.user_inactive', 401);
        }

        // rotação: revoga o atual e cria um novo
        $rtModel->update($row['id'], ['revoked_at' => date('Y-m-d H:i:s')]);

        $newRefresh = $jwt->generateRefreshToken();
        $rtModel->insert([
            'user_id'    => $user['id'],
            'token'      => $jwt->hashToken($newRefresh),
            'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30),
        ]);

        $access = $jwt->generateAccessToken($user, 900);

        return json_ok([
            'access_token'  => $access,
            'expires_in'    => 900,
            'refresh_token' => $newRefresh,
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Revoga a sessão (refresh token)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string')
                ],
                required: ['refresh_token'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sessão encerrada', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ]
    )]

    public function logout()
    {
        // revoga um refresh token específico (boa prática para mobile logout)
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $refresh = (string)($data['refresh_token'] ?? '');
        if ($refresh === '') {
            return json_error('Refresh token ausente.', 'auth.missing_refresh', 400);
        }

        $jwt = new JwtService();
        $hash = $jwt->hashToken($refresh);

        $rtModel = new RefreshTokenModel();
        $row = $rtModel->where('token', $hash)->first();
        if ($row) {
            $rtModel->update($row['id'], ['revoked_at' => date('Y-m-d H:i:s')]);
        }

        return json_ok(['message' => 'Sessão encerrada.']);
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Solicita e-mail de redefinição de senha',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email')
                ],
                required: ['email'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK (resposta genérica por segurança)', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 500, description: 'Falha ao enviar e-mail', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    
    public function forgotPassword()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $email = trim((string)($data['email'] ?? ''));
        if ($email === '') {
            return json_error('E-mail é obrigatório.', 'auth.missing_email', 422);
        }

        $users = new UserModel();
        $user = $users->where('email', $email)->first();
        if (! $user) {
            // não revelar existência; responde ok
            return json_ok(['message' => 'Se o e-mail existir, enviaremos instruções.']);
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $tokenPlain);
        $expires = date('Y-m-d H:i:s', time() + 60 * 60); // 60 min

        (new PasswordResetModel())->insert([
            'user_id'    => $user['id'],
            'token'      => $hash,
            'expires_at' => $expires,
        ]);

        $frontReset = (string) env('app.frontResetURL', 'https://julianafagiani.com.br/sistema/reset.html');
        $sep       = str_contains($frontReset, '?') ? '&' : '?';
        $resetLink = $frontReset . $sep . http_build_query(['token' => $tokenPlain]);

        // envia e-mail
        $emailSvc = Services::email();
        $emailSvc->setTo($user['email']);
        $emailSvc->setFrom(env('app.email.fromEmail'), env('app.email.fromName'));
        $emailSvc->setSubject('Redefinição de senha');
        $body = view('emails/reset_password', [
            'name' => $user['name'],
            'resetLink' => $resetLink,
            'expiresAt' => $expires,
        ]);
        $emailSvc->setMessage($body);

        try {
            $emailSvc->send();
        } catch (\Throwable $e) {
            return json_error('Falha ao enviar e-mail de recuperação.', 'mail.send_error', 500);
        }

        return json_ok(['message' => 'Se o e-mail existir, enviaremos instruções.']);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Redefine a senha a partir do token enviado por e-mail',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'new_password', type: 'string', minLength: 6)
                ],
                required: ['token','new_password'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senha alterada com sucesso', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 400, description: 'Token inválido/expirado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Campos inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    
    public function resetPassword()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $tokenPlain = (string)($data['token'] ?? '');
        $newPass = (string)($data['new_password'] ?? '');

        if ($tokenPlain === '' || strlen($newPass) < 6) {
            return json_error('Token e nova senha (mín. 6) são obrigatórios.', 'auth.reset_invalid', 422);
        }

        $hash = hash('sha256', $tokenPlain);
        $prModel = new PasswordResetModel();
        $row = $prModel->where('token', $hash)->where('used_at', null)->first();

        if (! $row) {
            return json_error('Token inválido.', 'auth.reset_invalid', 400);
        }
        if (strtotime($row['expires_at']) < time()) {
            return json_error('Token expirado.', 'auth.reset_expired', 400);
        }

        $users = new UserModel();
        $user = $users->find((int)$row['user_id']);
        if (! $user) {
            return json_error('Usuário não encontrado.', 'auth.user_not_found', 404);
        }

        $users->update($user['id'], [
            'password_hash' => password_hash($newPass, PASSWORD_DEFAULT),
        ]);
        $prModel->update($row['id'], ['used_at' => date('Y-m-d H:i:s')]);

        return json_ok(['message' => 'Senha alterada com sucesso.']);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Dados do usuário autenticado',
        tags: ['Auth'],
        security: [ ['bearerAuth' => []] ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, description: 'Não autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function me()
    {
        $id   = (int) ($_SERVER['AUTH_USER_ID'] ?? 0);
        $role = (string) ($_SERVER['AUTH_ROLE'] ?? '');
        if (! $id) {
            return json_error('Não autenticado.', 'auth.unauthenticated', 401);
        }
        $user = (new UserModel())->find($id);
        return json_ok([
            'id'     => (int)$user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'status' => $user['status'],
            'photo'  => $user['photo_url'],
        ]);
    }
}
