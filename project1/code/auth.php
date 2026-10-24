<?php
function require_token(): void {
    $token = getenv('API_TOKEN');
    if (!$token) {
        respond(500, ['error' => 'API_TOKEN is not configured']);
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)
        || !hash_equals($token, $match[1])) {
        header('WWW-Authenticate: Bearer');
        respond(401, ['error' => 'A valid Bearer token is required']);
    }
}
