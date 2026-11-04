# Partie 3 — Authentification et accès : récap complet

État au 04/11, branche `najib`, à jour avec `main` (Partie 1 de Louis comprise), prête pour la PR.

## 1. En bref

| Exigence du sujet | Où | Prouvé par |
|---|---|---|
| `password_hash` en Argon2id + `password_needs_rehash` | `PasswordHasher`, `Authenticator::attempt()` | `PasswordHasherTest`, `AuthenticatorTest` |
| Même message **et même temps** que le compte existe ou non | `Authenticator::attempt()` | `AuthenticatorTest` (timing) |
| `session_regenerate_id(true)` au login, destruction complète au logout, timeout d'inactivité | `NativeSession`, `Authenticator` | `NativeSessionHttpTest` |
| Cookies HttpOnly, Secure, SameSite=Lax, `use_strict_mode` | `NativeSession` | `NativeSessionHttpTest` |
| Token CSRF par session sur POST/PUT/PATCH/DELETE, `hash_equals` | `CsrfTokenManager` + `public/index.php` | `CsrfTokenManagerTest` |
| Rôles avec permissions, vérifiés côté serveur | `Gate` + `permissions.php` + `Controller::authorize()` | `GateTest` |
| Un client ne voit que ses commandes | `Gate::authorizeOwnership()` via `Auth::authorizeOwner()` | `GateTest`, `BridgeTest` |
| Routes signées HMAC (`APP_KEY`) + expiration, `hash_equals` | `UrlSigner` + lien de suivi `/suivi/commandes/{id}` | `UrlSignerTest` |
| Bonus : magic links | `MagicLink` (non branché : pas d'envoi d'email) | `MagicLinkTest` |
| En plus : pas d'open redirect après le login | `SafeRedirect` | `SafeRedirectTest` |

Tests : **62 tests unitaires** (`php tests/Auth/run.php`). J'ai aussi joué un scénario de bout en bout
contre le vrai site (php -S + SQLite à la place de MariaDB, pas versionné) : **47 vérifications sur 47,
aucun avertissement PHP**.

## 2. Comment ça marche : le trajet d'une requête

1. **`public/index.php`**
   - `require_env([... 'APP_KEY' ...])` : sans clé, l'application refuse de démarrer.
   - `Session::start()` → `NativeSession::start()` : cookie `__Host-caddistan_session` (HttpOnly,
     Secure, SameSite=Lax), `use_strict_mode`, et destruction si plus de 30 min d'inactivité.
   - `Auth::csrf()->verify(méthode, _csrf)` : tout POST sans le bon token → `CsrfTokenMismatchException` → 403.
   - Le routeur appelle le contrôleur.
2. **Contrôleur**
   - `$this->authorize('caddy.edit')` → `requireLogin()` (sinon 401, donc redirection vers
     `/connexion`) puis `Auth::authorize()` → `Gate` (sinon 403).
   - Sur une commande : `Auth::authorizeOwner($order)` → 404 si elle appartient à quelqu'un d'autre.
3. **Erreurs** : mes exceptions héritent de `App\Core\HttpException`, donc le `index.php` de Louis les
   affiche tout seul (401 → `/connexion`, 403/404 → page d'erreur, JSON pour l'API).

**Connexion** (`POST /connexion`) :
1. Le `Validator` de Louis vérifie l'email et le mot de passe, puis `Auth::attempt()` →
   `Authenticator::attempt()` cherche l'utilisateur : `RepositoryUserProvider` → `UserRepository::findByEmail()`.
2. Email inconnu : on hache quand même le mot de passe, pour avoir le même temps de réponse.
3. Sinon, `password_verify`. Si le hash est ancien, il est recalculé.
4. `login()` : nouvel ID de session (`session_regenerate_id(true)`), `user_id` en session, nouveau token CSRF.
5. Redirection vers la page demandée, filtrée par `SafeRedirect` (uniquement un chemin du site).

**Déconnexion** (`POST /deconnexion`, avec token) :
1. `NativeSession::destroy()` : données, fichier côté serveur et cookie.
2. Une session vierge est recréée pour le message flash.

**Lien de suivi signé** :
1. Sur `/compte/commandes/{id}`, `TrackingController::linkFor()` signe `/suivi/commandes/{id}` pour 7 jours.
2. Sur `/suivi/commandes/{id}?expires=…&signature=…`, `UrlSigner::verify()` recalcule le HMAC (`hash_equals`)
   et vérifie l'expiration. Un lien modifié ou expiré donne 403.
3. La page n'affiche pas l'adresse de livraison et envoie `Referrer-Policy: no-referrer`.

## 3. Fichier par fichier

### La bibliothèque `src/app/Auth/` (100 % Partie 3)

