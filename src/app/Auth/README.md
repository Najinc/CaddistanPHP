# Partie 3 : Authentification et accès (Najib)

Tout le code de la partie est dans `app/Auth/` (namespace `App\Auth`), les tests dans `tests/Auth/`.
Aucun fichier des autres parties n'est modifié. Quand la Partie 3 a besoin d'une autre partie,
elle définit une **interface** que le responsable de cette partie implémente (voir la dernière section).

## Ce qui est couvert

| Exigence | Où | Test |
|---|---|---|
| `password_hash` en Argon2id, `password_needs_rehash` | `PasswordHasher` | `PasswordHasherTest` |
| Même message **et même temps de réponse**, que le compte existe ou non | `Authenticator::attempt()` | `AuthenticatorTest` |
| `session_regenerate_id(true)` au login | `Authenticator::login()` | `AuthenticatorTest`, `NativeSessionHttpTest` |
| Destruction complète au logout (données, fichier serveur, cookie) | `NativeSession::destroy()` | `NativeSessionHttpTest` |
| Timeout d'inactivité (30 min par défaut) | `NativeSession::start()` | `NativeSessionHttpTest` |
| Cookies HttpOnly, Secure, SameSite=Lax, préfixe `__Host-` | `CookiePolicy` | `CookiePolicyTest`, `NativeSessionHttpTest` |
| `session.use_strict_mode` (refus des ID inventés) | `NativeSession` | `NativeSessionHttpTest` |
| Token CSRF par session sur POST, PUT, PATCH, DELETE, comparé avec `hash_equals` | `Csrf\CsrfTokenManager` | `CsrfTokenManagerTest` |
| Rôles → permissions, refus par défaut | `Authorization\Gate`, `permissions.php` | `GateTest` |
| Un client ne voit que ses commandes (404 sinon) | `Gate::authorizeOwnership()` | `GateTest` |
| Routes signées : HMAC (APP_KEY) sur URL + expiration, `hash_equals` | `Signing\UrlSigner` | `UrlSignerTest` |
| Bonus : magic links (15 min, usage unique) | `Signing\MagicLink` | `MagicLinkTest` |
| Redirection après login sans open redirect | `SafeRedirect` | `SafeRedirectTest` |

## Lancer les tests

```sh
# en local, depuis src/
composer dump-autoload
php tests/Auth/run.php

# ou dans le conteneur
docker compose exec app php tests/Auth/run.php
```

`NativeSessionHttpTest` lance un vrai serveur `php -S` et lit les en-têtes `Set-Cookie` réellement envoyés.

## Mise en place

```php
use App\Auth\Authenticator;
use App\Auth\Authorization\Gate;
use App\Auth\Authorization\PermissionMap;
use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\PasswordHasher;
use App\Auth\Session\CookiePolicy;
use App\Auth\Session\NativeSession;
use App\Auth\Signing\MagicLink;
use App\Auth\Signing\UrlSigner;

$session = new NativeSession(new CookiePolicy(secure: true), idleTimeout: 1800);
$csrf    = new CsrfTokenManager($session);
$auth    = new Authenticator($session, $users, new PasswordHasher(), $csrf); // $users : Partie 2
$gate    = new Gate(PermissionMap::caddistan());
$signer  = new UrlSigner($appKey);                                         // APP_KEY : Partie 1
$magic   = new MagicLink($signer, $usedTokens, $users);                    // $usedTokens : Partie 2 ou 5
```

La session se configure toute seule (`use_strict_mode`, cookies, timeout) : rien à changer dans le `php.ini`.

## Utilisation

Les exemples utilisent `$_POST` et `$_SERVER` en attendant la `Request` et le routeur de la Partie 1.

### CSRF : sur chaque requête, avant le contrôleur

```php
$csrf->verify($_SERVER['REQUEST_METHOD'], CsrfTokenManager::submittedToken($_POST, $_SERVER));
```

Dans chaque formulaire POST (sortie brute volontaire, le HTML est généré et échappé par la classe) :

```php
<form method="post" action="/panier">
    <?= $csrf->field() ?>
    ...
</form>
```

Pour `fetch()` : mettre `<?= $csrf->metaTag() ?>` dans le `<head>`, puis envoyer l'en-tête
`X-CSRF-Token` avec la valeur de `document.querySelector('meta[name="csrf-token"]').content`.

Aucune route GET ne doit modifier l'état : la déconnexion se fait en POST.

### Connexion et déconnexion

```php
// POST /connexion   ($email et $password validés en chaînes par la Partie 1)
if (!$auth->attempt($email, $password)) {
    // Réponse 422 avec Authenticator::FAILURE_MESSAGE, quelle que soit la cause.
}
// Redirection 303 vers SafeRedirect::target($_GET['redirect'] ?? null, '/compte')

// POST /deconnexion
$auth->logout(); // puis redirection 303 vers /
```

