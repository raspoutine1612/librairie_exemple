<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Schémas OpenAPI réutilisables
 * 
 * Définis une fois et utilisés dans tous les endpoints
 * pour éviter les doublons et les erreurs.
 */

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER'))
    ]
)]
class UserSchema {}

#[OA\Schema(
    schema: 'LoginRequest',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', example: 'password123')
    ],
    required: ['uuid', 'password']
)]
class LoginRequestSchema {}

#[OA\Schema(
    schema: 'RegisterRequest',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', example: 'password123'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER'))
    ],
    required: ['uuid', 'password']
)]
class RegisterRequestSchema {}

#[OA\Schema(
    schema: 'TokenResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie'),
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
        new OA\Property(property: 'expiresIn', type: 'integer', example: 3600)
    ]
)]
class TokenResponseSchema {}

#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Error message')
    ]
)]
class ErrorResponseSchema {}
