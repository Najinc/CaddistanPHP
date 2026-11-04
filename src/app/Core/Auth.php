<?php

namespace App\Core;

use App\Auth\Authenticator;
use App\Auth\Authorization\Gate;
use App\Auth\Bridge\OwnedRow;
use App\Auth\Bridge\RepositoryUserProvider;
use App\Auth\Bridge\UserRow;
use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\PasswordHasher;

/**
 * Utilisateur connecté et droits d'accès : point d'entrée de la Partie 3 pour le reste du site.
 *
 * Même API statique qu'avant (Auth::user(), Auth::check()…), mais toute la logique est dans app/Auth/ :
 * cette classe ne fait qu'assembler Authenticator, Gate, CsrfTokenManager et PasswordHasher.
 */
final class Auth
{
    private static ?Authenticator $authenticator = null;
    private static ?Gate $gate = null;
    private static ?CsrfTokenManager $csrf = null;
    private static ?PasswordHasher $hasher = null;

    /** Argon2id + même temps de réponse que l'e-mail existe ou non + nouvel ID de session si succès. */
    public static function attempt(string $email, #[\SensitiveParameter] string $password): bool
    {
        return self::authenticator()->attempt($email, $password);
    }

    /** Connecte un utilisateur déjà vérifié (ex : juste après l'inscription). */
    public static function login(array $user): void
    {
        self::authenticator()->login(new UserRow($user));
    }

    /** Destruction complète de la session (données, fichier serveur, cookie). */
    public static function logout(): void
    {
        self::authenticator()->logout();
    }

    /** Nouvel ID de session et nouveau token CSRF, par exemple après un changement de mot de passe. */
    public static function renewSession(): void
    {
        $user = self::authenticator()->user();

        if ($user !== null) {
            self::authenticator()->login($user);
        }
    }

    /** Utilisateur connecté, SANS le hash du mot de passe : il n'a rien à faire dans les vues. */
    public static function user(): ?array
    {
        $user = self::authenticator()->user();

        if (!$user instanceof UserRow) {
            return null;
        }

        $row = $user->row;
        unset($row['password']);

        return $row;
    }

    public static function id(): ?int
    {
        return self::authenticator()->user()?->getAuthId();
    }

    public static function check(): bool
    {
        return self::authenticator()->check();
    }

    /** Pour adapter l'affichage (ex : lien « Admin »). Ne protège rien : la vérification se fait avec authorize(). */
    public static function can(string $permission): bool
    {
        return self::gate()->can(self::authenticator()->user(), $permission);
    }

    /** 401 si personne n'est connecté, 403 s'il manque la permission (voir app/Auth/permissions.php). */
    public static function authorize(string $permission): void
    {
        self::gate()->authorize(self::authenticator()->user(), $permission);
    }

    /** 404 si la ligne (commande…) n'appartient pas à l'utilisateur connecté (colonne user_id). */
    public static function authorizeOwner(array $row): void
    {
        self::gate()->authorizeOwnership(self::authenticator()->user(), new OwnedRow($row));
    }

    public static function hashPassword(#[\SensitiveParameter] string $password): string
    {
        return self::hasher()->hash($password);
    }

    /** Vérifie le mot de passe de l'utilisateur connecté (ex : « mot de passe actuel »). */
    public static function checkPassword(#[\SensitiveParameter] string $password): bool
    {
        $user = self::authenticator()->user();

        return $user !== null && self::hasher()->verify($password, $user->getAuthPasswordHash());
    }

    public static function csrf(): CsrfTokenManager
    {
        return self::$csrf ??= new CsrfTokenManager(Session::native());
    }

    private static function authenticator(): Authenticator
    {
        return self::$authenticator ??= new Authenticator(
            Session::native(),
            new RepositoryUserProvider(),
            self::hasher(),
            self::csrf(),
        );
    }

    private static function gate(): Gate
    {
        return self::$gate ??= new Gate(require __DIR__ . '/../Auth/permissions.php');
    }

    private static function hasher(): PasswordHasher
    {
        return self::$hasher ??= new PasswordHasher();
    }
}
