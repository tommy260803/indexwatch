<?php

$base = 'http://127.0.0.1:8080';
$opts = ['http' => ['ignore_errors' => true, 'follow_location' => false]];
$ctx = stream_context_create($opts);

function check($method, $url, $ctx, $label) {
    if ($method === 'POST') {
        $data = stream_context_get_options($ctx)['http']['content'] ?? '';
        $r = @file_get_contents($url, false, $ctx);
    } else {
        $r = @file_get_contents($url, false, $ctx);
    }
    $code = isset($http_response_header) ? explode(' ', $http_response_header[0])[1] : '???';
    echo sprintf("%-40s => HTTP %s\n", $label, $code);
    return $code;
}

echo "\n=== INDEXWATCH ENDPOINT TEST ===\n\n";

check('GET', $base . '/up', $ctx, '1. Health Check (/up)');
check('GET', $base . '/', $ctx, '2. Home (/)');
check('GET', $base . '/login', $ctx, '3. Login page (/login)');
check('GET', $base . '/api/dashboard/data', $ctx, '4. API Dashboard (no auth)');

check('GET', $base . '/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test&hub.challenge=123', $ctx, '5. Webhook verify');

$postCtx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => 'Content-Type: application/json',
    'content' => json_encode(['entry' => [['changes' => [['value' => []]]]]]),
    'ignore_errors' => true,
]]);
check('POST', $base . '/api/webhook/whatsapp', $postCtx, '6. Webhook POST (empty payload)');

echo "\n=== TESTS COMPLETADOS ===\n";
echo "\nAbre tu navegador en: http://127.0.0.1:8080\n";
echo "Login: test@example.com / admin123\n";
echo "\nPáginas a probar:\n";
echo "  - /dashboard      (KPIs + alertas + índices)\n";
echo "  - /servers        (lista de servidores)\n";
echo "  - /audit          (registros de auditoría)\n";
echo "  - /reports        (solicitar/descargar reportes)\n";
echo "  - /actions        (centro de operaciones)\n";
echo "  - /settings       (configuración)\n";