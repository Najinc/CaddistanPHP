# Partie 3 : Authentification et accès (Najib)

Code dans `app/Auth/`, tests dans `tests/Auth/`. Aucun fichier des autres parties n'est modifié.

## Les fichiers

| Fichier | Rôle |
|---|---|
| `PasswordHasher` | Hash Argon2id, vérification, `password_needs_rehash` |
| `Authenticator` | Login (même message et même temps de réponse que l'email existe ou non), logout, utilisateur connecté |
| `Session/NativeSession` | Cookie HttpOnly, Secure, SameSite=Lax, `__Host-` ; `use_strict_mode` ; ID régénéré ; timeout de 30 min ; logout complet |
| `Csrf/CsrfTokenManager` | Token CSRF par session, vérifié avec `hash_equals` sur tout sauf GET, HEAD et OPTIONS |
| `Authorization/Gate` + `permissions.php` | Rôles → permissions (refus par défaut), contrôle de propriété (404) |
| `Signing/UrlSigner` | URL signées : HMAC-SHA256 avec `APP_KEY`, expiration |
| `Signing/MagicLink` | Bonus : connexion par lien email, 15 min, usage unique |
| `SafeRedirect` | Redirection après login sans open redirect |
| `Exception/*` | Erreurs 401, 403 et 404. Elles héritent de `App\Core\HttpException`, donc `index.php` les affiche déjà |
| Interfaces | `Authenticatable`, `UserProviderInterface`, `OwnedResource`, `UsedTokenStoreInterface` : implémentées par la Partie 2 |

## Lancer les tests

```sh
# depuis src/
composer dump-autoload
php tests/Auth/run.php

# ou dans le conteneur
docker compose exec app php tests/Auth/run.php
```

`NativeSessionHttpTest` lance un vrai serveur `php -S` et lit les en-têtes `Set-Cookie` réellement envoyés.

## Mise en place

```php
$session = new NativeSession(secure: true, idleTimeout: 1800);
$csrf    = new CsrfTokenManager($session);
$auth    = new Authenticator($session, $users, new PasswordHasher(), $csrf); // $users : Partie 2
$gate    = new Gate(require __DIR__ . '/../app/Auth/permissions.php');
$signer  = new UrlSigner(env('APP_KEY'));
$magic   = new MagicLink($signer, $usedTokens, $users);                      // $usedTokens : Partie 2
```

## Utilisation

### CSRF

Sur chaque requête, avant le contrôleur :

```php
$csrf->verify($request->method, $request->input(CsrfTokenManager::FIELD));
```

Dans chaque formulaire POST (sortie brute : le champ ne contient que du hexadécimal) :

```php
<form method="post" action="/panier">
    {!! $csrf->field() !!}
    ...
</form>
```

L'ajout au panier en AJAX envoie le formulaire entier (`new FormData(form)`), donc le champ part avec.
Aucune route GET ne doit modifier quoi que ce soit : la déconnexion se fait en POST.

### Connexion et déconnexion

```php
// POST /connexion
if (!$auth->attempt($email, $password)) {
    // afficher Authenticator::FAILURE_MESSAGE, quelle que soit la cause
}
// puis redirection vers SafeRedirect::target($intended, '/compte')

// POST /deconnexion
$auth->logout();
```

À l'inscription : `Authenticator::normalizeEmail($email)`, `$hasher->hash($password)`, rôle fixé par le
serveur, puis `$auth->login($user)`.

### Autorisations

```php
$gate->authorize($auth->user(), 'caddy.edit');                   // 401 si non connecté, 403 sinon

$gate->authorize($auth->user(), 'order.view');
$gate->authorizeOwnership($auth->user(), $order, 'order.view_any'); // 404 si la commande est à un autre
```

On vérifie toujours une permission, jamais un rôle. `$gate->can(...)` sert à cacher un bouton dans une
vue, mais seule la vérification dans le contrôleur protège.

### Routes signées

```php
$url = $signer->sign("/commandes/{$id}/facture", 3600);  // valable 1 h, URL déjà encodée
$signer->verify($_SERVER['REQUEST_URI']);                 // InvalidSignatureException (403) sinon
```

### Magic link (bonus)

1. `POST /connexion/lien` : si l'email existe, envoyer `APP_URL . $magic->create($user)`.
   Toujours répondre « Si un compte existe, un lien a été envoyé. »
2. `GET /connexion/lien?...` : afficher seulement un bouton « Se connecter » (un POST vers la même URL).
   Les antivirus de messagerie ouvrent les liens des emails : un GET qui connecte gâcherait le lien.
3. `POST /connexion/lien?...` : `$auth->login($magic->consume($_SERVER['REQUEST_URI']))`, puis redirection.

## Ce que la Partie 3 attend des autres parties

**Partie 2 (Benjamin)**
- Le modèle User implémente `Authenticatable`, le dépôt implémente `UserProviderInterface`, le modèle
  Order implémente `OwnedResource`.
- Le rôle ne vient jamais d'une saisie utilisateur.
- « Mes commandes » filtre en SQL (`WHERE user_id = ?`).
- Magic links : implémenter `UsedTokenStoreInterface`, par exemple avec une table
  `used_tokens(token VARCHAR(100) PRIMARY KEY, expires_at INT)`.

**Partie 1 (Louis)**
- `APP_KEY` dans les variables obligatoires (32 octets aléatoires minimum :
  `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`).

**Partie 4 (Erwan)**
- `Referrer-Policy: strict-origin-when-cross-origin` au minimum, pour que la signature des URL ne fuie pas.
- L'import d'image par URL est réservé à la permission `caddy.import`.

**Partie 5 (Tom)**
- Rate limiting autour de `$auth->attempt()` et de la demande de magic link.
- À logger : échecs de login (email et IP, jamais le mot de passe), `CsrfTokenMismatchException`,
  `AccessDeniedException` (propriété `reason`) et `InvalidSignatureException` (propriété `reason` :
  `missing`, `invalid`, `expired` ou `already_used`).
- Ne jamais logger : mot de passe, token CSRF, cookie de session, paramètre `signature`, magic links.
