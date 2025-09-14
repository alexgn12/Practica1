<?php
declare(strict_types=1);

require_once __DIR__ . '/src/http_client.php';
require_once __DIR__ . '/src/http_server.php';

// Defaults (si no hay config_local.php)
$server_host = "127.0.0.1";
$server_port = 8080;
$document_root = getcwd() . "/htdocs";

// Cargar config_local.php si existe (como exige la práctica)
$config = __DIR__ . '/config_local.php';
if (file_exists($config)) {
    /** @noinspection PhpIncludeInspection */
    require_once $config; // Debe definir $server_host, $server_port, $document_root
}

$argv1 = $argv[1] ?? '';

if ($argv1 === '--server') {
    if (!function_exists('socket_create')) {
        fwrite(STDERR, "ERROR: habilita la extensión 'sockets' en PHP\n");
        exit(1);
    }
    echo "Servidor en http://{$server_host}:{$server_port}  docroot={$document_root}\n";
    run_http_server($server_host, (int)$server_port, $document_root);
    exit;
}

if ($argv1 === '' || $argv1 === '-h' || $argv1 === '--help') {
    fwrite(STDERR, "Uso:\n  php main.php --server\n  php main.php <URL> [fichero_salida]\n");
    exit(1);
}

// Modo cliente
$out = $argv[2] ?? null;
http_download($argv1, $out);
echo "Descarga completada.\n";
