<?php
require __DIR__ . '/src/http_client.php';
require __DIR__ . '/src/http_server.php';

$server_host = "127.0.0.1";
$server_port = 8080;
$document_root = getcwd() . "/htdocs";

$argv1 = $argv[1] ?? '';
if ($argv1 === '--server') {
    run_http_server($server_host, $server_port, $document_root);
    exit;
}

if ($argv1 === '' || $argv1 === '-h' || $argv1 === '--help') {
    fwrite(STDERR, "Uso:\n  php main.php --server\n  php main.php <URL> [fichero_salida]\n");
    exit(1);
}

$out = $argv[2] ?? null;
http_download($argv1, $out);
echo "Descarga completada.\n";