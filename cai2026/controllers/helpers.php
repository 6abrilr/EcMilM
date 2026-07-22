<?php
declare(strict_types=1);
function h(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function base_url(string $p=''):string{global $app;return $app['base_url'].($p?'/'.ltrim($p,'/'):'');}
function csrf_token():string{return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function csrf_input():string{return '<input type="hidden" name="_csrf" value="'.h(csrf_token()).'">';}
function verify_csrf():void{$t=$_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');if(!is_string($t)||!hash_equals($_SESSION['csrf']??'',$t))json_error('Token CSRF inválido.',419);}
function user():?array{return isset($_SESSION['user'])&&is_array($_SESSION['user'])?$_SESSION['user']:null;}
function require_admin():void{if(!user()){header('Location: '.base_url('login.php'));exit;}if(!in_array(user()['rol'],['admin','juez'],true)){http_response_code(403);exit('Acceso denegado');}}
function json_out(array $d,int $s=200):never{http_response_code($s);header('Content-Type: application/json; charset=utf-8');echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function json_error(string $m,int $s=400):never{json_out(['ok'=>false,'error'=>$m],$s);}
function audit(string $a,?string $e=null,?int $id=null,?array $d=null):void{$q=db()->prepare('INSERT INTO historial(usuario_id,accion,entidad,entidad_id,detalle,ip)VALUES(?,?,?,?,?,?)');$q->execute([user()['id']??null,$a,$e,$id,$d?json_encode($d,JSON_UNESCAPED_UNICODE):null,substr($_SERVER['REMOTE_ADDR']??'',0,45)]);}

