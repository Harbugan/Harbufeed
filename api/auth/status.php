<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_session();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$connected = !empty($_SESSION['oauth']['access_token']);
hf_json(['connected'=>$connected,'csrf'=>$_SESSION['csrf']]);
