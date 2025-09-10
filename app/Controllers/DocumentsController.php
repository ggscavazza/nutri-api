<?php

namespace App\Controllers;

use App\Models\DocumentModel;
use App\Models\DocumentPatientModel;
use App\Models\UserModel;
use CodeIgniter\Controller;

use OpenApi\Attributes as OA;

class DocumentsController extends Controller
{
    private array $allowedMimes = [
        'application/pdf',
        'application/epub+zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // alguns servidores identificam DOCX como zip
        'application/zip',
    ];
    private array $allowedExt = ['pdf','epub','docx'];
    private int $maxSizeMB = 50;

    /** Lista documentos com regras por papel */
    #[OA\Get(
        path: '/documents',
        summary: 'Lista documentos conforme permissão do usuário',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'scope', in: 'query', schema: new OA\Schema(type: 'string', enum: ['geral','paciente'])),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ativo','inativo'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Document')),
                    new OA\Property(property: 'meta', type: 'object')
                ],
                type: 'object'
            )),
            new OA\Response(response: 401, description: 'Não autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function index()
    {
        $me = auth_user();
        $q       = trim((string)$this->request->getGet('q'));
        $scope   = $this->request->getGet('scope');   // geral|paciente|null
        $status  = $this->request->getGet('status');  // ativo|inativo|null

        $model = new DocumentModel();
        $builder = $model->select('id,title,description,file_type,scope,status,uploaded_by,size_bytes,created_at');

        if ($q !== '') {
            $builder->groupStart()
                    ->like('title', $q)
                    ->orLike('description', $q)
                    ->groupEnd();
        }
        if ($scope)  $builder->where('scope', $scope);
        if ($status) $builder->where('status', $status);

        if ($me['role'] === 'master') {
            // vê todos
        } elseif ($me['role'] === 'nutricionista') {
            // por padrão, vê apenas os que ele enviou
            $builder->where('uploaded_by', $me['id']);
        } elseif ($me['role'] === 'paciente') {
            // paciente vê "geral" + atribuídos a ele
            $db = db_connect();
            $sub = $db->table('document_patient')
                      ->select('document_id')
                      ->where('patient_id', $me['id'])
                      ->getCompiledSelect();
            $builder->groupStart()
                    ->where('scope', 'geral')
                    ->orWhere("id IN ($sub)")
                    ->groupEnd()
                    ->where('status', 'ativo');
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        $page = max(1, (int) $this->request->getGet('page'));
        $per  = min(100, max(1, (int) $this->request->getGet('per_page') ?: 20));

        $total = (clone $builder)->countAllResults(false);
        $builder->orderBy('id','desc')->limit($per, ($page-1)*$per);
        $rows = $builder->get()->getResultArray();

        return json_ok(['data' => $rows, 'meta' => ['page'=>$page,'per_page'=>$per,'total'=>(int)$total]]);
    }

    /** Cria documento (apenas nutricionista) */
    #[OA\Post(
        path: '/documents',
        summary: 'Upload de documento (nutricionista)',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['title','scope','file'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'scope', type: 'string', enum: ['geral','paciente']),
                        new OA\Property(property: 'patient_ids', type: 'array', items: new OA\Items(type: 'integer')),
                        new OA\Property(property: 'file', type: 'string', format: 'binary')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Criado', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 10),
                    new OA\Property(property: 'download_url', type: 'string')
                ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Campos/arquivo inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function create()
    {
        if (! auth_is('nutricionista')) {
            return json_error('Somente nutricionistas podem enviar documentos.', 'docs.forbidden', 403);
        }

        $title = trim((string) $this->request->getPost('title'));
        $scope = (string) $this->request->getPost('scope'); // geral|paciente
        $desc  = (string) $this->request->getPost('description');

        if ($title === '' || ! in_array($scope, ['geral','paciente'], true)) {
            return json_error('Título e escopo são obrigatórios.', 'docs.missing_fields', 422);
        }

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return json_error('Arquivo ausente ou inválido.', 'docs.bad_file', 422);
        }
        if ($file->getSizeByUnit('mb') > $this->maxSizeMB) {
            return json_error('Arquivo excede '.$this->maxSizeMB.'MB.', 'docs.too_large', 413);
        }

        // valida mime e extensão
        $mime = $file->getMimeType();
        $ext  = strtolower($file->getExtension());
        if (! in_array($mime, $this->allowedMimes, true) || ! in_array($ext, $this->allowedExt, true)) {
            return json_error('Tipo de arquivo não permitido (pdf, epub, docx).', 'docs.bad_type', 422);
        }

        // diretório de destino
        $subdir = 'uploads/ebooks/' . date('Y') . '/' . date('m');
        $destDir = WRITEPATH . $subdir;
        if (! is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        // nome único
        $newName = uniqid('doc_', true) . '.' . $ext;
        if (! $file->move($destDir, $newName)) {
            return json_error('Falha ao salvar o arquivo.', 'docs.save_error', 500);
        }

        $me = auth_user();
        $docModel = new DocumentModel();
        $docId = $docModel->insert([
            'title'        => $title,
            'description'  => $desc ?: null,
            'file_type'    => $ext,
            'storage_path' => $subdir . '/' . $newName, // relativo a WRITEPATH
            'download_url' => '', // definiremos após ter o id
            'size_bytes'   => $file->getSize(),
            'scope'        => $scope,
            'status'       => 'ativo',
            'uploaded_by'  => $me['id'],
        ]);

        // Define URL de download “canônica”
        $downloadUrl = rtrim((string) env('app.baseURL'), '/') . '/documents/' . $docId . '/download';
        $docModel->update($docId, ['download_url' => $downloadUrl]);

        // Se escopo paciente, processa atribuições
        if ($scope === 'paciente') {
            $patientIds = $this->request->getPost('patient_ids');
            if (empty($patientIds) || ! is_array($patientIds)) {
                return json_error('Informe patient_ids[] para escopo paciente.', 'docs.missing_patients', 422);
            }
            $linkModel = new DocumentPatientModel();
            $rows = [];
            foreach ($patientIds as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) $rows[] = ['document_id' => $docId, 'patient_id' => $pid];
            }
            if ($rows) $linkModel->insertBatch($rows);
        }

        return json_ok(['id' => (int) $docId, 'download_url' => $downloadUrl], 201);
    }

    #[OA\Get(
        path: '/documents/{id}',
        summary: 'Metadados do documento',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/Document')),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function show($id)
    {
        $doc = (new DocumentModel())->find($id);
        if (! $doc) return json_error('Documento não encontrado.', 'docs.not_found', 404);

        // Permissões de leitura de metadados:
        $me = auth_user();
        if ($me['role'] === 'master') {
            // ok
        } elseif ($me['role'] === 'nutricionista') {
            if ((int)$doc['uploaded_by'] !== $me['id']) {
                // se quiser permitir que nutris vejam meta de docs de outros, remova este bloco
                return json_error('Acesso negado.', 'auth.forbidden', 403);
            }
        } elseif ($me['role'] === 'paciente') {
            if ($doc['status'] !== 'ativo') return json_error('Acesso negado.', 'auth.forbidden', 403);
            if ($doc['scope'] === 'geral') {
                // ok
            } else {
                $exists = (new DocumentPatientModel())->where(['document_id'=>$id,'patient_id'=>$me['id']])->first();
                if (! $exists) return json_error('Acesso negado.', 'auth.forbidden', 403);
            }
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        return json_ok([
            'id' => (int)$doc['id'],
            'title' => $doc['title'],
            'description' => $doc['description'],
            'file_type' => $doc['file_type'],
            'scope' => $doc['scope'],
            'status' => $doc['status'],
            'size_bytes' => (int)$doc['size_bytes'],
            'uploaded_by' => (int)$doc['uploaded_by'],
            'created_at' => $doc['created_at'],
            'download_url' => $doc['download_url'],
        ]);
    }

    /** Edita metadados e reatribuições (nutri dono ou master) */
    #[OA\Put(
        path: '/documents/{id}',
        summary: 'Atualiza metadados e atribuições',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo']),
                    new OA\Property(property: 'scope', type: 'string', enum: ['geral','paciente']),
                    new OA\Property(property: 'patient_ids', type: 'array', items: new OA\Items(type: 'integer'))
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Atualizado', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function update($id)
    {
        $doc = (new DocumentModel())->find($id);
        if (! $doc) return json_error('Documento não encontrado.', 'docs.not_found', 404);

        $me = auth_user();
        $isOwner = ((int)$doc['uploaded_by'] === $me['id']);

        if ($me['role'] === 'master') {
            // ok (master pode tudo menos upload, conforme requisito)
        } elseif ($me['role'] === 'nutricionista') {
            if (! $isOwner) return json_error('Você só pode editar documentos que enviou.', 'docs.not_owner', 403);
        } else {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $data = [];
        if (isset($payload['title']))       $data['title']       = $payload['title'];
        if (isset($payload['description'])) $data['description'] = $payload['description'];
        if (isset($payload['status']) && in_array($payload['status'], ['ativo','inativo'], true)) {
            $data['status'] = $payload['status'];
        }
        if (isset($payload['scope']) && in_array($payload['scope'], ['geral','paciente'], true)) {
            $data['scope'] = $payload['scope'];
        }

        (new DocumentModel())->update($id, $data);

        // reatribuições quando scope='paciente'
        if (isset($payload['patient_ids']) && is_array($payload['patient_ids'])) {
            $link = new DocumentPatientModel();
            $link->where('document_id', $id)->delete();
            $rows = [];
            foreach ($payload['patient_ids'] as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) $rows[] = ['document_id'=>$id,'patient_id'=>$pid];
            }
            if ($rows) $link->insertBatch($rows);
        }

        return json_ok(['message' => 'Atualizado.']);
    }

    /** Toggle status (nutri dono ou master) */
    #[OA\Patch(
        path: '/documents/{id}/status',
        summary: 'Alterna status (ativo/inativo)',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'Status atualizado', content: new OA\JsonContent(
                properties: [ new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo']) ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function toggleStatus($id)
    {
        $doc = (new DocumentModel())->find($id);
        if (! $doc) return json_error('Documento não encontrado.', 'docs.not_found', 404);

        $me = auth_user();
        $isOwner = ((int)$doc['uploaded_by'] === $me['id']);

        if ($me['role'] === 'master' || ($me['role'] === 'nutricionista' && $isOwner)) {
            $new = $doc['status'] === 'ativo' ? 'inativo' : 'ativo';
            (new DocumentModel())->update($id, ['status' => $new]);
            return json_ok(['status' => $new]);
        }
        return json_error('Acesso negado.', 'auth.forbidden', 403);
    }

    /** Exclui (nutri dono ou master) */
    #[OA\Delete(
        path: '/documents/{id}',
        summary: 'Exclui documento (remove arquivo e vínculos)',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'Excluído', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Não encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function delete($id)
    {
        $doc = (new DocumentModel())->find($id);
        if (! $doc) return json_error('Documento não encontrado.', 'docs.not_found', 404);

        $me = auth_user();
        $isOwner = ((int)$doc['uploaded_by'] === $me['id']);

        if (! ($me['role'] === 'master' || ($me['role'] === 'nutricionista' && $isOwner))) {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }

        // remove links e apaga arquivo fisicamente
        (new DocumentPatientModel())->where('document_id', $id)->delete();

        $path = WRITEPATH . $doc['storage_path'];
        if (is_file($path)) @unlink($path);

        (new DocumentModel())->delete($id, true);
        return json_ok(['message' => 'Excluído.']);
    }

    /** Download protegido (checa permissão) */
    #[OA\Get(
        path: '/documents/{id}/download',
        summary: 'Download do arquivo (permissões aplicadas)',
        tags: ['Documents'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'OK (stream do arquivo)'),
            new OA\Response(response: 403, description: 'Proibido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Arquivo/metadados não encontrados', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    
    public function download($id)
    {
        $doc = (new DocumentModel())->find($id);
        if (! $doc || $doc['status'] !== 'ativo') return json_error('Documento não disponível.', 'docs.not_available', 404);

        $me = auth_user();
        $allowed = false;

        if ($me['role'] === 'master') {
            $allowed = true;
        } elseif ($me['role'] === 'nutricionista') {
            // qualquer nutri pode baixar? regra: sim (gestão)
            $allowed = true;
        } elseif ($me['role'] === 'paciente') {
            if ($doc['scope'] === 'geral') {
                $allowed = true;
            } else {
                $exists = (new DocumentPatientModel())->where(['document_id'=>$id,'patient_id'=>$me['id']])->first();
                $allowed = (bool)$exists;
            }
        }

        if (! $allowed) return json_error('Acesso negado.', 'auth.forbidden', 403);

        $path = WRITEPATH . $doc['storage_path'];
        if (! is_file($path)) return json_error('Arquivo não encontrado.', 'docs.file_missing', 410);

        // nome de download amigável
        $downloadName = preg_replace('/[^A-Za-z0-9\-_\.]+/','_', strtolower($doc['title'])) . '.' . $doc['file_type'];
        return $this->response->download($path, null)->setFileName($downloadName);
    }
}
