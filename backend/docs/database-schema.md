# Schema Base de Donnees - Lagedi

## Enums

### Gender (`App\Enums\Gender`)
Type de clientele pour les companies et services.
- `men` - Hommes uniquement (ex: barbershop)
- `women` - Femmes uniquement
- `both` - Mixte

### AppointmentStatus (`App\Enums\AppointmentStatus`)
Cycle de vie d'un rendez-vous.
- `pending` - RDV demande, en attente de confirmation
- `confirmed` - RDV confirme par le salon
- `cancelled` - RDV annule (par le client ou le salon)
- `completed` - RDV termine

### DayOfWeek (`App\Enums\DayOfWeek`)
Jours de la semaine pour les horaires.
- `0` Monday, `1` Tuesday, `2` Wednesday, `3` Thursday, `4` Friday, `5` Saturday, `6` Sunday

### CompanyRole (`App\Enums\CompanyRole`)
Role d'un utilisateur au sein d'une company.
- `owner` - Proprietaire/createur du salon. Peut tout modifier, ajouter/supprimer des employes
- `employee` - Coiffeur/employe. Voit ses RDV et son planning

---

## Tables et Modeles

### users (Model: `User`)
**Tous les utilisateurs de la plateforme (clients, employes et owners).**
Table d'authentification unique. Un meme utilisateur peut etre client d'un salon et employe/owner d'un autre. Le role dans un salon est defini par la table pivot `company_user`.

| Champ | Description |
|---|---|
| name | Nom de famille |
| first_name | Prenom |
| email | Email (unique, pour login) |
| password | Mot de passe hashe |
| phone | Numero de telephone |
| api_token | Token d'authentification API |

**Relations:** belongsToMany `companies` (via company_user), hasMany `companyUsers`, `appointments`

---

### companies (Model: `Company`)
**Les salons de coiffure.**
Chaque company represente un salon physique avec son adresse. Le champ `gender` definit le type de clientele : un barbershop sera `men`, un salon femme sera `women`, un salon mixte sera `both`. Les coordonnees GPS (`location`) permettent la recherche par proximite avec `ST_Distance_Sphere`.

| Champ | Description |
|---|---|
| name | Nom du salon |
| description | Description libre du salon |
| phone | Telephone du salon |
| email | Email de contact |
| address | Adresse (rue, numero) |
| city | Ville |
| postal_code | Code postal |
| country | Pays (defaut: France) |
| gender | Type de clientele: `men`, `women`, `both` |
| location | Coordonnees GPS (POINT SPATIAL, SRID 4326) |

**Relations:** belongsToMany `users` (via company_user), hasMany `members`, `services`, `openingHours`, `daysOff`, `galleryImages`, `appointments`

---

### company_user (Model: `CompanyUser`)
**Lien entre un utilisateur et un salon avec son role.**
Table pivot qui definit qui travaille dans quel salon et avec quel role (owner ou employee). Le owner est celui qui a cree le salon et peut ajouter d'autres membres. Chaque membre peut avoir une photo de profil specifique au salon et etre desactive sans supprimer son compte.

| Champ | Description |
|---|---|
| company_id | Le salon |
| user_id | L'utilisateur |
| role | `owner` ou `employee` |
| profile_photo | Photo de profil dans ce salon |
| is_active | Si false, ne prend plus de RDV dans ce salon |

**Relations:** belongsTo `company`, `user`, hasMany `schedules`, `daysOff`, `appointments`, belongsToMany `services` (pivot employee_service avec duration)

---

### company_opening_hours (Model: `CompanyOpeningHour`)
**Horaires d'ouverture globaux du salon.**
Definit pour chaque jour de la semaine quand le salon est ouvert. Maximum 7 entrees par salon (une par jour). Si `is_closed` est true, le salon est ferme ce jour-la. Ces horaires sont independants des horaires individuels des employes.

| Champ | Description |
|---|---|
| company_id | Salon concerne |
| day_of_week | Jour (0=Lundi ... 6=Dimanche) |
| open_time | Heure d'ouverture (ex: 09:00) |
| close_time | Heure de fermeture (ex: 19:00) |
| is_closed | Si true, ferme ce jour |

**Relations:** belongsTo `company`

---

### company_days_off (Model: `CompanyDayOff`)
**Fermetures exceptionnelles du salon.**
Permet de bloquer des jours specifiques : jours feries, vacances annuelles, fermeture pour travaux. Quand un jour est bloque ici, aucun RDV ne peut etre pris pour aucun employe du salon ce jour-la.

| Champ | Description |
|---|---|
| company_id | Salon concerne |
| date | Date de fermeture |
| reason | Raison (ex: "Jour ferie", "Vacances") |

**Relations:** belongsTo `company`

---

