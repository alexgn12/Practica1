<?php
function run_http_server(string $host, int $port, string $docroot): void
{
    $docrootReal = realpath($docroot);
    if ($docrootReal === false) {
        die("Docroot no existe: $docroot\n");
    }

    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create\n");
    @socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
    @socket_bind($sock, $host, $port) or die("socket_bind\n");
    @socket_listen($sock, 16) or die("socket_listen\n");

    echo "Servidor:  http://$host:$port\nDocroot:   $docrootReal\n\n";

    while (true) {
        $client = @socket_accept($sock);
        if ($client === false) { continue; }

        // 1) Leer petición hasta línea en blanco
        $req = read_until_headers_end($client);
        if ($req === '') { socket_close($client); continue; }

        // 2) Primera línea: método, ruta, versión
        $lines = preg_split("/\r\n|\n|\r/", $req);
        $requestLine = trim($lines[0] ?? '');
        if (!preg_match('#^(GET)\s+(\S+)\s+HTTP/1\.[01]$#', $requestLine, $m)) {
            send_text($client, 400, "Bad Request");
            socket_close($client);
            continue;
        }
        $method = $m[1];
        $target = $m[2];

        if ($method !== 'GET') {
            send_text($client, 405, "Method Not Allowed", ["Allow: GET"]);
            socket_close($client);
            continue;
        }

        // 3) Normalizar path
        $path = urldecode(parse_url($target, PHP_URL_PATH) ?? '/');
        if ($path === '' || $path[0] !== '/') { $path = '/'; } // exigimos ruta absoluta

        // 4) Construir ruta física segura dentro del docroot
        $full = realpath($docrootReal . $path);
        if ($full === false) {
            // ¿era un directorio sin realpath todavía? probar index.html
            $maybe = $docrootReal . rtrim($path, '/') . '/index.html';
            $full = realpath($maybe);
        }

        // Debe existir, ser archivo, y estar dentro del docroot
        if ($full === false || !is_file($full) || strpos($full, $docrootReal) !== 0) {
            send_text($client, 404, "Not Found");
            socket_close($client);
            continue;
        }

        // 5) Preparar y enviar respuesta 200 con el archivo
        $size = filesize($full) ?: 0;
        $mime = mime_basic($full);

        $hdr = "HTTP/1.0 200 OK\r\n"
             . "Content-Type: $mime\r\n"
             . "Content-Length: $size\r\n"
             . "Connection: close\r\n"
             . "\r\n";
        @socket_write($client, $hdr);

        $fp = fopen($full, 'rb');
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === '' || $chunk === false) break;
            @socket_write($client, $chunk);
        }
        fclose($fp);

        socket_close($client);
    }
}

// === Helpers muy simples ===

function read_until_headers_end($client): string
{
    $buf = '';
    while (true) {
        $x = @socket_read($client, 1024, PHP_BINARY_READ);
        if ($x === '' || $x === false) break;
        $buf .= $x;
        if (strpos($buf, "\r\n\r\n") !== false || strpos($buf, "\n\n") !== false) break;
    }
    return $buf;
}

function send_text($client, int $code, string $reason, array $extraHeaders = []): void
{
    $body = "$code $reason\n";
    $hdr  = "HTTP/1.0 $code $reason\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Length: " . strlen($body) . "\r\n";
    foreach ($extraHeaders as $h) { $hdr .= $h . "\r\n"; }
    $hdr .= "Connection: close\r\n\r\n";
    @socket_write($client, $hdr . $body);
}

function mime_basic(string $file): string
{
    static $map = [
        'html' => 'text/html; charset=UTF-8',
        'htm'  => 'text/html; charset=UTF-8',
        'txt'  => 'text/plain; charset=UTF-8',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return $map[$ext] ?? 'application/octet-stream';
}