<?php
// Loaded by every page: autoloader, helpers, error handling, session.

define('PASSGO_ROOT', dirname(__DIR__));

spl_autoload_register(function ($class) {
    $prefix = 'PassGo\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';
require PASSGO_ROOT . '/templates/icons.php';
require PASSGO_ROOT . '/templates/components.php';
require PASSGO_ROOT . '/templates/layout.php';

date_default_timezone_set((string) config('app.timezone', 'Europe/Lisbon'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli') {
    // Friendly error page; technical details only go to the error log.
    set_exception_handler(function ($e) {
        error_log('PASS GO error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        abort(500, 'Something went wrong on our side. Please try again in a moment.');
    });

    session_name('PASSGOSESSID'); // own name so it never clashes with other apps on the same server
    // Behind a hosting proxy (Render, etc.) HTTPS is reported in X-Forwarded-Proto.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path(),
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
