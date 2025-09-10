<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RateLimit implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // ignora preflight
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return;
        }

        $max = isset($arguments[0]) ? (int)$arguments[0] : 120;  // requisições
        $per = isset($arguments[1]) ? (int)$arguments[1] : 60;   // segundos

        $throttler = Services::throttler();

        $ip    = $request->getIPAddress() ?: 'unknown';
        $uri   = $request->getURI();
        $path  = $uri ? trim($uri->getPath(), '/') : '/';

        // evita chaves muito longas/estranhas em backends de cache
        $key  = 'rate:' . md5($ip . ':' . $path);

        if ($throttler->check($key, $max, $per) === false) {
            // tempo até novo token (aprox.)
            $retry = (int) ceil($throttler->getTokenTime());
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retry)
                ->setJSON([
                    'error' => [
                        'code'    => 'rate.limited',
                        'message' => 'Muitas requisições. Tente novamente em ' . $retry . 's.'
                    ]
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $max  = isset($arguments[0]) ? (int) $arguments[0] : 120;
        $per = isset($arguments[1]) ? (int) $arguments[1] : 60;
        $response->setHeader('X-RateLimit-Limit', (string) $max)
                 ->setHeader('X-RateLimit-Window', (string) $per);
    }
}