### company_gallery_images (Model: `CompanyGalleryImage`)
**Galerie photos du salon.**
Images pour mettre en avant les realisations du salon : coupes, colorations, ambiance. Triees par `sort_order` pour controler l'ordre d'affichage.

| Champ | Description |
|---|---|
| company_id | Salon concerne |
| image_path | Chemin de l'image dans le storage |
| sort_order | Ordre d'affichage (0 = premier) |

**Relations:** belongsTo `company`

---

### employee_schedules (Model: `EmployeeSchedule`)
**Planning fixe hebdomadaire d'un membre du salon.**
Definit les heures de travail par jour de semaine. Ex: Lundi 9h-17h, Mardi 10h-18h. Independant des horaires du salon (un employe peut commencer plus tard ou finir plus tot que l'ouverture du salon).

| Champ | Description |
|---|---|
| company_user_id | Membre du salon concerne |
| day_of_week | Jour (0=Lundi ... 6=Dimanche) |
| start_time | Heure de debut de travail |
| end_time | Heure de fin de travail |

**Relations:** belongsTo `companyUser`

---

### employee_days_off (Model: `EmployeeDayOff`)
**Conges ponctuels d'un membre du salon.**
Permet a un employe de bloquer un jour specifique sans modifier son planning fixe. Ex: "Lundi prochain je ne travaille pas". Les RDV existants ce jour-la devront etre geres (annulation ou deplacement).

| Champ | Description |
|---|---|
| company_user_id | Membre du salon concerne |
| date | Date du conge |
| reason | Raison optionnelle |

**Relations:** belongsTo `companyUser`

---

### service_categories (Model: `ServiceCategory`)
**Categories de services.**
Regroupement optionnel des services pour l'affichage : "Coupes", "Colorations", "Soins", "Barbe", etc. Un service peut ne pas avoir de categorie.

| Champ | Description |
|---|---|
| name | Nom de la categorie |

**Relations:** hasMany `services`

---

### services (Model: `Service`)
**Prestations proposees par un salon.**
Chaque service a un nom, un prix, une duree par defaut et un type de clientele. La duree peut etre personnalisee par membre via la table pivot `employee_service`. Ex: "Coupe homme" (30min, 25EUR), "Coloration complete" (2h, 80EUR).

| Champ | Description |
|---|---|
| company_id | Salon qui propose ce service |
| category_id | Categorie optionnelle |
| name | Nom du service (ex: "Coupe homme") |
| description | Description optionnelle |
| price | Prix en euros |
| duration | Duree par defaut en minutes |
| gender | Pour qui : `men`, `women`, `both` |
| is_active | Si false, service desactive |

**Relations:** belongsTo `company`, `category`, belongsToMany `companyUsers` (pivot employee_service avec duration), hasMany `appointments`

---

### employee_service (table pivot)
**Lien membre du salon <-> services qu'il propose.**
Table pivot qui definit quels services chaque membre sait faire. Le champ `duration` permet de personnaliser la duree : si un coiffeur est plus rapide ou plus lent que la duree par defaut du service, on met la duree specifique ici. Si NULL, on utilise la duree par defaut du service.

| Champ | Description |
|---|---|
| company_user_id | Le membre du salon |
| service_id | Le service |
| duration | Duree en minutes (NULL = utiliser la duree du service) |

---

### appointments (Model: `Appointment`)
**Les rendez-vous.**
Un RDV lie un client (user), un membre du salon (company_user), un service et un salon pour une date et un creneau horaire. Le statut suit le cycle : `pending` -> `confirmed` -> `completed` (ou `cancelled` a tout moment). `company_id` est denormalise pour simplifier les requetes du dashboard salon.

| Champ | Description |
|---|---|
| user_id | Le client |
| company_user_id | Le membre du salon (employe/owner) |
| service_id | Le service reserve |
| company_id | Le salon (denormalise) |
| date | Date du RDV |
| start_time | Heure de debut |
| end_time | Heure de fin |
| status | `pending`, `confirmed`, `cancelled`, `completed` |
| notes | Notes du client |

**Relations:** belongsTo `user`, `companyUser`, `service`, `company`

---

## Schema relationnel

```
users ──┬── M:N ──> companies (via company_user avec role)
        │                │
        │           company_user ──┬── 1:N ──> employee_schedules
        │               │         └── 1:N ──> employee_days_off
        │               │
        │               │ M:N (pivot: employee_service + duration)
        │               │
        │          companies ──┬── 1:N ──> company_opening_hours
        │               │     ├── 1:N ──> company_days_off
        │               │     └── 1:N ──> company_gallery_images
        │               │
        │               ├── 1:N ──> services <── N:1 ── service_categories
        │               │
        │               └── 1:N ──> appointments
        │                               ^
        └── 1:N ──> appointments ───────┘ (user = client)
                        ^
                        │ N:1
                        company_user (employe qui fait le RDV)
```
