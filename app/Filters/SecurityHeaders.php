<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $headers = [
            'X-Content-Type-Options'           => 'nosniff',
            'X-Frame-Options'                  => 'DENY',
            'Referrer-Policy'                  => 'no-referrer',
            'Permissions-Policy'               => "geolocation=(), microphone=(), camera=(), usb=(), payment=()",
            'X-Download-Options'               => 'noopen',
            'X-Permitted-Cross-Domain-Policies'=> 'none',
            // Para APIs puras, manter cache prudente (ajuste conforme endpoints):
            // 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ];
        foreach ($headers as $k => $v) {
            if (! $response->hasHeader($k)) {
                $response->setHeader($k, $v);
            }
        }
        return $response;
    }
}
