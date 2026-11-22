<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Flash;

// Logging out changes state, so it only works through a POST form with a CSRF token.
if (is_post()) {
    Csrf::check();
    Auth::logout();
    Flash::add('success', 'You were logged out.');
}
redirect('index.php');
