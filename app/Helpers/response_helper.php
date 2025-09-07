<?php

use CodeIgniter\HTTP\ResponseInterface;

if (! function_exists('json_ok')) {
    function json_ok(array $data = [], int $status = 200)
    {
        return service('response')->setStatusCode($status)->setJSON($data);
    }
}

if (! function_exists('json_error')) {
    function json_error(string $message, string $code = 'error', int $status = 400, array $extra = [])
    {
        return service('response')->setStatusCode($status)->setJSON([
            'error' => array_merge(['code' => $code, 'message' => $message], $extra),
        ]);
    }
}
