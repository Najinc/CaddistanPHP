# Récap Partie 3 (Najib) — authentification et accès

État au 03/11, branche `najib`.

## Reprendre sur un autre PC

```sh
git fetch origin
git checkout najib          # ou : git switch najib
cd src
composer dump-autoload      # src/vendor/ n'est pas versionné
php tests/Auth/run.php      # 57 tests, tous verts
```

Dans Docker : `docker compose exec app php tests/Auth/run.php`.

## Où en est la branche

- 13 petits commits (en anglais, sans Co-Authored-By), rebasés sur le `main` de Louis (`fc3fd0c`).
- Tout le code de la partie est dans `src/app/Auth/`, les tests dans `src/tests/Auth/`.
- Aucun fichier des autres n'est modifié pour l'instant.
- Doc détaillée (utilisation et contrat avec les autres parties) : `src/app/Auth/README.md`.

### Ce qui est fait (bibliothèque testée)

| Exigence | Classe |
|---|---|
| Argon2id, `password_needs_rehash`, même message et même temps de réponse que le compte existe ou non | `PasswordHasher`, `Authenticator` |
| `session_regenerate_id(true)` au login, destruction complète au logout, timeout d'inactivité de 30 min | `Session\NativeSession`, `Authenticator` |
| Cookies HttpOnly, Secure, SameSite=Lax, préfixe `__Host-`, `use_strict_mode` | `Session\NativeSession` |
| Token CSRF par session sur tout sauf GET/HEAD/OPTIONS, `hash_equals` | `Csrf\CsrfTokenManager` |
| Rôles → permissions, refus par défaut | `Authorization\Gate`, `permissions.php` |
| Contrôle de propriété (404 sur la commande d'un autre) | `Gate::authorizeOwnership()` |
| Routes signées HMAC (clé `APP_KEY`), bonus magic links à usage unique | `Signing\UrlSigner`, `Signing\MagicLink` |
| Redirection après login sans open redirect | `SafeRedirect` |

Démo pour la soutenance : sans la ligne `$this->hasher->hash($password)` du cas « email inconnu »
dans `Authenticator::attempt()`, un email inconnu répond en 0,0 ms contre 49,7 ms pour un mauvais
mot de passe. Le test `email inconnu prend autant de temps…` le détecte.

## Ce que Louis a poussé sur main (fc3fd0c)

Une boutique complète **volontairement non sécurisée** : routeur, `Request`/`Response`, session, vues,
MariaDB, back-office, API. Son `README.md` liste où chaque protection doit être ajoutée.

Failles qui concernent la Partie 3 :

| Problème | Où |
|---|---|
| Mots de passe en clair, comparés dans le SQL | `Repositories/UserRepository.php` (`findByCredentials`), `database/seed.sql` |
| Pas de régénération d'ID, cookies sans attributs, cookie non supprimé au logout | `Core/Auth.php`, `Core/Session.php` |
| Changement de mot de passe comparé en clair | `AccountController::update()` |
| Aucun token CSRF sur les 13 formulaires POST | `views/*` |
| **`/admin` accessible à tous, même déconnecté** (on peut se passer admin via `/admin/utilisateurs/{id}/role`) | `Controllers/Admin/*` |
| IDOR sur `/compte/commandes/{id}` **et** `/commande/confirmation/{id}` (ce dernier n'est pas dans son README) | `AccountController::order()`, `CheckoutController::confirmation()` |
| Redirection après login (`intended`) non filtrée | `AuthController::redirectIntended()` |

## À faire

### 1. Adapter ma bibliothèque (mes fichiers uniquement)

- Rôles : `customer` (et pas `client`) dans `permissions.php` et les tests.
- Ses utilisateurs et commandes sont des **tableaux** : ajouter deux petits adaptateurs vers
  `Authenticatable` / `UserProviderInterface` (qui appelle son `UserRepository`) et `OwnedResource`.
- ~~Faire hériter les exceptions de `App\Core\HttpException`~~ : fait, son `index.php` renvoie
  directement 401 (redirection vers `/connexion`) / 403 / 404.

### 2. Intégration dans la base de Louis (décision en attente)

Fichiers à modifier, ceux que son README attribue à la Partie 3 :

- `Core/Auth.php` et `Core/Session.php` : garder les mêmes méthodes statiques (`Auth::user()`,
  `Auth::check()`…) mais déléguer à mon code, pour ne rien changer ailleurs.
- `AuthController` (hash à l'inscription, `SafeRedirect` sur `intended`), `AccountController`
  (vérif du mot de passe actuel, propriété des commandes), `CheckoutController::confirmation`.
- `Controllers/Admin/*` : une vérification de permission par action.
- Vues :
  - les 13 formulaires POST : une ligne `csrf_field()` chacun. L'ajout au panier en AJAX envoie déjà
    le formulaire complet (`new FormData(form)`), donc rien à changer dans `main.ts` ;
  - le header : remplacer `Auth::isAdmin()` par une vérification de permission.
- `public/index.php` : une ligne pour vérifier le CSRF sur toutes les requêtes.
- `database/seed.sql` : remplacer les deux mots de passe par des hash Argon2id, puis `make db_reset`.

Ne pas toucher au SQL de `UserRepository` (Benjamin le passe en requêtes préparées) : utiliser
`findByEmail`, `create` et `updatePassword` existants en leur passant des hash. `findByCredentials`
ne servira plus, Benjamin pourra la supprimer.

Risque : petits conflits dans les vues et les contrôleurs quand Louis ajoutera l'échappement XSS et
la validation. **Prévenir Louis avant.**

## Points à transmettre aux autres

- **Louis** : permettre la sortie
  brute de `csrf_field()` quand il ajoutera l'échappement par défaut ; `APP_KEY` dans les secrets
  obligatoires si on utilise les routes signées ; valider la liste blanche des rôles dans
  `Admin\UserController::updateRole()`.
- **Benjamin** : requêtes préparées dans `UserRepository` et `OrderRepository` ; le rôle ne vient
  jamais d'une saisie utilisateur ; table `used_tokens` si on garde les magic links.
- **Erwan** : `Referrer-Policy: strict-origin-when-cross-origin` au minimum (signatures dans les URL).
- **Tom** : rate limiting autour du login et de l'inscription ; logger les échecs de login, CSRF
  invalides, 403 et signatures refusées ; jamais de mot de passe, token CSRF, cookie de session ni
  paramètre `signature` dans les logs.

## Remarques

- L'inscription répond « Un compte existe déjà avec cette adresse » : ça révèle quels emails sont
  inscrits. À discuter (l'exigence du sujet ne vise que le login).
- Commits : petits, en anglais, sans Co-Authored-By.
