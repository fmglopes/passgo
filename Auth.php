<?php
namespace PassGo;

use PassGo\Repository\UserRepository;

/** Session-based authentication. */
class Auth
{
    private static $user = false; // false = not loaded yet

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Current user row, or null for visitors. */
    public static function user(): ?array
    {
        if (self::$user === false) {
            $id = self::id();
            self::$user = $id ? (new UserRepository(Database::pdo()))->find($id) : null;
            if ($id && !self::$user) {
                unset($_SESSION['user_id']); // account no longer exists
            }
        }
        return self::$user;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true); // prevents session fixation
        $_SESSION['user_id'] = $userId;
        self::$user = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = false;
    }

    /** Pages that need a logged-in user call this first. */
    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Flash::add('info', 'Please log in to continue.');
            redirect('login.php?next=' . rawurlencode(current_relative_uri()));
        }
        return $user;
    }
}
