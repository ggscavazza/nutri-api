<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class Cors implements FilterInterface
{
    private array $allowedOrigins = [
        'https://julianafagiani.com.br',
        'https://www.julianafagiani.com.br',
        'https://api.julianafagiani.com.br',
    ];
    
    private function isAllowedOrigin(?string $origin): bool
    {
        if (!$origin) return false;
        if (in_array($origin, $this->allowedOrigins, true)) return true;
        if ($this->isLocalhost($origin)) return true;
        return false;
    }

    /** Headers base para CORS */
    private function corsHeaders(string $origin): array
    {
        return [
            'Access-Control-Allow-Origin'      => $origin,      // refletir a origem para permitir credenciais
            'Vary'                             => 'Origin',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, Origin, Accept',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Expose-Headers'    => 'Content-Disposition',
        ];
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        if (!$this->isAllowedOrigin($origin)) {
            return;
        }

        $response = Services::response();
        foreach ($this->corsHeaders($origin) as $key => $value) {
            $response->setHeader($key, $value);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        if (!$this->isAllowedOrigin($origin)) {
            return $response;
        }

        foreach ($this->corsHeaders($origin) as $key => $value) {
            if (!$response->hasHeader($key)) {
                $response->setHeader($key, $value);
            }
        }

        return $response;
    }
}
