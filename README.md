# ExpenseManager

Application de gestion de finances personnelles.

## Démo en production

> URL : https://esgi-symfony-2026.fly.dev/

---

## Installation et lancement en local

```bash
# 1. Démarrer les conteneurs
docker compose up -d

# 2. Installer les dépendances PHP
docker compose exec php composer install

# 3. Lancer les migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# 4. Charger les fixtures (données de démo)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

L'application est accessible sur **http://localhost:8089**

---

## Comptes de démonstration

Tous les comptes ont le mot de passe : **`password`**

| Rôle | Email | Accès |
|------|-------|-------|
| Utilisateur | `user@example.com` | Dashboard, portefeuilles, transactions, budgets |
| Conseiller | `advisor@example.com` | Dashboard clients, lecture des portefeuilles clients |
| Administrateur | `admin@example.com` | Accès complet + interface d'administration |

> Le compte conseiller (`advisor@example.com`) a déjà un accès actif au compte de `user@example.com`.

---

## Lancer les tests

```bash
# Créer la base de données de test et appliquer les migrations
docker compose exec php php bin/console doctrine:database:create --env=test
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Lancer la suite de tests
docker compose exec php php vendor/bin/phpunit
```

---

## Stack technique

- **Backend** : Symfony 8.1, Doctrine ORM, PostgreSQL 16
- **Frontend** : AssetMapper, Tailwind CSS v4, Stimulus, Turbo
- **Async** : Symfony Messenger + Scheduler (transactions récurrentes)
- **Infra** : Docker Compose (dev), Fly.io (prod)
