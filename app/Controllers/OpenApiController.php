<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class OpenApiController extends Controller
{
    /**
     * Página do Swagger UI
     */
    public function ui(): ResponseInterface
    {
        // opcional: desligar docs em produção via .env -> app.docsEnabled = false
        if (! (bool) env('app.docsEnabled', true)) {
            return $this->response->setStatusCode(404);
        }

        $html = <<<'HTML'
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8"/>
    <title>API Docs — Nutri Juliana</title>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"/>
    <style>body{margin:0}.topbar{display:none}</style>
  </head>
  <body>
    <div id="swagger"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
      window.ui = SwaggerUIBundle({
        url: "/openapi.json",
        dom_id: "#swagger",
        deepLinking: true,
        filter: true,
        presets: [SwaggerUIBundle.presets.apis],
        layout: "BaseLayout"
      });
    </script>
  </body>
</html>
HTML;

        return $this->response->setContentType('text/html')->setBody($html);
    }

    /**
     * Serve o openapi.json (estático ou gerado on-the-fly)
     */
    public function spec(): ResponseInterface
    {
        if (! (bool) env('app.docsEnabled', true)) {
            return $this->response->setStatusCode(404);
        }

        $file = FCPATH . 'openapi.json';
        if (is_file($file)) {
            return $this->response
                ->setHeader('Cache-Control', 'public, max-age=300')
                ->setContentType('application/json')
                ->setBody(file_get_contents($file));
        }

        // fallback: gerar on-the-fly se swagger-php estiver disponível
        try {
            if (function_exists('\OpenApi\scan')) {
                $openapi = \OpenApi\scan([APPPATH]);
                return $this->response->setContentType('application/json')->setBody($openapi->toJson());
            }
            if (class_exists(\OpenApi\Generator::class)) {
                $openapi = \OpenApi\Generator::scan([APPPATH]);
                return $this->response->setContentType('application/json')->setBody($openapi->toJson());
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => ['message' => 'Falha ao gerar OpenAPI', 'detail' => $e->getMessage()]
            ]);
        }

        return $this->response->setStatusCode(404)->setJSON([
            'error' => ['message' => 'openapi.json não encontrado e swagger-php indisponível']
        ]);
    }
}
