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
        $max = isset($arguments[0]) ? (int)$arguments[0] : 120;  // requisições
        $per = isset($arguments[1]) ? (int)$arguments[1] : 60;   // segundos

        $throttler = Services::throttler();

        $ip    = $request->getIPAddress() ?: 'unknown';
        $path  = $request->uri->getPath() ?: '/';
        $key   = sprintf('rate:%s:%s', $ip, $path); // por IP+rota (granular)

        if ($throttler->check($key, $max, $per) === false) {
            // tempo até novo token (aprox.)
            $retry = $throttler->getTokentime();
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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
