<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_session();
header('Cache-Control: no-store');
$pending = $_SESSION['oauth_pending'] ?? null;
unset($_SESSION['oauth_pending']);
$state = $_GET['state'] ?? '';
if (!is_array($pending) || !is_string($state) || !hash_equals($pending['state'], $state)
    || $pending['expires'] < time()) hf_error(400, 'Invalid or expired OAuth state');
if (isset($_GET['error'])) { header('Location: ../../index.html?auth=cancelled',true,303); exit; }
$code = $_GET['code'] ?? '';
if (!is_string($code) || $code === '') hf_error(400, 'Missing authorization code');
try {
    $cfg = hf_config();
    $tokens = hf_token_request([
        'code'=>$code,'client_id'=>$cfg['client_id'],
        'client_secret'=>$cfg['client_secret'],'redirect_uri'=>$cfg['redirect_uri'],
        'code_verifier'=>$pending['verifier'],'grant_type'=>'authorization_code'
    ]);
    if (empty($tokens['access_token'])) throw new RuntimeException('Missing access token');
    session_regenerate_id(true);
    $_SESSION['oauth'] = [
        'access_token'=>$tokens['access_token'],
        'refresh_token'=>$tokens['refresh_token'] ?? null,
        'expires_at'=>time()+(int)($tokens['expires_in'] ?? 3500)
    ];
    unset($_SESSION['csrf']);
    header('Location: ../../index.html?auth=connected',true,303);
} catch (Throwable $e) {
    error_log('Harbufeed callback: '.$e->getMessage());
    header('Location: ../../index.html?auth=failed',true,303);
}
