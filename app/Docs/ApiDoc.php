<?php
namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Nutri – API',
    description: 'API para gestão de usuários (master, nutricionista, paciente) e upload/entrega de documentos (ebooks).'
)]
#[OA\Server(
    url: 'https://api.julianafagiani.com.br/',
    description: 'Produção'
)]
#[OA\Server(
    url: 'http://localhost:8080/',
    description: 'Desenvolvimento local'
)]
// JWT Bearer global
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
// Tags
#[OA\Tag(name: 'Auth', description: 'Autenticação e recuperação de senha')]
#[OA\Tag(name: 'Users', description: 'Gestão de usuários')]
#[OA\Tag(name: 'Links', description: 'Vínculos nutricionista ↔ paciente')]
#[OA\Tag(name: 'Documents', description: 'Upload, gestão e download de documentos')]
class ApiDoc {}
