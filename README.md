# Template sécurité web

Ce dépôt contient tout le nécessaire pour faire tourner un serveur PHP 8.5, avec Vite et un système de Hot Reload personnalisé. Libre à vous de remplacer les composants par ceux de votre choix !

## A lire

- Les ports 80 et 443 doivent être dispo pour le reverse proxy Caddy
- Un script d'installation est fourni (voir Configuration), mais si Docker tourne sur un hôte différent (VM par exemple), l'installation du certificat SSL devra être réalisée manuellement.
- **Aucun hardening n'est effectué par défaut ! A vous de jouer.** 


| Service | Port |
|---|---|
| PHP-FPM | 9000 (interne) |
| Caddy | 80 et 443 |
| Vite | 5173 |

Le service Vite est uniquement actif lorsque le container est lancé en profil dev (--profile dev).

# Configuration

## Démarrage du projet

```sh
make setup
```

Cette commande se charge de démarrer les containers, copier et installer le certificat SSL sur l'hôte, installer les dépendances et build l'application.

## DNS

Editez votre fichier hosts pour faire pointer votre domaine vers l'IP de votre hôte/VM :
- `/etc/hosts` sous Linux
- `C:\Windows\System32\drivers\etc\hosts` sous Windows

Exemple : 

```sh
secuweb.local   localhost
```