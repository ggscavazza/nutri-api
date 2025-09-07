<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DeployController extends Controller
{
    public function request()
    {
        if ($this->request->getMethod() !== 'post') {
            return json_error('Method not allowed', 'http.method_not_allowed', 405);
        }

        $expected = (string) env('app.deployToken');
        $provided = $this->request->getHeaderLine('X-Deploy-Token');

        if (! $expected || ! hash_equals($expected, $provided)) {
            return json_error('Forbidden', 'deploy.forbidden', 403);
        }

        // grava o gatilho no WRITEPATH (=> public_html/writable)
        $dir = WRITEPATH . 'deploy';
        if (! is_dir($dir)) @mkdir($dir, 0775, true);

        $payload = $this->request->getJSON(true) ?? [];
        $sha = substr((string)($payload['sha'] ?? ''), 0, 40);

        file_put_contents($dir . '/trigger.json', json_encode([
            'time' => date('c'),
            'sha'  => $sha,
            'ip'   => $this->request->getIPAddress(),
        ], JSON_PRETTY_PRINT));

        return json_ok(['message' => 'Deploy solicitado', 'sha' => $sha]);
    }
}
