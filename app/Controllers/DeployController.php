<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DeployController extends Controller
{
    public function request()
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->response->setStatusCode(405)
                ->setJSON(['error' => ['code' => 'http.method_not_allowed', 'message' => 'Method not allowed']]);
        }

        $expected = (string) env('app.deployToken');
        $provided = $this->request->getHeaderLine('X-Deploy-Token');

        if (! $expected || ! hash_equals($expected, $provided)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => ['code' => 'deploy.forbidden', 'message' => 'Forbidden']]);
        }

        $payload = $this->request->getJSON(true) ?? [];
        $sha     = substr((string)($payload['sha'] ?? ''), 0, 40);

        $dir = WRITEPATH . 'deploy';
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => ['code' => 'deploy.mkdir_failed', 'message' => 'Cannot create deploy dir']]);
        }

        $ok = @file_put_contents($dir . '/trigger.json', json_encode([
            'time' => date('c'),
            'sha'  => $sha,
            'ip'   => $this->request->getIPAddress(),
        ], JSON_PRETTY_PRINT), LOCK_EX);

        if ($ok === false) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => ['code' => 'deploy.write_failed', 'message' => 'Cannot write trigger']]);
        }

        return $this->response->setJSON(['message' => 'Deploy solicitado', 'sha' => $sha]);
    }
}
