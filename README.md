# Link Catalog (Zero-Knowledge Storage)

Un catalogue de liens sécurisé construit avec Symfony 8, Vue.js 3 et PostgreSQL. 
Toutes les données sensibles (URLs, titres, descriptions, tags, images) sont cryptées côté serveur en utilisant une approche "Zero-Knowledge" : le serveur ne connaît jamais votre clé de décryptage permanente.

## Fonctionnalités

- 🔐 **Cryptage de bout en bout** : Utilisation d'AES-256-GCM avec PBKDF2.
- 📺 **Scraping automatique** : Récupération automatique du titre, de la description et de l'image de couverture depuis une URL.
- 🏷️ **Gestion des tags** : Organisez vos liens avec des tags (également cryptés).
- 🔍 **Recherche dynamique** : Recherche instantanée par titre ou par tag.
- 👥 **Multi-utilisateurs** : Chaque utilisateur possède son propre espace et ses propres clés de cryptage.

## Stack Technique

- **Backend** : PHP 8.4 / Symfony 8.0
- **Frontend** : Vue.js 3 / Webpack Encore
- **Base de données** : PostgreSQL 18
- **Sécurité** : AES-256-GCM (OpenSSL)
- **Infrastructure** : Docker Compose (Nginx + PHP-FPM + Postgres)

## Installation (Développement)

### Prérequis
- Docker et Docker Compose
- Un certificat SSL local (optionnel pour le dev, mais configuré pour HTTPS)

### Étapes
1. **Cloner le projet**
2. **Lancer les conteneurs** :
   ```bash
   docker compose up -d
   ```
3. **Installer les dépendances PHP** :
   ```bash
   docker compose exec app composer install
   ```
4. **Installer les dépendances JS** :
   ```bash
   docker compose exec app pnpm install
   ```
5. **Initialiser la base de données** :
   ```bash
   docker compose exec app php bin/console doctrine:schema:update --force
   ```
6. **Compiler les assets** :
   ```bash
   docker compose exec app pnpm run dev
   # Ou pour le rechargement à chaud :
   docker compose exec app pnpm run watch
   ```
7. **Accès** : L'application est disponible sur `https://localhost` (ou l'IP configurée).

## Déploiement (Production)

Pour un déploiement en production :

1. **Variables d'environnement** : Configurez `.env.local` avec des valeurs sécurisées :
   - `APP_ENV=prod`
   - `APP_SECRET`: Une clé longue et aléatoire.
   - `DATABASE_URL`: Accès à votre base de données Postgres de prod.
2. **Certificats SSL** : Remplacez les certificats dans `docker/certs/` par des certificats valides (ex: Let's Encrypt).
3. **Optimisation des Assets** :
   ```bash
   docker compose exec app pnpm run build
   ```
4. **Optimisation PHP** :
   Assurez-vous que l'OPcache est activé et que Composer est optimisé :
   ```bash
   docker compose exec app composer install --no-dev --optimize-autoloader
   ```
5. **Sécurité Nginx** : Le fichier `docker/nginx/default.conf` est pré-configuré pour forcer le HTTPS.

## Sécurité & Cryptage

Le système utilise un mot de passe de session distinct du mot de passe de connexion.
1. L'utilisateur se connecte (Authentification Symfony classique).
2. L'utilisateur fournit un "Mot de passe de décryptage".
3. Ce mot de passe est stocké uniquement dans la **session PHP** (mémoire serveur, jamais en base de données).
4. Lors de l'affichage des liens, Symfony utilise ce mot de passe pour dériver une clé (PBKDF2) et décrypter les données à la volée.

Si vous perdez ce mot de passe, les données sont définitivement irrécupérables.

---
Développé avec ❤️ par Junie.
