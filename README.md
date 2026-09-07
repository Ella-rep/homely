# Homely

Application web format mobile pour gérer les tâches ménagères à plusieurs, par foyer.
Backend **Symfony 7 + API Platform**, frontend **Twig + CSS + JS vanilla** (aucune logique métier côté front : calculs de saleté, tri et dates 100% backend).

## Design — inspiré de Tody

- Écran d'accueil : grille de tuiles colorées par pièce, avec un point de statut (vert/vide = à jour, orange = 1-2 tâches à faire, rouge = plusieurs tâches en retard).
- Écran pièce : liste des tâches avec une barre de progression colorée (vert → orange → rouge selon l'échéance) et un libellé ("Dans 3 jours", "Aujourd'hui", "2 jours de retard").
- Bouton "C'est fait ✓" pour valider une tâche (équivalent du "Just Did It" de Tody).
- Ajout de pièces / tâches via une feuille modale (bouton + en haut à droite), avec suggestions issues du catalogue (`RoomCatalog`, `TaskCatalog`).
- Thème clair / sombre (bouton dans l'en-tête, préférence mémorisée dans `localStorage`).

Toute la logique de saleté (`status`, `progressPercent`, `dueLabel`) est calculée dans `src/Service/Dirtiness.php` et exposée en lecture par l'API comme par les pages Twig : le front ne fait qu'afficher ces champs.

## Comptes et foyers

L'accès à l'application nécessite un compte (email + mot de passe). À l'inscription, on choisit :

- **Créer un foyer** : génère un code d'invitation à 6 caractères à partager avec les autres membres.
- **Rejoindre un foyer** existant avec ce code.

Toutes les pièces appartiennent à un foyer ; chaque utilisateur ne voit que les pièces de son propre foyer (`RoomController::denyUnlessSameFoyer`).

## Modèle de données

- **Foyer** : `name`, `inviteCode` (unique) — regroupe des `User` et des `Room`.
- **User** : `email` (identifiant de connexion), `pseudo`, `password` (hashé), `foyer`.
- **Room** (pièce) : `name`, `color`, `position`, `foyer` + champs calculés `status`, `taskCount`, `overdueCount`, `dueSoonCount`.
- **Task** (tâche récurrente) : `name`, `room`, `frequencyDays`, `lastDoneAt` + champs calculés `status`, `progressPercent`, `dueLabel`.
- **TaskLog** (historique) : `task`, `doneAt`, `doneBy`. Supprimé en cascade avec sa tâche (RG4 actée : pas de soft delete/archivage).

## Pages

| Route | Description |
|---|---|
| `GET /login`, `POST /login` | Connexion |
| `GET /register` | Choix créer / rejoindre un foyer |
| `POST /register/creer`, `POST /register/rejoindre` | Création de compte |
| `GET /` | Accueil : grille des pièces du foyer |
| `POST /rooms` | Créer une pièce |
| `GET /rooms/{id}` | Détail d'une pièce, liste des tâches |
| `POST /rooms/{id}/tasks` | Créer une tâche |
| `POST /rooms/{id}/tasks/catalogue` | Ajouter en masse des tâches suggérées (`TaskCatalog`) |
| `POST /tasks/{id}/complete` | Marquer une tâche comme faite |

## API (référence / catalogue, JSON)

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/room-catalog` | Types de pièces suggérés |
| GET | `/api/task-catalog` | Tâches suggérées par pièce |
| GET/POST/PATCH/DELETE | `/api/rooms`, `/api/rooms/{id}` | API Platform (nécessite d'être connecté) |
| GET/POST/PATCH/DELETE | `/api/tasks`, `/api/tasks/{id}` | API Platform (nécessite d'être connecté) |

## Installation (Docker)

```bash
cd homely
cp .env .env.local   # ajuste APP_SECRET, POSTGRES_* si besoin
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:load-demo-data   # foyer + user de démo + 4 pièces / 12 tâches
```

Ouvre `http://127.0.0.1:8000/` (ou le port défini par `APP_PORT`). Identifiants de démo : `demo@homely.app` / `demodemo`.

## Pistes de suite

- Historique visible dans l'UI (l'endpoint `task_logs` existe déjà côté API).
- Notifications de rappel.
- Gestion des membres du foyer (renommer, retirer un compte).
