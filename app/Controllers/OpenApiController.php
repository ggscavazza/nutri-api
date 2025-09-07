<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use OpenApi\Generator;

class OpenApiController extends Controller
{
    // Gera e entrega o JSON em tempo real (dev) ou sob flag em prod
    public function json()
    {
        $enabled = env('app.docsEnabled', 'true'); // desligue em prod se quiser
        if ($enabled !== 'true') {
            return service('response')->setStatusCode(404);
        }
        // Scaneia apenas diretórios relevantes
        $openapi = Generator::scan([APPPATH . 'Controllers', APPPATH . 'Docs']);
        return $this->response->setHeader('Content-Type', 'application/json')
                              ->setBody($openapi->toJson());
    }

    // Swagger UI simples via CDN
    public function ui()
    {
        $specUrl = rtrim((string)env('app.baseURL'), '/') . '/openapi.json';
        $html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Nutri – API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css"/>
  <style>body{margin:0} #swagger-ui{max-width:100%}</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
  window.ui = SwaggerUIBundle({
    url: '$specUrl',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
    layout: "BaseLayout"
  });
</script>
</body>
</html>
HTML;
        return $this->response->setHeader('Content-Type', 'text/html')->setBody($html);
    }
}