| Fichier | Ce qu'il fait |
|---|---|
| `Authenticatable.php` | Interface d'un utilisateur : id, hash du mot de passe, rôle. |
| `UserProviderInterface.php` | Interface d'accès aux utilisateurs : par id, par email, mise à jour du hash. |
| `PasswordHasher.php` | `password_hash` / `password_verify` / `password_needs_rehash` en Argon2id (paramètres réglables pour les tests). |
| `Authenticator.php` | `attempt()` (timing constant, rehash), `login()` (régénère l'ID et le token CSRF), `logout()`, `user()` (relu en base à chaque requête : un rôle modifié s'applique tout de suite), `normalizeEmail()`. |
| `Session/SessionInterface.php` | Ce dont la Partie 3 a besoin d'une session : get, set, regenerate, destroy. |
| `Session/NativeSession.php` | Session PHP réglée dans le code (pas de dépendance au php.ini) : cookie `__Host-`, HttpOnly, Secure, SameSite=Lax, `use_strict_mode`, timeout d'inactivité, destruction complète avec suppression du cookie. |
| `Csrf/CsrfTokenManager.php` | Token de 32 octets aléatoires par session, `verify()` avec `hash_equals` sur toute méthode sauf GET/HEAD/OPTIONS, `regenerate()` au login, `field()` pour les formulaires. |
| `Authorization/Gate.php` | `can()`, `authorize()` (401/403), `authorizeOwnership()` (404). Refus par défaut. |
| `Authorization/OwnedResource.php` | Interface d'une ressource qui appartient à un utilisateur. |
| `permissions.php` | Rôle → permissions : `customer` (commander, voir ses commandes, laisser un avis) et `admin` (tout, plus une permission par action d'administration). |
| `Signing/UrlSigner.php` | `sign()` ajoute `expires` et `signature` (HMAC-SHA256 de l'URL avec `APP_KEY`). `verify()` compare avec `hash_equals`, puis vérifie l'expiration. |
| `Signing/MagicLink.php` | Bonus : lien de connexion signé, 15 min, usage unique, lié au chemin `/connexion/lien`. |
| `Signing/UsedTokenStoreInterface.php` | Mémoire des jetons à usage unique (à implémenter en base si on branche les magic links). |
| `SafeRedirect.php` | N'accepte qu'un chemin local (refuse `//evil.com`, `/\evil.com`, `https://…`, caractères de contrôle). |
| `Exception/AuthenticationException.php` | 401 : personne n'est connecté. |
| `Exception/AccessDeniedException.php` | 403 (permission manquante) ou 404 (ressource d'un autre). `reason` sert aux logs. |
| `Exception/CsrfTokenMismatchException.php` | 403 : token CSRF absent ou faux. |
| `Exception/InvalidSignatureException.php` | 403 : lien signé refusé. `reason` = `missing`, `invalid`, `expired` ou `already_used`. |
| `Bridge/UserRow.php` | Transforme une ligne `users` (tableau) en `Authenticatable`. |
| `Bridge/OwnedRow.php` | Transforme une ligne avec `user_id` (commande…) en `OwnedResource`. |
| `Bridge/RepositoryUserProvider.php` | `UserProviderInterface` qui appelle le `UserRepository` existant, sans ajouter de SQL. |
| `README.md` | Doc pour l'équipe : utilisation et attentes envers les autres parties. |

### Fichiers de la base modifiés pour brancher la Partie 3

Ce sont les endroits que le README de Louis attribue à la Partie 3 (« Où ça se passe »).

| Fichier | Modification |
|---|---|
| `app/Core/Auth.php` | Réécrit : même API statique, mais délègue à `Authenticator`, `Gate`, `CsrfTokenManager`, `PasswordHasher`, `UrlSigner`. `user()` ne renvoie plus le hash. `isAdmin()` remplacé par `can()` / `authorize()` / `authorizeOwner()`. |
| `app/Core/Session.php` | `start()`, `regenerate()` et `destroy()` passent par `NativeSession`. Les flashs ne changent pas. |
| `app/Controllers/Controller.php` | Ajout de `authorize($permission)`. |
| `app/Controllers/AuthController.php` | Hash à l'inscription, message d'échec unique, `SafeRedirect` sur la page demandée avant connexion. |
| `app/Controllers/AccountController.php` | Mot de passe actuel vérifié avec `password_verify` **avant** d'enregistrer quoi que ce soit, nouveau hash, nouvelle session. Propriété des commandes, lien de suivi. |
| `app/Controllers/CheckoutController.php` | `order.create` pour commander. Propriété sur `/commande/confirmation/{id}` (IDOR absent du README de Louis). |
| `app/Controllers/ReviewController.php` | `review.create`. |
| `app/Controllers/Admin/*.php` | Une permission par action. Un admin ne peut pas changer son propre rôle. |
| `app/Controllers/TrackingController.php` | **Nouveau** : page de suivi par lien signé. |
| `public/index.php` | `APP_KEY` obligatoire, vérification CSRF avant le routeur. |
| `app/helpers.php` | `csrf_field()`. |
| `routes.php` | `GET /suivi/commandes/{id}`. |
| `views/**` | `{!! csrf_field() !!}` dans les 14 formulaires POST ; header : `Auth::can('admin.dashboard')` ; lien de suivi sur la page commande ; `views/tracking/show.php` (**nouveau**). |
| `database/seed.sql` | Mots de passe de démo hachés en Argon2id (toujours `Admin123!` / `Client123!`). |
| `.env.example`, `compose.yml`, `Makefile` | `APP_KEY` : variable, passage au conteneur, génération par `make app_key` (appelée par `make setup`). |

### Les tests `src/tests/Auth/`

| Fichier | Ce qu'il prouve |
|---|---|
| `run.php`, `TestCase.php` | Mini-framework de test sans dépendance. Un warning fait échouer le test. |
| `Support/Fakes.php` | Faux utilisateurs, commandes, session et magasin de jetons en mémoire. |
| `PasswordHasherTest` | Argon2id, sel aléatoire, rehash d'un ancien bcrypt. |
| `AuthenticatorTest` | Régénération de l'ID et du token CSRF, email inconnu aussi lent qu'un mauvais mot de passe, logout, compte supprimé. |
| `NativeSessionHttpTest` + `fixtures/session_app.php` | Vrai serveur `php -S` : attributs du cookie, strict mode, ID changé au login, logout, timeout. |
| `CsrfTokenManagerTest` | GET passe, POST/PUT/PATCH/DELETE sans token, avec un faux token ou un token en tableau → 403. |
| `GateTest` | Permissions client/admin, refus par défaut, 401/403/404. |
| `BridgeTest` | Conversion des lignes SQL, propriété via `user_id`. |
| `UrlSignerTest` | Paramètre, chemin ou expiration modifié → refusé ; lien expiré ; autre clé. |
| `MagicLinkTest` | Usage unique, expiration, utilisateur modifié, URL signée d'une autre page. |
| `SafeRedirectTest` | Les redirections vers un autre site sont refusées. |

## 4. Ce que j'utilise des autres parties

**Partie 1 (Louis)** :
- `HttpException` : mes exceptions en héritent, donc son `index.php` les affiche.
- `Request::input()` : refuse les tableaux, donc un `_csrf[]=` donne 400.
- `Validator` : email et mot de passe validés avant `Auth::attempt()` et à l'inscription.
- `routeId()` : id strict avant le contrôle de propriété.
- Templates `{{ }}` / `{!! !!}` : `csrf_field()` est la sortie brute explicite.
- `require_env()` et `env()` (pour `APP_KEY`), le routeur et `routes.php`.

**Partie 2 (Benjamin)** :
- `UserRepository` : `find`, `findByEmail`, `create`, `updatePassword`, via `RepositoryUserProvider`.
- `OrderRepository::find` pour la propriété.
- ⚠ Ces requêtes sont encore concaténées : tant qu'elles ne sont pas préparées, la recherche par email
  du login reste injectable. C'est sa partie, mais elle est prioritaire.

**Partie 4 (Erwan)** et **Partie 5 (Tom)** : rien n'est utilisé. Je ne pose que `Referrer-Policy` sur la
page de suivi, à cause de la signature dans l'URL.

## 5. À transmettre

- **Benjamin** : requêtes préparées dans `UserRepository` et `OrderRepository` ; `findByCredentials()`
  peut être supprimée ; table `used_tokens` seulement si on branche les magic links.
- **Louis** : je modifie les fichiers listés plus haut (surtout `Core/Auth.php`, `Core/Session.php`,
  `index.php`, 14 formulaires). Si son `.env` existe déjà, il doit lancer `make app_key`.
- **Erwan** :
  - `Referrer-Policy` sur tout le site ;
  - aucune de mes vues n'ajoute de script inline (compatible avec sa CSP) ;
  - un upload plus gros que `post_max_size` arrive sans `$_POST`, donc sans token : c'est un 403 CSRF.
- **Tom** :
  - rate limiting sur `/connexion` et `/inscription` ;
  - logger les échecs de login (email et IP), `CsrfTokenMismatchException`, `AccessDeniedException`
    et `InvalidSignatureException` (propriété `reason`) ;
  - ne jamais logger : mot de passe, token CSRF, cookie de session, paramètre `signature`. Attention :
    le lien de suivi complet contient la signature.
- **Remarque** : l'inscription répond « Un compte existe déjà avec cette adresse », ce qui révèle quels
  emails sont inscrits. Le sujet ne l'exige que pour le login ; à discuter.

## 6. Démo pour la soutenance

Après la fusion : `make app_key` (si le `.env` existe) puis `make db_reset`.

| Protection | Démo |
|---|---|
| Cookies | DevTools → Application → Cookies : `__Host-caddistan_session`, HttpOnly, Secure, SameSite=Lax |
| Régénération d'ID | Noter le cookie, se connecter : la valeur change |
| CSRF | DevTools : supprimer le champ caché `_csrf` d'un formulaire puis l'envoyer → 403 |
| Rôles | Connecté en client, ouvrir `/admin` → 403 ; le lien Admin n'apparaît pas |
| IDOR | Connecté en client, changer l'id dans `/compte/commandes/{id}` → 404 |
| Route signée | Copier le lien de suivi, changer l'id ou `expires` → 403 ; sans compte, le lien valide marche |
| Mots de passe | `make db_shell` → `SELECT email, password FROM users;` : que des `$argon2id$…` |
| Même temps de réponse | Retirer la ligne `$this->hasher->hash($password)` dans `Authenticator::attempt()` : le test passe de ~50 ms à 0 ms et échoue |
