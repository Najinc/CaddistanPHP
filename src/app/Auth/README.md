# Partie 3 : Authentification et accès (Najib)

La logique est dans `app/Auth/`, les tests dans `tests/Auth/`. Le reste du site l'utilise à travers
`App\Core\Auth` et `App\Core\Session`, qui gardent leur API statique.

## Les fichiers

| Fichier | Rôle |
|---|---|
| `PasswordHasher` | Hash Argon2id, vérification, `password_needs_rehash` |
| `Authenticator` | Login (même message et même temps de réponse que l'email existe ou non), logout, utilisateur connecté |
| `Session/NativeSession` | Cookie HttpOnly, Secure, SameSite=Lax, `__Host-` ; `use_strict_mode` ; ID régénéré ; timeout de 30 min ; logout complet |
| `Csrf/CsrfTokenManager` | Token CSRF par session, vérifié avec `hash_equals` sur tout sauf GET, HEAD et OPTIONS |
| `Authorization/Gate` + `permissions.php` | Rôles → permissions (refus par défaut), contrôle de propriété (404) |
| `Signing/UrlSigner` | URL signées : HMAC-SHA256 avec `APP_KEY`, expiration |
| `Signing/MagicLink` | Bonus, non branché : connexion par lien email, 15 min, usage unique |
| `SafeRedirect` | Redirection après login sans open redirect |
| `Exception/*` | Erreurs 401, 403 et 404. Elles héritent de `App\Core\HttpException`, donc `index.php` les affiche |
| `Bridge/*` | Adaptent les tableaux SQL des repositories aux interfaces de la Partie 3, sans ajouter de SQL |
| Interfaces | `Authenticatable`, `UserProviderInterface`, `OwnedResource`, `UsedTokenStoreInterface` |

## Utilisation dans le site

```php
// Contrôleur : permission obligatoire (connexion demandée si besoin, 403 sinon)
$this->authorize('caddy.edit');

// Contrôle de propriété : 404 si la commande appartient à un autre client
$order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound();
Auth::authorizeOwner($order);

// Mots de passe : jamais en clair
$users->create($email, Auth::hashPassword($password), ...);
Auth::checkPassword($current);     // « mot de passe actuel »
Auth::renewSession();               // après un changement de mot de passe

// Affichage seulement (la protection reste authorize() dans le contrôleur)
<?php if (Auth::can('admin.dashboard')): ?> ... <?php endif ?>
```

Dans **chaque** formulaire POST : `{!! csrf_field() !!}`. Le token est vérifié dans `public/index.php`,
avant le routeur, sur toute requête autre que GET, HEAD et OPTIONS. Aucune route GET ne doit modifier
quoi que ce soit (la déconnexion se fait en POST).

Les permissions se trouvent dans `permissions.php`. On vérifie toujours une permission, jamais un rôle.

### Routes signées

Le lien de suivi `/suivi/commandes/{id}` (page « Commande n°… » du compte) est signé avec `APP_KEY`
et valable 7 jours. Un lien modifié ou expiré répond 403.

```php
$url = Auth::signer()->sign('/suivi/commandes/' . $id, 7 * 24 * 3600);
Auth::signer()->verify($_SERVER['REQUEST_URI']);   // InvalidSignatureException (403) sinon
```

`APP_KEY` est obligatoire au démarrage. `make setup` la génère, et `make app_key` la crée dans un
`.env` existant.

## Lancer les tests

```sh
# depuis src/
composer dump-autoload
php tests/Auth/run.php

# ou dans le conteneur
docker compose exec app php tests/Auth/run.php
```

`NativeSessionHttpTest` lance un vrai serveur `php -S` et lit les en-têtes `Set-Cookie` réellement envoyés.

Après la fusion : `make app_key` (si le `.env` existe déjà) puis `make db_reset` (mots de passe du seed
hachés).

## Ce que la Partie 3 attend des autres parties

**Partie 2 (Benjamin)**
- Requêtes préparées dans `UserRepository` (la recherche par email sert au login) et `OrderRepository`.
- `UserRepository::findByCredentials()` n'est plus utilisée : elle peut être supprimée.
- Le rôle ne vient jamais d'une saisie utilisateur.
- Magic links (si on les branche) : implémenter `UsedTokenStoreInterface`, par exemple avec une table
  `used_tokens(token VARCHAR(100) PRIMARY KEY, expires_at INT)`.

**Partie 4 (Erwan)**
- `Referrer-Policy: strict-origin-when-cross-origin` au minimum sur tout le site (la page de suivi envoie
  déjà `no-referrer`).
- Un upload plus gros que `post_max_size` arrive avec un `$_POST` vide, donc sans token CSRF : il est
  refusé en 403. Vérifier la taille (`CONTENT_LENGTH`) avant si on veut un message plus clair.

**Partie 5 (Tom)**
- Rate limiting autour de `Auth::attempt()` (login) et de l'inscription.
- À logger : échecs de login (email et IP, jamais le mot de passe), `CsrfTokenMismatchException`,
  `AccessDeniedException` (propriété `reason`) et `InvalidSignatureException` (propriété `reason` :
  `missing`, `invalid`, `expired` ou `already_used`).
- Ne jamais logger : mot de passe, token CSRF, cookie de session, paramètre `signature` des URL.
