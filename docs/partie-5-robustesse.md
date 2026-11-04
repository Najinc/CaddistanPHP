# Partie 5 : Robustesse (Tom)

Rate limiting · Logging · Gestionnaire d'exceptions · Transactions · Cache

## Ce qui est fait

| Exigence | Où |
|---|---|
| Rate limiting en fenêtre fixe, par IP et par compte, login + inscription + API, 429 + `Retry-After` | `Core/RateLimiter`, `Core/Throttle` (branché dans `public/index.php`) |
| Logs JSON (une ligne par événement) dans `storage/logs/security-AAAA-MM-JJ.log` et `error-AAAA-MM-JJ.log` | `Core/Logger` |
| Jamais de mot de passe / token / cookie / signature dans les logs, retours à la ligne neutralisés | `Logger::sanitize()`, `Logger::sanitizeUri()` |
| Page générique en prod + référence d'incident, détail uniquement dans les logs, traces sans arguments | `Core/ExceptionHandler` |
| Commande en transaction (stock + paiement) : tout ou rien | `Services/OrderPlacement` |
| Prix et total recalculés côté serveur, sous verrou (`SELECT … FOR UPDATE`) | `Services/OrderPlacement` |
| Cache partagé entre workers (JSON, jamais `unserialize`, clés hachées) | `Core/Cache` (stocke les compteurs du rate limit) |

### Limites appliquées

| Route | Par IP | Par compte |
|---|---|---|
| `POST /connexion` | 20 / 15 min | 5 / 15 min (e-mail saisi, existant ou non) |
| `POST /inscription` | 5 / heure | – |
| `/api/*` | 60 / minute | 60 / minute si connecté |

