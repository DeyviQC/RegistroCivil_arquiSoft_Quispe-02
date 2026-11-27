<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API REST · Registro Civil — Swagger UI</title>
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <style>body { margin: 0; } .topbar { display: none; }</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
<script>
    // Swagger UI reads the OpenAPI contract and lets you try every endpoint ("Try it out").
    window.ui = SwaggerUIBundle({
        url: @json(route('api.documentacion.contrato')),
        dom_id: '#swagger-ui',
        deepLinking: true,
        docExpansion: 'list',
        persistAuthorization: false,
        tryItOutEnabled: false,
    });
</script>
</body>
</html>
