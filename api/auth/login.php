<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_require_method('GET');
hf_session();
try {
    $cfg = hf_config();
    $state = bin2hex(random_bytes(32));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['oauth_pending'] = ['state'=>$state,'verifier'=>$verifier,'expires'=>time()+600];
    $params = [
        'client_id'=>$cfg['client_id'],'redirect_uri'=>$cfg['redirect_uri'],
        'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/youtube.readonly',
        'state'=>$state,'access_type'=>'offline','prompt'=>'consent',
        'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)), '+/','-_'),'='),
        'code_challenge_method'=>'S256'
    ];
    header('Cache-Control: no-store');
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params), true, 302);
} catch (Throwable $e) {
    error_log('Harbufeed login: '.$e->getMessage());
    hf_error(503, 'Google login is not configured');
}