- On compte **avant** de traiter la requête (incrément atomique avec `flock`) : une rafale de requêtes parallèles ne passe pas entre la vérification et l'incrément.
- Un login réussi remet à zéro le compteur **du compte** (pas celui de l'IP). Un utilisateur déjà connecté ne peut pas remettre à zéro le compteur d'un autre compte.
- L'IP vient de `REMOTE_ADDR` (fixée par Caddy), jamais de `X-Forwarded-For` (falsifiable).
- Le dépassement n'est loggé qu'**une fois par tranche** : 10 000 requêtes d'une attaque ne donnent pas 10 000 lignes de log.
- Limite connue de la fenêtre fixe : à cheval sur deux tranches, on peut passer jusqu'à 2 × max requêtes.

### Événements loggés

| Événement | Niveau | Écrit par |
|---|---|---|
| `auth.login_failed` / `auth.login_succeeded` | warning / info | `AuthController::login()` |
| `rate_limit.exceeded` | warning | `Throttle` |
| `access.denied` (tout 403, et les 404 de propriété de Najib) | warning | `ExceptionHandler` |
| `csrf.invalid`, `signature.invalid` | warning | `ExceptionHandler` (exceptions de Najib) |
| `payment.declined` | info | `OrderPlacement` |
| `exception`, `fatal_error`, `deprecated` | error | `ExceptionHandler` |
| `upload.rejected`, `ssrf.blocked`, `path_traversal.blocked` | warning | **à ajouter par Erwan**, voir plus bas |

Chaque ligne contient : `time`, `level`, `event`, `request_id`, `ip`, `method`, `uri` (paramètres sensibles masqués), `user_id`, `user_agent`, `context`.
Le `request_id` est aussi affiché sur la page 500 : on retrouve la ligne de log exacte à partir de ce que le client voit.

### Commande

Le contrôleur ne transmet que `Cart::raw()` (id produit => quantité). Dans une seule transaction :
verrou des produits → prix relus en base → commande `pending` + lignes + baisse du stock → paiement → `paid` → `COMMIT`.
Si une étape échoue (produit retiré, stock insuffisant, paiement refusé, panne SQL), on fait un `ROLLBACK` et rien ne reste.

Le paiement simulé refuse au-delà de **10 000 €** (`SimulatedPaymentGateway::CEILING_CENTS`), pour la démo du rollback.
Seuls les messages de `OrderException` sont montrés au client. Avant, `catch (RuntimeException)` affichait aussi les `PDOException` (du SQL) dans le message flash.

## En-têtes de sécurité (repris de la Partie 4)

`Core/SecurityHeaders`, ajouté par `Response::send()` à **toutes** les réponses PHP. Les fichiers statiques reçoivent les mêmes en-têtes via Caddy (`docker/caddy/Caddyfile`, en valeurs par défaut `?`, qui n'écrasent pas celles de PHP).

| En-tête | Valeur | Pourquoi |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Le navigateur refuse le HTTP pendant 1 an (pas de SSL stripping). Pas de `preload`, difficile à annuler. |
| `Content-Security-Policy` | `default-src 'self'; script-src 'nonce-…' 'strict-dynamic'; … frame-ancestors 'none'` | Un script injecté (XSS) n'a pas le nonce, il ne s'exécute pas. Ni `unsafe-inline`, ni `unsafe-eval`. |
| `X-Frame-Options` | `DENY` | Anti-clickjacking pour les anciens navigateurs (`frame-ancestors` pour les récents). |
| `X-Content-Type-Options` | `nosniff` | Le navigateur ne devine pas le type : un upload ne peut pas devenir du HTML ou du JS. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Les sites tiers ne reçoivent que l'origine, jamais le chemin ni les tokens. `no-referrer` casserait `Controller::back()`. |

- Le nonce fait 128 bits aléatoires et change à chaque requête (`SecurityHeaders::nonce()`). `Vite::vite()` le met sur chaque `<script>` et `<link rel="modulepreload">`, ainsi que dans `<meta property="csp-nonce">`, que le client Vite lit en dev pour ses `<style>`.
- Toute nouvelle balise `<script>` dans une vue doit porter `nonce="{{ \App\Core\SecurityHeaders::nonce() }}"`, sinon elle est bloquée. De même, aucun `onclick=` ni `style=` inline.
- Une réponse peut durcir un en-tête avec `withHeader()` (ex : `default-src 'none'; sandbox` pour un fichier servi).

## Tester

```sh
docker compose exec app php tests/Robustness/run.php     # 36 tests
```

`OrderPlacementTest` utilise la vraie base, puis remet le stock et supprime ses commandes à la fin.

Démo pour la soutenance :

```sh
# brute force : 5 × 302, puis 429 avec Retry-After, même avec le bon mot de passe
for i in $(seq 1 7); do curl -sk -o /dev/null -w "%{http_code}\n" -d "email=client@caddistan.local&password=faux" https://caddistan.local/connexion; done

# injection de log : le \n apparaît en \x0A, sur la même ligne
tail -f src/storage/logs/security-$(date +%F).log
```

Rollback : mettre 40 × « Caddy Classic » + 14 × « Caddy Maxi » au panier (11 466 €), puis commander. On obtient « Paiement refusé », aucune commande n'est créée et le stock ne bouge pas.

## Ce que j'attends des autres parties

**Erwan (Partie 4)** : une ligne par refus, sans jamais logger le contenu du fichier :

```php
use App\Core\Logger;

Logger::security('upload.rejected', ['reason' => 'mime', 'mime' => $mime, 'size' => $size]);
Logger::security('ssrf.blocked', ['url' => $url, 'resolved_ip' => $ip, 'reason' => 'private_ip']);
Logger::security('path_traversal.blocked', ['requested' => $filename]);
```

Les valeurs sont nettoyées par le logger (retours à la ligne, longueur), elles peuvent venir directement de l'attaquant.

**Najib (Partie 3)**
- Garder les deux lignes `Logger::security('auth.login_*')` dans `AuthController::login()` quand tu y brancheras `Authenticator`.
- Le rate limit est déjà autour de `POST /connexion`, donc tu n'as rien à faire de ton côté. Pour le magic link, ajoute la route `POST /connexion/lien` dans `Throttle::limitsFor()`.
- Tes exceptions (`CsrfTokenMismatchException`, `AccessDeniedException`, `InvalidSignatureException`) sont loggées automatiquement avec leur `reason`.
- `Throttle::after()` détecte le login réussi via `Auth::check()` : garde cette façade.
- Le logger lit `$_SESSION['user_id']`. Si tu changes la clé de session, préviens-moi.

**Benjamin (Partie 2)**
- `OrderRepository::create()` n'est plus appelée (remplacée par `OrderPlacement`, déjà en requêtes préparées) : tu peux la supprimer au lieu de la réécrire.
- `PDO::ATTR_EMULATE_PREPARES` est déjà à `false` dans `Database.php`.

**Louis (Partie 1)**
- La gestion d'erreurs de `public/index.php` est passée dans `Core/ExceptionHandler` (même comportement pour les 401, 422 et 400).
- Une seule différence : à la suite d'une 422 sur un formulaire, les champs sensibles (`password*`, `_token`) ne sont plus recopiés dans la session. `Controller::back()` les recopie encore.
- `docker/php/php.ini` garde `display_errors = On` pour le dev, mais `ExceptionHandler` le remet à `Off` dès que l'on n'est pas en debug.
- Prod : `APP_ENV=production`. Le debug est alors impossible, même avec `APP_DEBUG=1`.
- `resources/main.ts` : j'ai seulement ajouté la gestion des réponses non-200 (429). En revanche, la recherche instantanée injecte encore `p.name` en `innerHTML` sans échappement, ce qui relève du XSS, ta partie.
