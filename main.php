<?php
declare(strict_types=1);

$H='127.0.0.1'; $P=8081; $R=__DIR__.'/htdocs'; // host, puerto, docroot

if(!function_exists('socket_create')){fwrite(STDERR,"Activa la extensión sockets en PHP\n");exit(1);}
$s=socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
socket_set_option($s,SOL_SOCKET,SO_REUSEADDR,1);
if(!@socket_bind($s,$H,$P) || !@socket_listen($s,16)){fwrite(STDERR,"bind/listen falló\n");exit(1);}
echo "HTTP en http://$H:$P  docroot=$R\n";

while($c=@socket_accept($s)){
  $buf=''; while(true){ $x=@socket_read($c,1024,PHP_BINARY_READ); if($x===false||$x==='')break;
    $buf.=$x; if(strpos($buf,"\r\n\r\n")!==false||strpos($buf,"\n\n")!==false)break; }
  $ln=preg_split("/\r\n|\n|\r/",$buf)[0]??'';
  if(!preg_match('#^GET\s+(\S+)#',$ln,$m)){ socket_write($c,"HTTP/1.0 403 Forbidden\r\n\r\n"); socket_close($c); continue; }

  $path=urldecode(parse_url($m[1],PHP_URL_PATH)??'/'); if($path==='')$path='/';
  $root=realpath($R)?:$R; $t=$root.$path; if(is_dir($t)) $t=rtrim($t,'/\\').DIRECTORY_SEPARATOR.'index.html';
  $f=realpath($t);
  if($f===false || strpos($f,$root)!==0 || !is_file($f)){ socket_write($c,"HTTP/1.0 403 Forbidden\r\n\r\n"); socket_close($c); continue; }

  $body=file_get_contents($f);
  $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
  $mime=['html'=>'text/html; charset=UTF-8','htm'=>'text/html; charset=UTF-8','txt'=>'text/plain; charset=UTF-8','css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif'][$ext]??'application/octet-stream';

  $hdr="HTTP/1.0 200 OK\r\nContent-Type: $mime\r\nContent-Length: ".strlen($body)."\r\n\r\n";
  socket_write($c,$hdr); socket_write($c,$body); socket_close($c);
}
