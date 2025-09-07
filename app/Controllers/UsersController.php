<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

use OpenApi\Attributes as OA;

class UsersController extends Controller
{
    /** Lista usuários conforme o papel */
    #[OA\Get(
        path: '/users',
        summary: 'Lista usuários (master: todos; nutri: apenas pacientes)',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ativo','inativo'])),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['master','nutricionista','paciente'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                    new OA\Property(property: 'meta', type: 'object')
                ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function index()
    {
        $me = auth_user();
        $q = trim((string) $this->request->getGet('q'));
        $status = $this->request->getGet('status'); // ativo|inativo|null
        $role   = $this->request->getGet('role');   // master|nutricionista|paciente|null

        $model = new UserModel();
        $builder = $model->select('id,name,email,role,status,photo_url,created_at');

        if ($q !== '') {
            $builder->groupStart()
                    ->like('name', $q)
                    ->orLike('email', $q)
                    ->groupEnd();
        }
        if ($status) $builder->where('status', $status);
        if ($role)   $builder->where('role', $role);

        // Regras por papel
        if ($me['role'] === 'master') {
            // master vê todos (pode filtrar por role acima)
        } elseif ($me['role'] === 'nutricionista') {
            // nutri vê apenas pacientes
            $builder->where('role', 'paciente');
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        // paginação simples
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));

        $total = (clone $builder)->countAllResults(false);
        $builder->orderBy('id','desc')->limit($per, ($page-1)*$per);

        $rows = $builder->get()->getResultArray();

