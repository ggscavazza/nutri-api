<?php
namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'message', type: 'string')
        ])
    ]
)]

#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Fulana'),
        new OA\Property(property: 'email', type: 'string', example: 'fulana@dominio.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['master','nutricionista','paciente']),
        new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo']),
        new OA\Property(property: 'photo', type: 'string', nullable: true)
    ]
)]

#[OA\Schema(
    schema: 'LoginRequest',
    type: 'object',
    required: ['email','password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', example: 'no-reply@julianafagiani.com.br'),
        new OA\Property(property: 'password', type: 'string', example: 'senha123')
    ]
)]

#[OA\Schema(
    schema: 'LoginResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'access_token', type: 'string'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 900),
        new OA\Property(property: 'refresh_token', type: 'string'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User')
    ]
)]

#[OA\Schema(
    schema: 'RefreshRequest',
    type: 'object',
    required: ['refresh_token'],
    properties: [
        new OA\Property(property: 'refresh_token', type: 'string')
    ]
)]

#[OA\Schema(
    schema: 'Document',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 10),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'file_type', type: 'string', enum: ['pdf','epub','docx']),
        new OA\Property(property: 'scope', type: 'string', enum: ['geral','paciente']),
        new OA\Property(property: 'status', type: 'string', enum: ['ativo','inativo']),
        new OA\Property(property: 'size_bytes', type: 'integer'),
        new OA\Property(property: 'uploaded_by', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string'),
        new OA\Property(property: 'download_url', type: 'string')
    ]
)]

#[OA\Schema(
    schema: 'MessageResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'OK')
    ]
)]

class Schemas {}
