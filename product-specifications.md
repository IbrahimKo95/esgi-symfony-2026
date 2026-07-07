# Cahier des Charges
## Gestionnaire de Dépenses Personnelles avec Détection d'Anomalies

**Version 1.1**

---

## 1. Présentation du projet

### 1.1 Contexte

La gestion des finances personnelles est une tâche répétitive que la majorité des utilisateurs délaisse faute d'outils simples et pertinents. Les applications existantes (tableurs, apps bancaires) se contentent le plus souvent d'un historique brut de transactions, sans jamais alerter l'utilisateur lorsqu'un comportement de dépense devient anormal.

Ce projet propose une application web développée avec Symfony permettant à un utilisateur de suivre ses dépenses et revenus, de définir des budgets par catégorie, et surtout de **détecter automatiquement des anomalies de dépenses** grâce à une analyse statistique de son historique.

### 1.2 Objectifs du projet

- Fournir une application de suivi de finances personnelles complète (CRUD dépenses/revenus, catégories, budgets).
- Sécuriser l'accès selon une hiérarchie de rôles (utilisateur standard, conseiller financier, administrateur).
- Implémenter un moteur de détection d'anomalies capable d'identifier :
    1. une dépense ponctuelle anormalement élevée par rapport à l'historique de l'utilisateur ;
    2. une hausse inhabituelle des dépenses sur une catégorie donnée (ex. "+65% sur Restaurant vs moyenne 3 mois") ;
    3. un risque de dépassement du budget mensuel défini par catégorie.
- Restituer ces informations via un dashboard graphique et des notifications (application + e-mail).
- Respecter l'intégralité des exigences techniques obligatoires du syllabus Symfony (hors points bonus).

---

## 2. Description fonctionnelle

### 2.1 Fonctionnalités principales

| Domaine | Fonctionnalités |
|---|---|
| **Comptes & Auth** | Inscription, connexion/déconnexion, hachage des mots de passe, gestion de profil |
| **Portefeuilles (Wallets)** | Création de comptes/portefeuilles (courant, épargne, espèces) ; portefeuilles collaboratifs ouverts à tout utilisateur |
| **Transactions** | Ajout/édition/suppression de dépenses et revenus, catégorisation, tags, pièces jointes (notes) |
| **Catégories** | Gestion des catégories de dépenses (par défaut + personnalisées) |
| **Budgets** | Définition d'un budget mensuel par catégorie et par portefeuille, suivi de consommation |
| **Détection d'anomalies** | Analyse automatique post-ajout d'une transaction, génération d'alertes |
| **Dashboard** | Graphiques (répartition par catégorie, évolution mensuelle, alertes actives) |
| **Conseil financier** | Un utilisateur peut inviter un conseiller (`ROLE_ADVISOR`) à consulter son dashboard en lecture seule |
| **Notifications** | Notifications in-app + e-mail transactionnel (Mailer) lors d'une anomalie détectée |
| **Administration** | Interface dédiée : gestion des utilisateurs, catégories globales, supervision des anomalies, logs d'audit |
| **API** | Endpoint `/api/v1/...` exposant les transactions et anomalies au format JSON (Serializer + groupes) |
| **Conversion de devises** | Consommation d'une API externe de taux de change pour les portefeuilles en devise étrangère |

### 2.2 Algorithme de détection d'anomalies (résumé fonctionnel)

Le service de détection s'exécute à chaque création de transaction et applique trois règles indépendantes :

1. **Anomalie ponctuelle** : la dépense dépasse *moyenne + 2 écarts-types* des dépenses de l'utilisateur sur les 3 derniers mois.
2. **Anomalie de catégorie** : le total mensuel dépensé dans une catégorie dépasse la moyenne des 3 derniers mois d'un seuil paramétrable (ex. +50%).
3. **Risque de dépassement de budget** : projection linéaire de la dépense actuelle sur le mois complet, comparée au budget défini pour la catégorie.

Chaque anomalie détectée crée une entité `Anomaly`, déclenche une `Notification` in-app, et un e-mail asynchrone via Mailer.

---

## 3. Rôles utilisateurs

| Rôle | Description | Accès |
|---|---|---|
| **Visiteur** | Utilisateur non authentifié | Page d'accueil, inscription, connexion |
| **ROLE_USER** | Utilisateur standard | Gestion de ses propres portefeuilles (personnels **et collaboratifs**), transactions, catégories personnelles, budgets ; consultation de son dashboard et de ses anomalies ; invitation de membres sur un portefeuille dont il est propriétaire |
| **ROLE_ADVISOR** | Conseiller financier | Aucun accès en écriture sur les transactions. Peut être invité en lecture seule par un ou plusieurs clients (`ROLE_USER`) pour consulter leur dashboard et leurs anomalies détectées, et y laisser un commentaire/conseil |
| **ROLE_ADMIN** | Administrateur de la plateforme | Interface d'administration : gestion globale des utilisateurs et catégories, supervision de toutes les anomalies détectées, consultation des logs d'audit, statistiques globales |

