<?php
declare(strict_types=1);
$app=require __DIR__.'/app.php'; date_default_timezone_set($app['timezone']);
if(PHP_SAPI!=='cli'&&session_status()!==PHP_SESSION_ACTIVE){session_name('CAI2026SESSID');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']);session_start();}
require_once __DIR__.'/database.php'; require_once dirname(__DIR__).'/controllers/helpers.php';
