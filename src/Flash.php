<?php
namespace PassGo;

/** One-time messages shown after a redirect. Types: success, error, info. */
class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Returns and clears all pending messages. */
    public static function consume(): array
    {
        $all = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
        unset($_SESSION['flash']);
        return $all;
    }
}
