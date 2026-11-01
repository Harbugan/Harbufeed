<?php
declare(strict_types=1);
const HF_CONFIG = '/home/lilleoer/harbufeed-private/config.php';
function hf_config(): array {
    if (!is_file(HF_CONFIG)) { throw new RuntimeException('Server configuration missing'); }
    $cfg = require HF_CONFIG;
    if (!is_array($cfg) || empty($cfg['client_id']) || empty($cfg['client_secret']) || empty($cfg['redirect_uri'])) {
        throw new RuntimeException('Server configuration incomplete');
    }
    return $cfg;
}
function hf_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('HARBUFEED_SESSION');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/Projects/Harbufeed/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}
function hf_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function hf_error(int $status, string $message): never { hf_json(['error'=>$message], $status); }
function hf_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) hf_error(405, 'Method not allowed');
}
function hf_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        hf_error(403, 'CSRF validation failed');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== 'https://lilleoere.eu') hf_error(403, 'Origin not allowed');
}
function hf_http(string $url, array $options = []): array {
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2
    ]);
    foreach ($options as $key=>$value) curl_setopt($handle, $key, $value);
    $response = curl_exec($handle);
    $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false) throw new RuntimeException('Network request failed: '.$error);
    $json = json_decode((string)$response, true);
    if (!is_array($json)) throw new RuntimeException('Unexpected provider response');
    if ($code < 200 || $code >= 300) {
        error_log('Harbufeed provider error HTTP '.$code);
        throw new RuntimeException('YouTube request failed (HTTP '.$code.')');
    }
    return $json;
}
function hf_token_request(array $params): array {
    return hf_http('https://oauth2.googleapis.com/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
}
function hf_access_token(): string {
    if (empty($_SESSION['oauth']['access_token'])) hf_error(401, 'Not connected');
    $oauth = $_SESSION['oauth'];
    if (($oauth['expires_at'] ?? 0) > time()+60) return $oauth['access_token'];
    if (empty($oauth['refresh_token'])) {
        unset($_SESSION['oauth']);
        hf_error(401, 'Google session expired. Please reconnect.');
    }
    $cfg = hf_config();
    try {
        $new = hf_token_request([
            'client_id'=>$cfg['client_id'], 'client_secret'=>$cfg['client_secret'],
            'refresh_token'=>$oauth['refresh_token'], 'grant_type'=>'refresh_token'
        ]);
    } catch (Throwable $e) {
        error_log('Harbufeed refresh failed: '.$e->getMessage());
        unset($_SESSION['oauth']);
        hf_error(401, 'Google session expired. Please reconnect.');
    }
    $_SESSION['oauth']['access_token'] = $new['access_token'];
    $_SESSION['oauth']['expires_at'] = time() + (int)($new['expires_in'] ?? 3500);
    return $_SESSION['oauth']['access_token'];
}
function hf_youtube(string $endpoint, array $params): array {
    $url = 'https://www.googleapis.com/youtube/v3/'. $endpoint . '?' . http_build_query($params);
    return hf_http($url, [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.hf_access_token()]]);
}
function hf_page_token(): string {
    $token = (string)($_GET['pageToken'] ?? '');
    if (strlen($token) > 512 || !preg_match('/^[a-zA-Z0-9_=-]*$/', $token)) hf_error(400, 'Invalid page token');
    return $token;
}
function hf_api_run(callable $fn): void {
    try { $fn(); }
    catch (Throwable $e) { error_log('Harbufeed API: '.$e->getMessage()); hf_error(502, 'YouTube is temporarily unavailable'); }
}