À l'inscription : `Authenticator::normalizeEmail($email)`, `$hasher->hash($password)`, rôle `client`
fixé par le serveur, puis `$auth->login($user)`.

### Autorisations

```php
// Toujours une permission, jamais un rôle en dur :
$gate->authorize($auth->user(), 'caddy.edit');            // 401 si non connecté, 403 sinon

// Contrôle de propriété : le client voit sa commande, l'admin toutes, les autres reçoivent un 404.
$gate->authorize($auth->user(), 'order.view');
$gate->authorizeOwnership($auth->user(), $order, 'order.view_any');
```

Les rôles et permissions se modifient dans `permissions.php`. Dans les vues, `$gate->can(...)` sert
à masquer un bouton, mais seule la vérification dans le contrôleur protège.

### Routes signées

```php
$url = $signer->sign("/commandes/{$id}/facture", 3600);   // lien valable 1 h
// Dans la route :
$signer->verify($_SERVER['REQUEST_URI']);                  // InvalidSignatureException (403) sinon
```

La signature porte sur le chemin, tous les paramètres et l'expiration : changer un seul caractère
invalide le lien.

### Magic link (bonus)

1. `POST /connexion/lien` : si l'email existe, envoyer `APP_URL . $magic->create($user)`.
   Toujours répondre « Si un compte existe, un lien a été envoyé. »
2. `GET /connexion/lien?...` : afficher seulement un bouton « Se connecter » (formulaire POST vers la
   même URL, avec le token CSRF). Les antivirus de messagerie ouvrent les liens des emails : un GET
   qui connecte consommerait le lien à usage unique.
3. `POST /connexion/lien?...` : `$auth->login($magic->consume($_SERVER['REQUEST_URI']))`, puis
   redirection 303 immédiate, pour que le lien ne reste ni dans la barre d'adresse ni dans le Referer.

## Ce que la Partie 3 attend des autres parties

### Partie 2 (Benjamin)

- Le modèle **User** implémente `App\Auth\Authenticatable`, le dépôt implémente `App\Auth\UserProviderInterface`.
- Table `users` : `email VARCHAR(255) UNIQUE` (stocké normalisé), `password_hash VARCHAR(255)`,
  `role VARCHAR(20) NOT NULL DEFAULT 'client'`. Le rôle ne vient **jamais** d'une entrée utilisateur
  (mass assignment).
- Le modèle **Order** implémente `App\Auth\Authorization\OwnedResource` (`getOwnerId()` = `user_id`).
- La liste « mes commandes » filtre en SQL (`WHERE user_id = ?`), elle ne charge jamais tout pour trier ensuite.
- Magic links : implémenter `Signing\UsedTokenStoreInterface`, par exemple une table
  `used_tokens(token VARCHAR(100) PRIMARY KEY, expires_at INT)`. Si l'INSERT échoue sur la clé
  primaire, renvoyer `false`.

### Partie 1 (Louis)

- Convertir les `App\Auth\Exception\AuthException` en réponse avec `statusCode()` ; leur message est
  sûr à afficher. Sur une page HTML, une `AuthenticationException` peut rediriger vers
  `/connexion?redirect=<chemin>`.
- Appeler `$csrf->verify(...)` sur chaque requête avant le contrôleur (dans un middleware s'il existe).
  Si le routeur accepte `_method=DELETE`, seulement sur un POST, jamais sur un GET.
- Le template doit permettre la sortie brute explicite pour `$csrf->field()` et `$csrf->metaTag()`.
- `APP_KEY` dans les secrets obligatoires : 32 octets aléatoires minimum, par exemple
  `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`.
- La validation du login garantit que l'email et le mot de passe sont des chaînes (refus des tableaux).

### Partie 4 (Erwan)

- `Referrer-Policy: strict-origin-when-cross-origin` au minimum, pour que la signature des URL
  ne fuie pas vers d'autres sites.
- L'import d'image par URL est réservé à la permission `caddy.import`.

### Partie 5 (Tom)

- Rate limiting autour de `$auth->attempt()` et de la demande de magic link.
- À logger : échec de login (`attempt()` renvoie `false` : email et IP, **jamais** le mot de passe),
  `CsrfTokenMismatchException`, `AccessDeniedException` (propriété `reason`) et
  `InvalidSignatureException` (propriété `reason` : `missing`, `invalid`, `expired` ou `already_used`).
- Ne jamais logger : mot de passe, token CSRF, cookie de session, paramètre `signature` des URL, magic links.
