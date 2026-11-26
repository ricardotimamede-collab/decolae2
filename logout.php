<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametros_cookie = session_get_cookie_params();
    
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros_cookie['path'],
        $parametros_cookie['domain'],
        $parametros_cookie['secure'],
        $parametros_cookie['httponly']
    );
}

session_destroy();

header('Location: login-cliente.php?logout=1');

exit;
