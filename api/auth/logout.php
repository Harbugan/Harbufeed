<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_require_method('POST');
hf_session(); hf_csrf();
$_SESSION=[];
if (ini_get('session.use_cookies')) {
    $p=session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$p['path'],
        'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']]);
}
session_destroy();
hf_json(['connected'=>false]);
