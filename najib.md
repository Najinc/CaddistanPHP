# Partie 3 — Authentification et accès : récap complet

État au 04/11, branche `najib`, à jour avec `main` (PR #7 de Tom comprise).
Partie 3 fusionnée par la PR #2, correctif path traversal par la PR #5. Magic links branchés et boîte
mail de test : sur `najib`, pas encore fusionnés.

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
| Bonus : magic links | `MagicLinkController`, `MagicLink`, `FileUsedTokenStore` | `MagicLinkTest`, `FileUsedTokenStoreTest` |
| En plus : pas d'open redirect après le login | `SafeRedirect` | `SafeRedirectTest` |
| Path traversal (point de la Partie 4 qui m'est attribué) | `DocumentController::safePath()` | `PathTraversalTest` |
| En plus : boîte mail de test (hors production) | `App\Mail\*` + widget `views/partials/mail-widget.php` | `DevMailboxTest` |

Tests : **71 tests unitaires** (`php tests/Auth/run.php`, depuis `src/`). J'ai aussi joué des scénarios de
bout en bout contre le vrai site, Partie 5 de Tom comprise (php -S + SQLite à la place de MariaDB, pas
versionnés) : **47 + 22 vérifications (magic links, boîte mail) + 5 en mode production, aucun avertissement
PHP**. Les scénarios à jouer à la main sont dans la page de révision (section « Scénarios de test »).

## 2. Comment ça marche : le trajet d'une requête

1. **`public/index.php`**
   - `ExceptionHandler::register()` (Tom) en premier : toute erreur donne une page générique et une ligne de log.
   - `require_env([... 'APP_KEY' ...])` : sans clé, l'application refuse de démarrer.
   - `Session::start()` → `NativeSession::start()` : cookie `__Host-caddistan_session` (HttpOnly,
     Secure, SameSite=Lax), `use_strict_mode`, et destruction si plus de 30 min d'inactivité.
   - `Throttle::before()` (Tom) : limite de requêtes sur `/connexion`, `/inscription` et `/api/*`, sinon 429.
     Il passe avant le CSRF pour compter aussi les rafales sans token.
   - `Auth::csrf()->verify(méthode, _csrf)` : tout POST sans le bon token → `CsrfTokenMismatchException` → 403.
   - Le routeur appelle le contrôleur.
2. **Contrôleur**
   - `$this->authorize('caddy.edit')` → `requireLogin()` (sinon 401, donc redirection vers
     `/connexion`) puis `Auth::authorize()` → `Gate` (sinon 403).
   - Sur une commande : `Auth::authorizeOwner($order)` → 404 si elle appartient à quelqu'un d'autre.
3. **Erreurs** : mes exceptions héritent de `App\Core\HttpException`. Le `catch` de `index.php` les passe à
   `ExceptionHandler::handle()` (Tom) :
   - 401 → la page demandée est mémorisée, redirection vers `/connexion` ;
   - 403 / 404 → page d'erreur (JSON pour l'API) ;
   - au passage, une ligne dans `storage/logs/security-*.log` : `csrf.invalid`, `signature.invalid` ou
     `access.denied`, avec la raison (`reason`) de mes exceptions.

**Connexion** (`POST /connexion`) :
1. `Throttle` (Tom) : 5 essais / 15 min par email, 20 / 15 min par IP, sinon 429.
2. Le `Validator` de Louis vérifie l'email et le mot de passe, puis `Auth::attempt()` →
   `Authenticator::attempt()` cherche l'utilisateur : `RepositoryUserProvider` → `UserRepository::findByEmail()`.
3. Email inconnu : on hache quand même le mot de passe, pour avoir le même temps de réponse.
4. Sinon, `password_verify`. Si le hash est ancien, il est recalculé.
5. `login()` : nouvel ID de session (`session_regenerate_id(true)`), `user_id` en session, nouveau token CSRF.
6. Log `auth.login_failed` ou `auth.login_succeeded` (email et IP, jamais le mot de passe), ajouté par Tom
   dans `AuthController`.
7. Redirection vers la page demandée, filtrée par `SafeRedirect` (uniquement un chemin du site).

**Déconnexion** (`POST /deconnexion`, avec token) :
1. `NativeSession::destroy()` : données, fichier côté serveur et cookie.
2. Une session vierge est recréée pour le message flash.

**Lien de suivi signé** :
1. Sur `/compte/commandes/{id}`, `TrackingController::linkFor()` signe `/suivi/commandes/{id}` pour 7 jours.
2. Sur `/suivi/commandes/{id}?expires=…&signature=…`, `UrlSigner::verify()` recalcule le HMAC de tout ce
   qui précède `&signature=` (`hash_equals`), puis vérifie l'expiration. Un lien modifié ou expiré donne 403.
3. La page n'affiche pas l'adresse de livraison et envoie `Referrer-Policy: no-referrer`.
4. Le même lien part aussi par e-mail à chaque commande (`CheckoutController`, via `Mailer::send()`).

**Magic link** (bonus) :
1. `GET /connexion/sans-mot-de-passe` (lien sous le formulaire de connexion), puis `POST` avec l'email.
2. `MagicLinkController::send()` : au plus 3 demandes par email et 10 par IP en 15 min (`RateLimiter` de
   Tom), un e-mail seulement si le compte existe, mais toujours le même message.
3. Le lien `/connexion/lien?user=…&nonce=…&expires=…&signature=…` est une URL signée valable 15 min.
4. `GET` sur le lien : signature vérifiée, la page n'affiche qu'un bouton « Se connecter ». Un antivirus
   de messagerie qui ouvre le lien ne le consomme donc pas.
5. `POST` du bouton (avec le token CSRF) : `Auth::loginWithMagicLink()` → `MagicLink::consume()` vérifie
   signature, chemin, puis `markUsed(nonce)`. `FileUsedTokenStore` crée un fichier par nonce avec
   `fopen(…, 'x')`, qui échoue si le fichier existe : un second usage donne 403 `already_used`.
6. `login()` : nouvel ID de session, nouveau token CSRF, comme une connexion classique.

**Boîte mail de test** : `Mailer::send()` écrit l'e-mail en JSON dans `storage/mails/` (50 derniers gardés)
si `APP_ENV` n'est pas `production`. Le widget (enveloppe en bas à droite) les affiche, en HTML pur avec
`<details>`, sans script, donc compatible avec la CSP de Tom. En production : pas de widget, et les routes
de la boîte et des magic links répondent 404.

## 3. Fichier par fichier

### La bibliothèque `src/app/Auth/` (100 % Partie 3)

| Fichier | Ce qu'il fait |
|---|---|
| `Authenticatable.php` | Interface d'un utilisateur : id, hash du mot de passe, rôle. |
| `UserProviderInterface.php` | Interface d'accès aux utilisateurs : par id, par email, mise à jour du hash. |
| `PasswordHasher.php` | `password_hash` / `password_verify` / `password_needs_rehash` en Argon2id (paramètres réglables pour les tests). |
| `Authenticator.php` | `attempt()` (timing constant, rehash), `login()` (régénère l'ID et le token CSRF), `logout()`, `user()` (relu en base à chaque requête : un rôle modifié s'applique tout de suite). L'email est mis en minuscules avant la recherche. |
| `Session/SessionInterface.php` | Ce dont la Partie 3 a besoin d'une session : get, set, regenerate, destroy. |
| `Session/NativeSession.php` | Session PHP réglée dans le code (pas de dépendance au php.ini) : cookie `__Host-`, HttpOnly, Secure, SameSite=Lax, `use_strict_mode`, timeout d'inactivité, destruction complète avec suppression du cookie. |
| `Csrf/CsrfTokenManager.php` | Token de 32 octets aléatoires par session, `verify()` avec `hash_equals` sur toute méthode sauf GET/HEAD/OPTIONS, `regenerate()` au login, `field()` pour les formulaires. |
| `Authorization/Gate.php` | `can()`, `authorize()` (401/403), `authorizeOwnership()` (404). Refus par défaut. |
| `Authorization/OwnedResource.php` | Interface d'une ressource qui appartient à un utilisateur. |
| `permissions.php` | Rôle → permissions : `customer` (commander, voir ses commandes, laisser un avis) et `admin` (tout, plus une permission par action d'administration). |
| `Signing/UrlSigner.php` | `sign()` ajoute `expires` et `signature` (HMAC-SHA256 de l'URL avec `APP_KEY`). `verify()` compare avec `hash_equals`, puis vérifie l'expiration. Refuse une clé de moins de 32 octets. |
| `Signing/MagicLink.php` | Bonus : lien de connexion signé, 15 min, usage unique, lié au chemin `/connexion/lien`. |
| `Signing/UsedTokenStoreInterface.php` | Mémoire des jetons à usage unique. |
| `Signing/FileUsedTokenStore.php` | Implémentation : un fichier par jeton dans `storage/used-tokens/`, nommé par son SHA-256, créé avec `fopen(…, 'x')` (atomique). Les jetons expirés sont oubliés. |
| `SafeRedirect.php` | N'accepte qu'un chemin local (refuse `//evil.com`, `/\evil.com`, `https://…`, caractères de contrôle). |
| `Exception/AuthenticationException.php` | 401 : personne n'est connecté. |
| `Exception/AccessDeniedException.php` | 403 (permission manquante) ou 404 (ressource d'un autre). `reason` part dans les logs, jamais à l'écran. |
| `Exception/CsrfTokenMismatchException.php` | 403 : token CSRF absent ou faux. |
| `Exception/InvalidSignatureException.php` | 403 : lien signé refusé. `reason` = `missing`, `invalid`, `expired` ou `already_used`. |
| `Bridge/UserRow.php` | Transforme une ligne `users` (tableau) en `Authenticatable`. |
| `Bridge/OwnedRow.php` | Transforme une ligne avec `user_id` (commande…) en `OwnedResource`. |
| `Bridge/RepositoryUserProvider.php` | `UserProviderInterface` qui appelle le `UserRepository` existant, sans ajouter de SQL. |
| `README.md` | Doc pour l'équipe : utilisation et attentes envers les autres parties. |

### La boîte mail de test `src/app/Mail/` et les nouvelles pages

| Fichier | Ce qu'il fait |
|---|---|
| `Mail/Mail.php` | Un e-mail : destinataire, sujet, texte, lien et libellé du bouton, date. Converti en tableau pour le JSON. |
| `Mail/DevMailbox.php` | Stocke les e-mails en JSON dans `storage/mails/` (jamais `serialize`), les relit du plus récent au plus ancien, garde les 50 derniers, se vide. |
| `Mail/Mailer.php` | Point d'envoi unique : `send()` n'écrit dans la boîte que si `APP_ENV` n'est pas `production`. |
| `Controllers/MagicLinkController.php` | Demande du lien (limite, message neutre), page avec le bouton, connexion au POST. |
| `Controllers/DevMailController.php` | Bouton « Vider » de la boîte (POST avec token CSRF, 404 en production). |
| `views/auth/magic-link-request.php`, `views/auth/magic-link.php` | Formulaire de demande, page « Se connecter ». |
| `views/partials/mail-widget.php` | L'enveloppe en bas à droite et la liste des e-mails. |

### Fichiers de la base modifiés pour brancher la Partie 3

Ce sont les endroits que le README de Louis attribue à la Partie 3 (« Où ça se passe »).

| Fichier | Modification |
|---|---|
| `app/Core/Auth.php` | Réécrit : même API statique, mais délègue à `Authenticator`, `Gate`, `CsrfTokenManager`, `PasswordHasher`, `UrlSigner`, `MagicLink`. `user()` ne renvoie plus le hash. `isAdmin()` remplacé par `can()` / `authorize()` / `authorizeOwner()`. Ajout de `magicLinkFor()` et `loginWithMagicLink()`. |
| `app/Core/Session.php` | `start()` et `destroy()` passent par `NativeSession`. Les flashs ne changent pas. |
| `app/Controllers/Controller.php` | Ajout de `authorize($permission)`. |
| `app/Controllers/AuthController.php` | Hash à l'inscription, message d'échec unique, `SafeRedirect` sur la page demandée avant connexion. Tom y a ajouté ses logs de login. |
| `app/Controllers/AccountController.php` | Mot de passe actuel vérifié avec `password_verify` **avant** d'enregistrer quoi que ce soit, nouveau hash, nouvelle session. Propriété des commandes, lien de suivi. |
| `app/Controllers/CheckoutController.php` | `order.create` pour commander. Propriété sur `/commande/confirmation/{id}` (IDOR absent du README de Louis). Tom a remplacé la création de commande par son `OrderPlacement` (transaction). E-mail de confirmation avec le lien de suivi signé. |
| `app/Controllers/ReviewController.php` | `review.create`. |
| `app/Controllers/Admin/*.php` | Une permission par action. Un admin ne peut pas changer son propre rôle. |
| `app/Controllers/TrackingController.php` | **Nouveau** : page de suivi par lien signé. |
| `public/index.php` | `APP_KEY` obligatoire, vérification CSRF avant le routeur. Tom a ensuite remplacé la gestion d'erreurs par son `ExceptionHandler` et ajouté le `Throttle` juste avant ma ligne CSRF. |
| `app/helpers.php` | `csrf_field()`. |
| `routes.php` | `GET /suivi/commandes/{id}`, les 4 routes des magic links, `POST /dev/mails/vider`. |
| `views/**` | `{!! csrf_field() !!}` dans les formulaires POST ; header : `Auth::can('admin.dashboard')` ; lien de suivi sur la page commande ; `views/tracking/show.php` (**nouveau**) ; lien « Recevoir un lien de connexion » sur `views/auth/login.php` ; widget inclus dans `views/layouts/app.php` (hors production). |
| `.gitignore` | `storage/mails/` et `storage/used-tokens/`. |
| `database/seed.sql` | Mots de passe de démo hachés en Argon2id (toujours `Admin123!` / `Client123!`). |
| `.env.example`, `compose.yml`, `Makefile` | `APP_KEY` : variable, passage au conteneur, génération par `make app_key` (appelée par `make setup`). |

### Les tests `src/tests/Auth/`

| Fichier | Ce qu'il prouve |
|---|---|
| `run.php`, `TestCase.php` | Mini-framework de test sans dépendance. Un warning fait échouer le test. |
| `Support/Fakes.php` | Faux utilisateurs, commandes, session et magasin de jetons en mémoire. |
| `PasswordHasherTest` (5) | Argon2id, sel aléatoire, rehash d'un ancien bcrypt. |
| `AuthenticatorTest` (9) | Régénération de l'ID et du token CSRF, email inconnu aussi lent qu'un mauvais mot de passe, logout, compte supprimé. |
| `NativeSessionHttpTest` (5) + `fixtures/session_app.php` | Vrai serveur `php -S` : attributs du cookie, strict mode, ID changé au login, logout, timeout. |
| `CsrfTokenManagerTest` (8) | GET passe, POST/PUT/PATCH/DELETE sans token, avec un faux token ou un token en tableau → 403. |
| `GateTest` (11) | Permissions client/admin, refus par défaut, 401/403/404. |
| `BridgeTest` (3) | Conversion des lignes SQL, propriété via `user_id`. |
| `UrlSignerTest` (10) | Paramètre, chemin ou expiration modifié → refusé ; lien expiré ; autre clé ; clé trop courte. |
| `MagicLinkTest` (6) | Bon utilisateur connecté, usage unique, expiration, id d'utilisateur modifié dans le lien, URL signée d'une autre page, compte supprimé. |
| `SafeRedirectTest` (3) | Les redirections vers un autre site sont refusées. |
| `PathTraversalTest` (3) | Fiche normale → 200, attaques → 404, `../../caddy-classic-100l.txt` ramené à la bonne fiche. |
| `FileUsedTokenStoreTest` (4) | Un jeton ne sert qu'une fois, le nom du fichier ne contient pas le jeton, les jetons expirés sont oubliés, un vrai magic link refusé au second usage. |
| `DevMailboxTest` (4) | Le plus récent en premier, stockage en JSON, 50 e-mails gardés au maximum, vider la boîte. |

## 4. En plus : path traversal sur `/documents/{fichier}`

C'est un point de la Partie 4 qui m'est attribué. Il est fusionné dans `main` (PR #5).

**Avant** : `/documents/..%2F..%2Fcomposer.json` renvoyait le `composer.json` du projet, et
`/documents/..%2F..%2F..%2F.env.example` le fichier de config.

**Maintenant**, `DocumentController::safePath()` :
1. Un octet nul (`\0`) dans le nom → refusé (sinon `realpath()` lève une erreur).
2. `basename()` : on ne garde que le nom du fichier, `../../x.txt` devient `x.txt`.
3. `realpath()` du dossier et du fichier : résout `..`, `.` et les liens symboliques, renvoie `false` si
   le fichier n'existe pas.
4. Le chemin obtenu doit commencer par `dossier + séparateur` et être un fichier (`is_file`).
   - Le séparateur évite qu'un dossier voisin (`documents-secret/`) passe le test.
   - `..` reste `..` après `basename`, mais `realpath` le transforme en dossier parent, qui ne commence
     pas par `documents/` : refusé.

Dans tous les cas de refus : 404, sans dire pourquoi. Le `MediaController` d'Erwan (images uploadées)
était déjà sûr : le nom est imposé par une regex stricte.

## 5. Ce que j'utilise des autres parties

**Partie 1 (Louis)** :
- `HttpException` : mes exceptions en héritent, donc elles deviennent des réponses 401/403/404 sans code en plus.
- `Request::input()` : refuse les tableaux, donc un `_csrf[]=` donne 400.
- `Validator` : email et mot de passe validés avant `Auth::attempt()` et à l'inscription.
- `routeId()` : id strict avant le contrôle de propriété.
- Templates `{{ }}` / `{!! !!}` : `csrf_field()` est la sortie brute explicite.
- `require_env()` et `env()` (pour `APP_KEY`), le routeur et `routes.php`.

**Partie 2 (Benjamin)** :
- `UserRepository` : `find`, `findByEmail`, `create`, `updatePassword`, via `RepositoryUserProvider`.
- `OrderRepository::find` pour la propriété.
- ⚠ Ces requêtes sont toujours concaténées sur `main` (vérifié le 04/11) : tant qu'elles ne sont pas
  préparées, la recherche par email du login reste injectable. C'est sa partie, mais elle est prioritaire.

**Partie 4** : rien n'est utilisé. Je ne pose que `Referrer-Policy` sur la page de suivi, à cause de la
signature dans l'URL. Le path traversal de cette partie m'est attribué (voir §4).

**Partie 5 (Tom)** : c'est surtout lui qui s'appuie sur ma partie.
- `ExceptionHandler` reconnaît mes exceptions et les logge : `csrf.invalid`, `signature.invalid`,
  `access.denied`, avec leur `reason`.
- `Throttle` utilise `Auth::check()` et `Auth::id()` : remise à zéro du compteur après un login réussi,
  limite de l'API par utilisateur.
- `Logger` masque `password`, `_csrf`, `signature`, `session`, `cookie`, y compris dans l'URL : un lien de
  suivi refusé apparaît avec `signature=[MASQUÉ]` (vérifié dans les logs du scénario de bout en bout).
- `zend.exception_ignore_args` + mes `#[\SensitiveParameter]` : le mot de passe n'apparaît jamais dans une
  trace d'erreur.
- J'utilise son `RateLimiter` (avec son `Cache`) et son `Logger` pour limiter les demandes de magic link,
  sans toucher à son `Throttle`.
- Ses en-têtes de sécurité (PR #7) s'ajoutent à ceux de mes pages sans les remplacer : la page de suivi
  garde `Referrer-Policy: no-referrer`. Le widget n'a aucun script, donc rien à faire pour sa CSP.

## 6. À transmettre (état au 04/11)

- **Benjamin** :
  - à faire : requêtes préparées dans `UserRepository` et `OrderRepository` ; `findByCredentials()` peut
    être supprimée ;
  - pour ses correctifs front et back : j'ai ajouté quelques lignes dans `routes.php`,
    `views/auth/login.php`, `views/layouts/app.php` et `CheckoutController` (e-mail de commande).
- **Louis** :
  - si son `.env` existe déjà, il doit lancer `make app_key` ;
  - upload (Partie 4) : un fichier plus gros que `post_max_size` arrive sans `$_POST`, donc sans token :
    c'est un 403 CSRF.
- **Tom** : ✅ fait. Rate limiting sur `/connexion` et `/inscription`, logs des échecs de login et de mes
  exceptions, secrets masqués, en-têtes de sécurité avec CSP stricte (PR #7).
- **Remarque** : l'inscription répond « Un compte existe déjà avec cette adresse », ce qui révèle quels
  emails sont inscrits. Le sujet ne l'exige que pour le login ; à discuter.

## 7. Démo pour la soutenance

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
| Traces | Après un CSRF ou un lien de suivi modifié : `storage/logs/security-*.log` contient `csrf.invalid` / `signature.invalid`, avec `signature=[MASQUÉ]` |
| Path traversal | `/documents/..%2F..%2Fcomposer.json` → 404 ; `/documents/caddy-classic-100l.txt` → téléchargement |
| Magic link | `/connexion` → « Recevoir un lien de connexion par e-mail » → enveloppe en bas à droite → « Se connecter » ; le même lien une seconde fois → 403 |
| Lien de suivi par e-mail | Passer une commande → enveloppe → « Suivre ma commande », marche sans compte |

Le déroulé complet, étape par étape, est dans la page de révision : section « Scénarios de test ».

## 8. Questions qu'on peut me poser

- **Pourquoi Argon2id ?** Il est lent et gourmand en mémoire exprès : un attaquant avec des GPU teste
  beaucoup moins de mots de passe par seconde qu'avec MD5 ou SHA. Le sel aléatoire est inclus dans le hash.
  `password_needs_rehash` met à jour un ancien hash au login suivant.
- **Pourquoi hacher quand l'email n'existe pas ?** Sinon la réponse est instantanée pour un email inconnu
  et prend ~50 ms pour un email connu : le temps révélerait quels comptes existent.
- **Pourquoi régénérer l'ID au login ?** Contre la fixation de session : l'attaquant fait utiliser à la
  victime un ID qu'il connaît, elle se connecte, il est connecté avec elle. Avec un nouvel ID, l'ancien ne
  vaut plus rien. `use_strict_mode` refuse en plus un ID inventé.
- **Pourquoi `__Host-`, HttpOnly, Secure, SameSite=Lax ?**
  - `__Host-` impose HTTPS, `Path=/` et pas de `Domain` : un sous-domaine ne peut pas écraser le cookie.
  - HttpOnly : le JavaScript (donc une XSS) ne peut pas le lire.
  - Secure : il ne passe jamais en HTTP.
  - Lax : il n'est pas envoyé sur un POST venant d'un autre site. Strict casserait l'arrivée depuis un lien
    externe : on apparaîtrait déconnecté.
- **Si SameSite=Lax bloque déjà les POST d'un autre site, pourquoi un token CSRF ?** Défense en
  profondeur (vieux navigateurs, sous-domaines considérés comme le même site), et le sujet l'exige.
- **Pourquoi un token par session et pas par requête ?** Plusieurs onglets et le bouton retour marchent.
  Il est renouvelé au login.
- **Pourquoi `hash_equals` ?** Comparaison en temps constant : `===` s'arrête au premier caractère
  différent, ce qui permet de deviner un secret caractère par caractère en mesurant le temps.
- **Pourquoi 404 et pas 403 sur la commande d'un autre ?** Un 403 confirmerait que la commande existe ; un
  404 ne donne rien à qui essaie les ids un par un.
- **Pourquoi un HMAC et pas juste `sha256(url)` ?** Sans clé, n'importe qui recalcule le hash d'une URL
  modifiée. Le HMAC demande `APP_KEY`, qui ne quitte jamais le serveur.
- **Pourquoi `expires` est dans la partie signée ?** Sinon on pourrait repousser la date à volonté. Ici,
  changer `expires` casse la signature → 403.
- **Pourquoi `Referrer-Policy: no-referrer` sur la page de suivi ?** L'URL contient la signature : sans ça,
  un clic vers un autre site lui enverrait le lien complet dans l'en-tête `Referer`.
- **Pourquoi pas de JWT ?** Notre « jeton » de connexion, c'est l'ID de session : une valeur aléatoire
  sans contenu, qui sert seulement à retrouver la session côté serveur. Un JWT, lui, contient les données
  (id, rôle, expiration) signées, et le serveur ne garde rien. Ici, ce serait moins bien :
  - **Déconnexion** : un JWT reste valable jusqu'à son expiration, même après le logout. Pour le révoquer,
    il faut une liste noire en base, donc revenir à un état côté serveur. Notre `destroy()` invalide la
    session tout de suite.
  - **Changement de rôle** : un rôle écrit dans le JWT reste valable jusqu'à son expiration. Chez nous,
    l'utilisateur est relu en base à chaque requête : un admin rétrogradé perd ses droits immédiatement.
  - **Stockage** : dans `localStorage`, une XSS peut le voler ; dans un cookie HttpOnly, il faut le même
    token CSRF qu'avec une session. On n'y gagne rien.
  - **Pièges propres au JWT** : `alg: none` accepté, confusion d'algorithme (RS256 → HS256), clé faible,
    contenu seulement encodé en base64 (lisible par tous). Plus de surface d'attaque, et une bibliothèque
    en plus.
  - **Contexte** : site rendu côté serveur, un seul domaine, pas d'API publique ni d'application mobile.
    C'est le cas d'usage des sessions, et le sujet demande `session_regenerate_id` et des cookies de session.
  - **On utilise quand même le principe là où il a du sens** : l'URL signée (données + `expires` + HMAC avec
    `APP_KEY`) reprend l'idée d'un JWT HS256, pour un lien de suivi sans compte et à durée limitée. Pour le
    magic link à usage unique, il faut justement une mémoire côté serveur (`storage/used-tokens/`) : un
    jeton sans état ne sait pas se révoquer tout seul.
- **Où est vérifiée la permission ?** Côté serveur, dans le contrôleur (`authorize()`). `Auth::can()` dans
  les vues ne sert qu'à cacher un lien : cacher un lien ne protège rien.
- **Pourquoi le magic link affiche un bouton au lieu de connecter tout de suite ?** Les antivirus de
  messagerie ouvrent les liens des e-mails pour les analyser. Si le GET connectait, il consommerait le lien
  avant l'utilisateur. Le GET vérifie seulement la signature ; le POST du bouton, avec le token CSRF, connecte.
- **La boîte mail de test n'est-elle pas dangereuse ?** Elle montre tous les e-mails, liens de connexion
  compris : en production, ce serait une prise de contrôle de n'importe quel compte. Elle n'existe donc que
  si `APP_ENV` n'est pas `production` : sinon pas de widget, et ses routes répondent 404.
- **Comment garantir qu'un magic link ne sert qu'une fois, même avec deux clics simultanés ?**
  `fopen(…, 'x')` crée le fichier du nonce seulement s'il n'existe pas, en une seule opération du système :
  le second clic échoue forcément. Lire puis écrire en deux temps laisserait passer les deux.
