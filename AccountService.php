<?php
namespace PassGo\Service;

use PassGo\Repository\UserRepository;
use PassGo\Validator;
use PDO;

/** Registration, login check and profile updates. */
class AccountService
{
    // Used to spend the same time on unknown e-mails as on wrong passwords.
    const DUMMY_HASH = '$2y$10$CO0NmN6dXE7F1Rszmhc1tOWD4ACrIqANcOrwTzFv2SzYoQKiiOYjK';

    private $users;

    public function __construct(PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
    }

    /** @return array ['errors' => [field => message], 'user_id' => ?int] */
    public function register(string $name, string $email, string $password, string $confirm): array
    {
        $name = trim($name);
        $email = trim($email);
        $errors = Validator::registration($name, $email, $password, $confirm);
        if (!isset($errors['email']) && $this->users->findByEmail($email)) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        }
        if ($errors) {
            return ['errors' => $errors, 'user_id' => null];
        }
        try {
            $id = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (\PDOException $e) {
            if ($e->getCode() === '23505') { // two people registering the same e-mail at once
                return ['errors' => ['email' => 'An account with this email already exists. Try logging in instead.'], 'user_id' => null];
            }
            throw $e;
        }
        return ['errors' => [], 'user_id' => $id];
    }

    /** Returns the user row when e-mail and password match, otherwise null (never says which one was wrong). */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail(trim($email));
        $hash = $user ? $user['password_hash'] : self::DUMMY_HASH;
        $ok = password_verify($password, $hash);
        return ($user && $ok) ? $user : null;
    }

    /** @return string|null error message, null on success */
    public function updateName(int $userId, string $name): ?string
    {
        $error = Validator::nameError($name);
        if ($error !== null) {
            return $error;
        }
        $this->users->updateName($userId, trim($name));
        return null;
    }
}
