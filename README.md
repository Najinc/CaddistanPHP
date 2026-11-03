# Caddistan

Boutique en ligne de caddies : supermarché, golf, logistique, marché, jardin…
Projet de sécurité web (M2) en **PHP 8.5 natif, sans framework**.

> ⚠️ **Base volontairement non sécurisée.** Le code ne contient encore aucune protection : SQL concaténé, mots de passe en clair, aucun échappement HTML, pas de CSRF, pas de contrôle d'accès sur l'admin… L'objectif du projet est d'implémenter ces protections à la main (voir [Sécurité à implémenter](#sécurité-à-implémenter)). À ne jamais exposer sur Internet.

## Stack

| Service | Rôle | Port |
|---|---|---|
| `caddy` | Reverse proxy HTTPS (certificat local) | 80 / 443 |
| `app` | PHP-FPM 8.5 | 9000 (interne) |
| `db` | MariaDB 11 | 3306 (interne) |
| `vite` | Serveur de dev + HMR (profil `dev`) | 5173 |

Front : Vite + Tailwind CSS 4 + TypeScript (aucun framework JS).

## Démarrage

```sh
make setup      # copie .env, démarre les conteneurs, installe le certificat, les dépendances, et build
```

Le site est ensuite accessible sur **https://caddistan.local**.

- Les ports 80/443 doivent être libres : arrêtez d'abord le conteneur du template s'il tourne encore (`docker stop secuweb-caddy`).
- `make install_cert` ajoute le certificat racine de Caddy au trousseau (macOS) ou au magasin système (Linux), et `make install_host` ajoute `caddistan.local` au fichier hosts : les deux demandent votre mot de passe.
- Si les conteneurs tournent déjà, `make install_cert install_host` suffit.

Commandes utiles :

```sh
make dev        # démarre tout (avec Vite)
make stop       # arrête tout
make sh         # shell dans le conteneur PHP
make db_shell   # console MariaDB
make db_reset   # recrée la base depuis src/database/schema.sql + seed.sql
```

### Comptes de démo

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | `admin@caddistan.local` | `Admin123!` |
| Client | `client@caddistan.local` | `Client123!` |

## Architecture

```
src/
├── app/
│   ├── Core/            # Mini-framework maison
│   │   ├── Router.php         routes GET/POST + paramètres {slug}
│   │   ├── Request.php        accès à $_GET/$_POST/$_FILES/$_SERVER
│   │   ├── Response.php       réponses HTML / JSON / redirection / téléchargement
│   │   ├── Database.php       connexion PDO
│   │   ├── View.php           rendu des vues PHP + layout
│   │   ├── Session.php        session, messages flash, anciennes saisies
│   │   ├── Auth.php           utilisateur connecté
│   │   └── HttpException.php  erreurs 404/403/401…
│   ├── Controllers/     # Boutique, compte, panier, commande, Api/, Admin/
│   ├── Repositories/    # Requêtes SQL par table
│   ├── Services/        # Panier (session), images produits (upload / URL)
│   ├── helpers.php      # env(), money(), partial(), slugify()…
│   └── Vite.php
├── database/            # schema.sql + seed.sql (chargés à la création du volume)
├── public/              # Racine web : index.php (front controller), images, uploads
├── resources/           # main.ts + style.css (Vite)
├── storage/             # documents (fiches techniques), logs
├── views/               # Templates PHP
└── routes.php           # Déclaration de toutes les routes
```

Cycle d'une requête : `public/index.php` → `Router::dispatch()` → `Controller::méthode()` → `Response::send()`.

## Fonctionnalités

- **Catalogue** : catégories, recherche, tri, pagination, fiche produit, produits similaires
- **Avis clients** : note + commentaire (connecté)
- **Fiches techniques** téléchargeables (`/documents/{fichier}`)
- **Panier** en session (ajout AJAX depuis les cartes produit)
- **Commande** : adresse de livraison, paiement simulé, décrément du stock
- **Compte** : inscription, connexion, profil, mot de passe, historique des commandes
- **API JSON** : `/api/produits?q=` (recherche instantanée), `/api/panier`
- **Admin** (`/admin`) : tableau de bord, CRUD produits (upload d'image ou import par URL), commandes et statuts, rôles des utilisateurs

## Sécurité à implémenter

Checklist du sujet, avec les endroits du code concernés :

| Fonctionnalité | Où ça se passe |
|---|---|
| Authentification sécurisée (régénération d'id…) | `Core/Auth.php`, `AuthController` |
| Stockage des mots de passe (argon2id / bcrypt) | colonne `users.password` (en clair), `UserRepository` |
| Cookies sécurisés | `Core/Session.php` |
| En-têtes HTTP (HSTS, CSP, X-Frame-Options, nosniff…) | `Core/Response.php`, `docker/caddy/Caddyfile` |
| Injection SQL (requêtes préparées) | `Repositories/*` (requêtes concaténées) |
| Réponses HTTP et JSON | `Core/Response.php`, `Controllers/Api/` |
| Token CSRF | tous les formulaires `POST` |
| Anti-SSRF | `ProductImageService::storeFromUrl()` (import d'image par URL) |
| Autorisations (rôles, permissions) | `Controllers/Admin/*` (aucun contrôle), `/compte/commandes/{id}` (IDOR) |
| Anti Path Traversal | `DocumentController::download()` |
| Upload sécurisé (taille, MIME, whitelist) | `ProductImageService::storeUpload()` |
| Templating / filtrage XSS | `views/*` (aucun échappement), recherche instantanée dans `main.ts` |
| Rate limiting (fenêtre fixe) | `/connexion`, `/inscription`, API |
| Logging | `storage/logs/`, `public/index.php` |
| Validation des données | contrôleurs (aucune validation) |

Bonus possibles : ORM, model binding, signed routes, middlewares, migrations/seeding, lecture du `.env`, gestionnaire d'exceptions, injection de dépendances.
