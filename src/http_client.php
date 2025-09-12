<?php 
function http_download(string $url, ?string $outFile = null): void
{
    // 1) Parsear la URL y poner valores por defecto sencillos
    $u = parse_url($url);
    if (!$u || ($u['scheme'] ?? '') !== 'http') {
        throw new RuntimeException("Solo se admite http:// en esta práctica");
    }
    $host = $u['host'] ?? '';
    $port = $u['port'] ?? 80;
    $path = ($u['path'] ?? '/') . (isset($u['query']) ? ('?' . $u['query']) : '');

    if ($host === '') {
        throw new RuntimeException("URL inválida (falta host)");
    }

    // 2) Abrir socket TCP al servidor
    $fp = @fsockopen($host, $port, $errno, $errstr, 5.0);
    if (!$fp) {
        throw new RuntimeException("No se pudo conectar a $host:$port — $errstr ($errno)");
    }

    // 3) Enviar petición HTTP/1.0 mínima
    $request  = "GET $path HTTP/1.0\r\n";
    $request .= "Host: $host\r\n";
    $request .= "Connection: close\r\n";
    $request .= "\r\n";
    fwrite($fp, $request);

    // 4) Leer cabeceras hasta la línea en blanco
    $rawHeaders = '';
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) break;
        $rawHeaders .= $line;
        if ($rawHeaders === "\r\n" || $rawHeaders === "\n") break;          // caso extremo
        if (str_ends_with($rawHeaders, "\r\n\r\n") || str_ends_with($rawHeaders, "\n\n")) break;
    }

    // 5) Separar cabeceras y cuerpo ya leído (si vino pegado)
    $sep = (strpos($rawHeaders, "\r\n\r\n") !== false) ? "\r\n\r\n" : "\n\n";
    $parts = explode($sep, $rawHeaders, 2);
    $headerStr = $parts[0] ?? '';
    $bodyStart = $parts[1] ?? '';

    // 6) Comprobar código de estado
    $headerLines = preg_split("/\r\n|\n|\r/", trim($headerStr));
    $statusLine = $headerLines[0] ?? '';
    if (!preg_match('#^HTTP/\d\.\d\s+(\d{3})#', $statusLine, $m)) {
        fclose($fp);
        throw new RuntimeException("Respuesta inválida del servidor");
    }
    $code = (int)$m[1];

    // 7) Mapa de cabeceras (solo lo que nos interesa)
    $contentLength = null;
    foreach (array_slice($headerLines, 1) as $h) {
        $pos = strpos($h, ':');
        if ($pos === false) continue;
        $k = strtolower(trim(substr($h, 0, $pos)));
        $v = trim(substr($h, $pos + 1));
        if ($k === 'content-length') {
            $contentLength = (int)$v;
        }
    }

    // 8) Decidir nombre de archivo de salida
    if ($outFile === null) {
        $base = basename(parse_url($path, PHP_URL_PATH) ?: '/');
        $outFile = ($base === '' || $base === '/') ? 'index.html' : $base;
    }

    $out = fopen($outFile, 'wb');
    if (!$out) {
        fclose($fp);
        throw new RuntimeException("No se pudo abrir $outFile para escritura");
    }

    // 9) Escribir lo ya leído del cuerpo (si llegó junto a cabeceras)
    $written = 0;
    if ($bodyStart !== '') {
        fwrite($out, $bodyStart);
        $written = strlen($bodyStart);
    }

    // 10) Leer el resto del cuerpo:
    //     - Si Content-Length: leer exactamente esos bytes
    //     - Si no: leer hasta EOF (HTTP/1.0 cierra conexión)
    if ($contentLength !== null) {
        $remaining = max(0, $contentLength - $written);
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remaining));
            if ($chunk === '' || $chunk === false) break;
            fwrite($out, $chunk);
            $remaining -= strlen($chunk);
        }
    } else {
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === '' || $chunk === false) break;
            fwrite($out, $chunk);
        }
    }

    fclose($out);
    fclose($fp);

    // 11) Si no es 200, avisamos (ya hemos guardado el cuerpo por si quieres inspeccionarlo)
    if ($code !== 200) {
        throw new RuntimeException("Servidor devolvió $code (revisa URL o permisos). Archivo guardado en $outFile");
    }
}
?>