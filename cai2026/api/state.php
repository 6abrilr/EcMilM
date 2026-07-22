<?php
require_once __DIR__.'/../config/bootstrap.php';require_once __DIR__.'/../models/Competition.php';
try{json_out(['ok'=>true]+Competition::state());}catch(Throwable $e){error_log($e->getMessage());json_error('No se pudo consultar el estado.',500);}