        return json_ok([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $per,
                'total' => (int) $total,
            ],
        ]);
    }

    /** Cria usuário (master: nutri/paciente; nutri: paciente) */
    #[OA\Post(
        path: '/users',
        summary: 'Cria usuário (master: qualquer; nutri: apenas paciente)',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role', type: 'string', enum: ['master','nutricionista','paciente']),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'photo_url', type: 'string', nullable: true)
                ],
                required: ['name','email','role','password'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Criado', content: new OA\JsonContent(
                properties: [ new OA\Property(property: 'id', type: 'integer', example: 123) ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Dados inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function create()
    {
        $me = auth_user();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'name'  => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'role'  => 'required|in_list[master,nutricionista,paciente]',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return json_error('Dados inválidos.', 'users.invalid', 422, ['details' => $this->validator->getErrors()]);
        }

        $role = $payload['role'];
        if ($me['role'] === 'master') {
            // ok: pode criar master/nutri/paciente, mas recomendo evitar criar outro master sem necessidade
        } elseif ($me['role'] === 'nutricionista') {
            if ($role !== 'paciente') {
                return json_error('Nutricionista só pode criar pacientes.', 'users.forbidden_role', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        $data = [
            'name'          => $payload['name'],
            'email'         => strtolower($payload['email']),
            'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
            'role'          => $role,
            'status'        => 'ativo',
            'photo_url'     => $payload['photo_url'] ?? null,
        ];

        $id = (new UserModel())->insert($data);
        return json_ok(['id' => (int) $id], 201);
    }

    #[OA\Get(
        path: '/users/{id}',
        summary: 'Exibe usuário',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function show($id)
    {
        $me = auth_user();
        $user = (new UserModel())->select('id,name,email,role,status,photo_url,created_at,updated_at')->find($id);
        if (! $user) return json_error('Usuário não encontrado.', 'users.not_found', 404);

        if ($me['role'] === 'master') {
            // ok
        } elseif ($me['role'] === 'nutricionista') {
            if ($user['role'] !== 'paciente') {
                return json_error('Acesso negado.', 'auth.forbidden', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        return json_ok($user);
    }

    /** Atualiza (master: qualquer; nutri: apenas pacientes) */
    #[OA\Put(
        path: '/users/{id}',
        summary: 'Atualiza usuário',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'photo_url', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo'])
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Atualizado', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function update($id)
    {
        $me = auth_user();
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $user = (new UserModel())->find($id);
        if (! $user) return json_error('Usuário não encontrado.', 'users.not_found', 404);

        if ($me['role'] === 'master') {
            // ok
        } elseif ($me['role'] === 'nutricionista') {
            if ($user['role'] !== 'paciente') {
                return json_error('Nutricionista só edita pacientes.', 'users.forbidden_role', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        $data = [];
        if (isset($payload['name']))  $data['name']  = $payload['name'];
        if (isset($payload['email']) && $payload['email'] !== $user['email']) {
            // validação email único
            $exists = (new UserModel())->where('email', strtolower($payload['email']))->where('id !=', $id)->first();
            if ($exists) return json_error('E-mail já em uso.', 'users.email_exists', 422);
            $data['email'] = strtolower($payload['email']);
        }
        if (!empty($payload['password'])) {
            $data['password_hash'] = password_hash($payload['password'], PASSWORD_DEFAULT);
        }
        if (isset($payload['photo_url'])) $data['photo_url'] = $payload['photo_url'];
        if (isset($payload['status'])) {
            if (! in_array($payload['status'], ['ativo','inativo'], true)) {
                return json_error('Status inválido.', 'users.bad_status', 422);
            }
            $data['status'] = $payload['status'];
        }

        if (empty($data)) return json_ok(['message' => 'Sem alterações.']);
        (new UserModel())->update($id, $data);
        return json_ok(['message' => 'Atualizado.']);
    }

    /** Ativa/Inativa */
    #[OA\Patch(
        path: '/users/{id}/status',
        summary: 'Alterna status (ativo/inativo)',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'Status atualizado', content: new OA\JsonContent(
                properties: [ new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo']) ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function toggleStatus($id)
    {
        $me = auth_user();
        $user = (new UserModel())->find($id);
        if (! $user) return json_error('Usuário não encontrado.', 'users.not_found', 404);

        if ($me['role'] === 'master') {
            // ok
        } elseif ($me['role'] === 'nutricionista') {
            if ($user['role'] !== 'paciente') {
                return json_error('Nutricionista só altera pacientes.', 'users.forbidden_role', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        $new = $user['status'] === 'ativo' ? 'inativo' : 'ativo';
        (new UserModel())->update($id, ['status' => $new]);
        return json_ok(['status' => $new]);
    }

    /** Exclusão (hard delete). Front deve pedir confirmação. */
    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Exclui usuário (hard delete)',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'Excluído', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function delete($id)
    {
        $me = auth_user();
        $user = (new UserModel())->find($id);
        if (! $user) return json_error('Usuário não encontrado.', 'users.not_found', 404);

        if ($me['role'] === 'master') {
            // ok
        } elseif ($me['role'] === 'nutricionista') {
            if ($user['role'] !== 'paciente') {
                return json_error('Nutricionista só exclui pacientes.', 'users.forbidden_role', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        (new UserModel())->delete($id, true); // hard (por ter deleted_at na tabela, true ignora soft)
        return json_ok(['message' => 'Excluído.']);
    }

    /** Paciente/nutri/master editam o próprio perfil */
    #[OA\Put(
        path: '/users/me',
        summary: 'Atualiza o próprio perfil',
        tags: ['Users'],
        security: [ ['bearerAuth' => []] ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'photo_url', type: 'string', nullable: true)
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil atualizado', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Não autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function updateSelf()
    {
        $me = auth_user();
        if (! $me['id']) return json_error('Não autenticado.', 'auth.unauthenticated', 401);

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $model = new UserModel();
        $user  = $model->find($me['id']);

        $data = [];
        if (isset($payload['name']))  $data['name']  = $payload['name'];
        if (isset($payload['email']) && $payload['email'] !== $user['email']) {
            $exists = $model->where('email', strtolower($payload['email']))->where('id !=', $me['id'])->first();
            if ($exists) return json_error('E-mail já em uso.', 'users.email_exists', 422);
            $data['email'] = strtolower($payload['email']);
        }
        if (!empty($payload['password'])) $data['password_hash'] = password_hash($payload['password'], PASSWORD_DEFAULT);
        if (isset($payload['photo_url'])) $data['photo_url'] = $payload['photo_url'];

        if (empty($data)) return json_ok(['message' => 'Sem alterações.']);
        $model->update($me['id'], $data);
        return json_ok(['message' => 'Perfil atualizado.']);
    }
}
