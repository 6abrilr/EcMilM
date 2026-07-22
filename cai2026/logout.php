<?php
require_once __DIR__.'/config/bootstrap.php';if(user())audit('logout','usuarios',(int)user()['id']);$_SESSION=[];session_destroy();header('Location: '.base_url('login.php'));

