<?php
namespace PassGo;

/** CSRF protection for every POST form. */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Hidden input to place inside every POST form. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    public static function valid(?string $sent): bool
    {
        return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
    }

    /** Call at the top of every POST handler. */
    public static function check(): void
    {
        $sent = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null;
        if (!self::valid($sent)) {
            abort(400, 'Your session expired or the request was not valid. Please go back and try again.');
        }
    }
}