La hiérarchie globale est cloisonnée via le `Security Component` de Symfony (contrôle d'accès par attribut `#[IsGranted]` sur les routes) et affinée par deux Voters personnalisés :
- **`TransactionVoter`** : un utilisateur peut modifier/supprimer une transaction uniquement s'il en est l'auteur, ou s'il est propriétaire/contributeur du portefeuille concerné (via `WalletMember`) — un lecteur (`viewer`) ne peut que consulter.
- **`AdvisorAccessVoter`** : un `ROLE_ADVISOR` ne peut consulter (jamais modifier) les données d'un client que si un accès actif existe (entité `AdvisorAccess`) ; tout accès révoqué coupe immédiatement la lecture.

---

## 4. Cas d'utilisation (Use Cases)

### 4.1 Visiteur

| ID | Cas d'utilisation | Description |
|---|---|---|
| UC-01 | S'inscrire | Créer un compte utilisateur avec e-mail/mot de passe |
| UC-02 | Se connecter | Authentification via formulaire de connexion natif Symfony |

### 4.2 Utilisateur (ROLE_USER)

| ID | Cas d'utilisation | Description |
|---|---|---|
| UC-03 | Créer un portefeuille | Ajouter un compte/wallet (courant, épargne, espèces, devise), personnel ou collaboratif |
| UC-04 | Ajouter une transaction | Enregistrer une dépense ou un revenu (montant, catégorie, date, tags, note) |
| UC-05 | Modifier/supprimer une transaction | Édition limitée aux transactions dont il est l'auteur, ou dont il est propriétaire/contributeur du portefeuille (Voter) |
| UC-06 | Gérer ses catégories | Créer des catégories personnalisées en plus des catégories par défaut |
| UC-07 | Définir un budget mensuel | Fixer un montant plafond par catégorie et par portefeuille |
| UC-08 | Consulter le dashboard | Visualiser graphiques de répartition et d'évolution des dépenses |
| UC-09 | Recevoir une alerte d'anomalie | Être notifié (in-app + e-mail) en cas de dépense/dépassement anormal |
| UC-10 | Consulter l'historique d'anomalies | Lister les anomalies détectées sur son compte |
| UC-11 | Taguer une transaction | Associer un ou plusieurs tags libres à une transaction |
| UC-12 | Commenter une transaction | Ajouter une note contextuelle à une dépense |
| UC-13 | Inviter un membre sur un portefeuille | En tant que propriétaire d'un wallet, ajouter un autre utilisateur avec un rôle contextuel (contributeur/lecteur) |
| UC-14 | Consulter la vue consolidée d'un portefeuille partagé | Voir l'agrégat des dépenses de tous les membres d'un wallet collaboratif |
| UC-15 | Inviter un conseiller financier | Accorder à un compte `ROLE_ADVISOR` un accès en lecture seule à son dashboard et ses anomalies (et le révoquer à tout moment) |

### 4.3 Conseiller financier (ROLE_ADVISOR)

| ID | Cas d'utilisation | Description |
|---|---|---|
| UC-16 | Accepter une invitation client | Valider un accès en lecture accordé par un utilisateur (`AdvisorAccess`) |
| UC-17 | Consulter le portefeuille de clients | Lister l'ensemble des clients lui ayant accordé un accès |
| UC-18 | Consulter le dashboard d'un client | Vue lecture seule des transactions, budgets et anomalies d'un client — aucune action de modification possible (bloqué par `AdvisorAccessVoter`) |
| UC-19 | Laisser un conseil sur une anomalie | Ajouter un commentaire sur une anomalie détectée chez un client, sans modifier la transaction elle-même |

### 4.4 Administrateur (ROLE_ADMIN)

| ID | Cas d'utilisation | Description |
|---|---|---|
| UC-20 | Gérer les utilisateurs | Lister, activer/désactiver, modifier les rôles des comptes |
| UC-21 | Gérer les catégories globales | CRUD sur les catégories par défaut proposées à tous les utilisateurs |
| UC-22 | Superviser les anomalies | Vue globale de toutes les anomalies détectées sur la plateforme |
| UC-23 | Consulter les statistiques globales | Nombre d'utilisateurs actifs, volume de transactions, taux d'anomalies |
| UC-24 | Consulter les logs d'audit | Historique des actions sensibles (suppression, changement de rôle...) |

---

## 5. Aperçu de l'architecture des données

### 5.1 Entités (15 — minimum requis : 10)

1. `User`
2. `Wallet`
3. `WalletMember` *(entité de liaison User ↔ Wallet, porte l'attribut `role`)*
4. `Category`
5. `Transaction` *(entité parente — héritage Doctrine)*
6. `Expense` *(sous-type de `Transaction`)*
7. `Income` *(sous-type de `Transaction`)*
8. `Budget`
9. `Anomaly`
10. `Notification`
11. `Tag`
12. `RecurringTransaction`
13. `Comment`
14. `AuditLog`
15. `AdvisorAccess` *(entité de liaison User "client" ↔ User "conseiller", porte les attributs `status`, `grantedAt`)*

### 5.2 Héritage Doctrine

`Transaction` est l'entité parente (Single Table Inheritance), factorisant les champs communs (`amount`, `date`, `description`, `category`, `wallet`, `author`). `Expense` et `Income` en héritent et ajoutent leurs spécificités (ex. `isRecurring` côté `Expense`).

### 5.3 Relations principales

**ManyToMany (2 minimum — ici 3) :**
- `Transaction` ↔ `Tag`
- `User` ↔ `Wallet` via `WalletMember` (attribut `role` : propriétaire / contributeur / lecteur) — accessible à **tout** `ROLE_USER`
- `User` ↔ `User` (self-référencée, client ↔ conseiller) via `AdvisorAccess` (attributs `status`, `grantedAt`)

**OneToMany / ManyToOne (8 minimum — ici 14) :**
- `User` → `Wallet` (créateur)
- `Wallet` → `Transaction`
- `Wallet` → `WalletMember`
- `User` → `WalletMember`
- `Category` → `Transaction`
- `Category` → `Budget`
- `User` → `Budget`
- `User` → `Notification`
- `User` → `Anomaly`
- `Transaction` → `Comment`
- `User` → `Comment` (auteur)
- `User` → `AuditLog`
- `User` (client) → `AdvisorAccess`
- `User` (conseiller) → `AdvisorAccess`

---

## 6. Sécurité & droits d'accès

- Authentification via `Security Component` Symfony, mots de passe hachés (`PasswordHasher`), formulaire de connexion/déconnexion natif.
- 3 rôles cloisonnés : `ROLE_USER`, `ROLE_ADVISOR`, `ROLE_ADMIN`.
- **`TransactionVoter`** : un utilisateur peut modifier/supprimer une transaction uniquement s'il en est l'auteur, ou s'il est propriétaire/contributeur du portefeuille concerné (via `WalletMember`).
- **`AdvisorAccessVoter`** : un conseiller ne peut consulter les données d'un client qu'avec un accès `AdvisorAccess` actif, sans jamais pouvoir modifier quoi que ce soit.

---

## 7. API & communications

- **Endpoint API dédié** : `/api/v1/transactions`, `/api/v1/anomalies` — retour JSON via le Serializer Symfony, avec groupes de normalisation (`transaction:read`) et de dénormalisation (`transaction:write`) distincts selon le contexte d'appel.
- **Mailer** : envoi d'e-mails transactionnels asynchrones (confirmation d'inscription, alerte d'anomalie détectée).
- **API externe consommée** : API de taux de change (ex. exchangerate.host / Frankfurter API) via `HttpClient`, pour convertir automatiquement les montants des portefeuilles en devise étrangère vers la devise de référence de l'utilisateur.

---

## 8. Fonctionnalités avancées & qualité de code

- **Interface d'administration** dédiée et sécurisée (Twig sur mesure ou EasyAdminBundle) pour la gestion globale.
- **Formulaires dynamiques** : le formulaire de création de transaction utilise des Form Events (`PRE_SET_DATA`, `PRE_SUBMIT`) pour adapter dynamiquement les champs disponibles selon le type sélectionné (Expense vs Income) ou le portefeuille choisi (devise associée).
- **Repositories avancés** : méthodes `QueryBuilder` dédiées (ex. calcul de moyenne glissante sur 3 mois par catégorie, agrégation des dépenses par mois avec jointures pour éviter le N+1).
- **Interface Twig** : minimum 10 pages distinctes (accueil, inscription, connexion, dashboard, liste transactions, formulaire transaction, catégories, budgets, anomalies, portefeuilles, profil, administration...), avec héritage de templates et filtres Twig personnalisés (ex. formatage de devise).

---

## 9. Tests & CI/CD

- Au moins **1 test unitaire** (ex. `AnomalyDetectionService`) et **1 test fonctionnel** (`WebTestCase` — scénario de connexion ou soumission de formulaire de transaction).
- **Pipeline CI** (GitHub Actions) déclenchée à chaque push : lint Symfony (config, container, templates Twig), analyse statique PHPStan (niveau 5 minimum), exécution de la suite de tests.
- **Déploiement** : application hébergée et accessible en ligne (ex. Render, Platform.sh ou VPS).

---

## 10. Exigences non-fonctionnelles

- **Sécurité** : validation des entrées, protection CSRF native Symfony, hachage des mots de passe.
- **Performance** : requêtes optimisées (jointures explicites, évitement du N+1).
- **Ergonomie** : interface responsive, retours utilisateurs clairs (flash messages, validation de formulaires).
- **Maintenabilité** : respect des conventions Symfony (architecture MVC, services injectés, séparation des responsabilités).
