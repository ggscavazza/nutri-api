<?php

namespace App\Controllers;

use App\Models\NutritionistPatientModel;
use App\Models\UserModel;
use CodeIgniter\Controller;

use OpenApi\Attributes as OA;

class LinksController extends Controller
{
    #[OA\Get(
        path: '/nutritionists/{id}/patients',
        summary: 'Lista pacientes vinculados a um nutricionista',
        tags: ['Links'],
        security: [ ['bearerAuth' => []] ],
        parameters: [ new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')) ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [ new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')) ],
                type: 'object'
            )),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function listPatients($nutritionistId)
    {
        // master ou nutri (o próprio)
        $me = auth_user();
        if (! auth_is('nutricionista','master')) {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }
        if ($me['role'] === 'nutricionista' && $me['id'] !== (int)$nutritionistId) {
            return json_error('Você só pode ver seus próprios vínculos.', 'links.forbidden', 403);
        }

        $db = db_connect();
        $rows = $db->table('nutritionist_patient np')
            ->select('u.id, u.name, u.email, u.status, u.photo_url')
            ->join('users u', 'u.id = np.patient_id', 'inner')
            ->where('np.nutritionist_id', (int)$nutritionistId)
            ->get()->getResultArray();

        return json_ok(['data' => $rows]);
    }

    #[OA\Post(
        path: '/nutritionists/{id}/patients/{patientId}',
        summary: 'Vincula paciente ao nutricionista',
        tags: ['Links'],
        security: [ ['bearerAuth' => []] ],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 201, description: 'Vínculo criado', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Dados inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]

    public function attach($nutritionistId, $patientId)
    {
        if (! auth_is('nutricionista','master')) {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }
        $me = auth_user();
        if ($me['role'] === 'nutricionista' && $me['id'] !== (int)$nutritionistId) {
            return json_error('Você só pode vincular seus próprios pacientes.', 'links.forbidden', 403);
        }

        $users = new UserModel();
        $nutri = $users->find($nutritionistId);
        $paci  = $users->find($patientId);

        if (! $nutri || $nutri['role'] !== 'nutricionista') return json_error('Nutricionista inválido.', 'links.bad_nutri', 422);
        if (! $paci  || $paci['role']  !== 'paciente')      return json_error('Paciente inválido.', 'links.bad_patient', 422);

        $link = new NutritionistPatientModel();
        // evita duplicar
        $exists = $link->where(['nutritionist_id'=>$nutritionistId,'patient_id'=>$patientId])->first();
        if ($exists) return json_ok(['message' => 'Já estava vinculado.']);

        $link->insert([
            'nutritionist_id' => (int)$nutritionistId,
            'patient_id'      => (int)$patientId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        return json_ok(['message' => 'Vínculo criado.'], 201);
    }

    #[OA\Delete(
        path: '/nutritionists/{id}/patients/{patientId}',
        summary: 'Remove vínculo paciente ↔ nutricionista',
        tags: ['Links'],
        security: [ ['bearerAuth' => []] ],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vínculo removido', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Acesso negado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    
    public function detach($nutritionistId, $patientId)
    {
        if (! auth_is('nutricionista','master')) {
            return json_error('Acesso negado.', 'auth.forbidden', 403);
        }
        $me = auth_user();
        if ($me['role'] === 'nutricionista' && $me['id'] !== (int)$nutritionistId) {
            return json_error('Você só pode desvincular seus próprios pacientes.', 'links.forbidden', 403);
        }

        $link = new NutritionistPatientModel();
        $link->where(['nutritionist_id'=>$nutritionistId,'patient_id'=>$patientId])->delete();
        return json_ok(['message' => 'Vínculo removido.']);
    }
}
