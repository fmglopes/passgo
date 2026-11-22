<?php
namespace PassGo;

/** Input validation. Each method returns error messages keyed by field name. */
class Validator
{
    public static function validEmail(string $email): bool
    {
        return strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function nameError(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Please enter your name.';
        }
        if (mb_strlen($name) > 100) {
            return 'Your name must have at most 100 characters.';
        }
        return null;
    }

    public static function passwordError(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Your password must have at least 8 characters.';
        }
        if (strlen($password) > 72) {
            return 'Your password must have at most 72 characters.';
        }
        return null;
    }

    public static function registration(string $name, string $email, string $password, string $confirm): array
    {
        $errors = [];
        if (($e = self::nameError($name)) !== null) {
            $errors['name'] = $e;
        }
        if (!self::validEmail(trim($email))) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (($e = self::passwordError($password)) !== null) {
            $errors['password'] = $e;
        } elseif ($password !== $confirm) {
            $errors['confirm'] = 'The two passwords do not match.';
        }
        return $errors;
    }
}
