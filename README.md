# PeasantGo.AI

![CI](https://github.com/hedihedi822/PeasantGo/actions/workflows/ci.yml/badge.svg)

Plateforme d'aide à la décision pour les agriculteurs tunisiens : prévision de rentabilité des cultures et prévention
de la surproduction avant les semis (données satellites, météo, prix de marché et analyse par IA générative).

**Stack** : Symfony 7.2 · PHP 8.2+ · Doctrine / MySQL · Twig · Google Gemini (IA) · Sentinel-2 / Copernicus (NDVI) · Open-Meteo ·
Docker · GitHub Actions

## Démarrage rapide avec Docker

```bash
cp .env.local.example .env.local        # puis remplir APP_SECRET (voir le fichier)
export DB_PASSWORD='un-mot-de-passe-solide'
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console app:seed   # affiche le mot de passe admin généré
# Site : http://localhost:8080   ·   Boîte mail de test (Mailpit) : http://localhost:8025
```

## Sécurité

- Aucun secret dans le dépôt : `.env` ne contient que des exemples, les vraies valeurs vont dans `.env.local` (ignoré par git).
- Mots de passe hachés (hasher Symfony `auto`), protection CSRF, mot de passe oublié par code à 6 chiffres haché, 5 essais, 5 min.
- Compte admin : mot de passe généré aléatoirement par `app:seed` (aucune valeur par défaut en dur).
- CI : audit des dépendances (`composer audit`) et détection de secrets (Gitleaks) à chaque push.
- Les vidéos de démo (`public/Video/*.mp4`, ~110 Mo) ne sont pas versionnées.

---

**Fonctionnalités** : catalogue des cultures et élevages avec statistiques, formations vidéo, fenêtre du terrain
(cultures prévues et résultats), profil agricole, demandes de partenariat et panneau d'administration.

---

## 1. Installation

```bash
# 1. Base de données : adapter DATABASE_URL dans .env (ou mieux, dans .env.local)
#    copier .env.local.example en .env.local puis y mettre APP_SECRET et DATABASE_URL (jamais dans .env)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate        # crée toutes les tables (MySQL)
#    SQLite (sans serveur MySQL) : DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db" dans .env.local
#    puis : php bin/console doctrine:schema:create   (mise à jour plus tard : doctrine:schema:update --force)

# 2. Données de démonstration + compte admin
php bin/console app:seed                            # compte admin : identifiant « PeasantGo.AI » ; le mot de passe est généré et affiché UNE fois (ou --admin-password=...)

# 3. Lancer le site
symfony server:start      # ou : php -S 127.0.0.1:8000 -t public
```

> **Ancienne base `tef`** : le schéma a beaucoup changé (voir §5). Utilisez une nouvelle base (`peasantgo`)
> ou, si vous gardez `tef`, videz-la d'abord (`doctrine:database:drop --force` puis `create` + `migrate`).

### Tâches automatiques (cron, chaque nuit)

```cron
30 4 * * * cd /chemin/PeasantGo && php bin/console app:satellite:sync          # images satellites + reconnaissance des cultures
30 5 * * * cd /chemin/PeasantGo && php bin/console app:robot:veille            # sites officiels + vendeurs d'intrants, puis apprentissage
0  6 * * * cd /chemin/PeasantGo && php bin/console app:robot:analyser          # recalcul du tableau de chaque terrain (avec IA) + alertes email/SMS
30 6 * * * cd /chemin/PeasantGo && php bin/console app:robot:classement        # 24 gouvernorats d'avance : accueil visiteur et classement instantanés
0  7 * * * cd /chemin/PeasantGo && php bin/console app:statistiques:notifier   # nouvelles statistiques → tous les utilisateurs
```

### Clés des satellites (dans `.env.local`)

| Variable | Source | Où l'obtenir |
|---|---|---|
| `AGRO_API_KEY` | Agromonitoring : NDVI + images (couleurs réelles, NDVI) | compte gratuit sur agromonitoring.com → API key |
| `CDSE_CLIENT_ID` / `CDSE_CLIENT_SECRET` | Copernicus (ESA) : NDVI, EVI, NDMI sur Sentinel-2, nuages masqués | compte gratuit sur dataspace.copernicus.eu → Sentinel Hub dashboard → OAuth clients |
| `EARTHSEARCH_ENABLED=1` | Earth Search (Sentinel-2 sur AWS) : dates de passage + vignettes | aucune clé |
| `SATELLITE_DEMO=1` | données simulées pour tester sans clé | **jamais en production** |

Toutes les sources configurées sont interrogées et combinées ; une source en panne n'empêche pas les autres.

### Clés du robot (dans `.env.local`)

| Variable | Rôle | Où l'obtenir |
|---|---|---|
| `GEMINI_API_KEY` | IA générative : conseil rédigé (français + derja), lecture des images satellites, extraction des chiffres des sites officiels | aistudio.google.com/apikey (gratuit avec quotas) |
| `GEMINI_MODEL` | modèle utilisé (défaut `gemini-flash-latest`) | — |
| `OPEN_METEO_API_KEY` | météo : prévision 16 jours + 8 ans d'historique au point du terrain | **aucune clé** en usage non commercial ; clé seulement pour l'offre commerciale d'Open-Meteo |
| `METEO_DEMO=1` | météo simulée pour tester hors ligne | **jamais en production** |
| `SMS_DSN` | SMS des alertes du robot (`null://null` = aucun SMS) | un fournisseur SMS + son bridge Symfony Notifier, ex. `composer require symfony/orange-sms-notifier` (Orange) ou `symfony/twilio-notifier` |

Sans clé Gemini, le robot calcule tout avec ses règles et écrit un conseil automatique (pas de texte « IA »).

Autres commandes utiles :

| Commande | Rôle |
|---|---|
| `php bin/console app:create-admin email@x.tn` | Crée un autre membre de l'équipe (mot de passe demandé) |
| `php bin/console app:seed --admin-email=... --admin-password=...` | Seed avec un autre compte admin |
| `php bin/console app:satellite:sync --terrain=12` | Images + reconnaissance pour un seul terrain |
| `php bin/console app:statistiques:notifier --simulation` | Aperçu du message sans rien envoyer (`--seuil=10`, `--forcer`, `--sans-email`) |
| `php bin/console debug:router` | Liste de toutes les routes |

**Emails** (code « mot de passe oublié ») : en développement `MAILER_DSN=null://null`, aucun email ne part et
le code s'affiche directement sur la page (`[DEV] Code : ...`). En production, configurez `MAILER_DSN` et `MAILER_FROM`
dans `.env.local` (ex. Gmail : `composer require symfony/google-mailer`).

---

## 2. Logique du site

### Les 3 types de comptes (héritage SINGLE_TABLE sur la table `utilisateur`)

| Type | Classe | Connexion avec | Rôles | Après connexion |
|---|---|---|---|---|
| Personne (agriculteur) | `RegularUtilisateur` | **email** + mot de passe | `ROLE_USER` | `/home/user` |
| Société | `Societe` | **code société** + mot de passe | `ROLE_USER`, `ROLE_SOCIETE` | `/home/user` |
| Équipe PeasantGo | `Admin` | email + mot de passe | `ROLE_USER`, `ROLE_ADMIN` | `/Equipe_PeasantGo` |

La page `/login` a un sélecteur « Personne / Société » : `App\Security\LoginAuthenticator` cherche l'utilisateur
par email ou par code société selon ce choix. « Se souvenir de moi » garde la session 2 semaines.

### Visiteur vs membre (logique « freemium »)

| Page | Visiteur | Membre connecté |
|---|---|---|
| Accueil (`/` → `/home/user`) | Produits, saisons et dates seulement ; les statistiques sont remplacées par « Connectez-vous » (**elles ne sont même pas envoyées au navigateur**) | Tout : taux de réussite, export, % excès, coût, prix, confiance + favoris (étoile) |
| Formations (`/formation/no/user` → `/formation/user`) | Titre + extrait, vidéo verrouillée | Vidéos, description complète, compteur de vues |
| Formulaire, Profil, Partenaires | Redirection vers `/login` | Accessibles |
| `/Equipe_PeasantGo` | — | Admins uniquement (403 sinon) |

### Accueil : tableaux du catalogue

`App\Service\CatalogueService` assemble une ligne par **produit (`id_p`) × saison (`id_s`)** à partir de :
`ProduitsAgricole`, `Saison`, `TauxReussite`, `Exportation`, `EtatProduction`, `Cout`, `Prix`, `Production`,
`DateCultivation`, `Region`.

Les produits dont la catégorie commence par « Anim » vont dans « Productions animales », les autres dans
« Productions végétales ». Chaque valeur existe à 3 niveaux (national / État / région) :

- filtre **État : Tunisie** → colonnes `...ParEtat` (`tauxEtat`, `prixMoyenneDeGrosParEtat`...)
- filtre **Région** → colonnes `...ParRegion`, et seulement les produits présents dans cette région (table `region`)

Filtres (texte, type, n° saison, dates, taux, excès, favoris) et pagination se font dans le navigateur.

### Après la connexion : « Mes terrains » (`/mes-terrains`)

Carte satellite (Leaflet + imagerie Esri, plan OpenStreetMap) et liste des terrains, synchronisées (toucher l'un
sélectionne l'autre). On entre avec **un** terrain : personne ne continue sans terrain (`ChoixTerrainListener`),
le choix est gardé en session (`App\Terrain\TerrainActif`). La page regroupe aussi paramètres, profil, contact,
partenaires et le **guide d'utilisation** (`/guide`).

**Ajouter un terrain** : nom et irrigation, puis au choix (une seule des deux façons) :
- **avec l'adresse** : adresse écrite librement + surface, sans dessin ; le robot retrouve la localité
  (`Localisation` / `App\Terrain\Geocodage` : Gemini remet l'adresse en forme, puis Nominatim / Open-Meteo,
  sinon le centre du gouvernorat) ;
- **sur la carte** : l'agriculteur dessine le contour (Leaflet.draw, au doigt) et la surface est calculée du dessin.

**Justificatif obligatoire** (certificat de propriété, contrat de location ou autre preuve ; PDF/JPG/PNG/WEBP ≤ 8 Mo),
stocké dans `var/justificatifs/` (hors du dossier public, visible par le propriétaire et l'équipe seulement —
**à sauvegarder avec la base**). `App\Terrain\Justificatifs` fait une **pré-vérification**, jamais une décision :
même fichier déjà envoyé par un autre compte, contour qui chevauche le terrain d'un autre agriculteur, et avec
Gemini lecture du document (type, titulaire, CIN, surface, lieu, document retouché ?) comparée au compte.
Statuts : à fournir → en attente / pré-vérifié par le robot → vérifié par l'équipe, ou refusé (avec motif).
**Une terre = un seul compte** : si un autre compte déclare la même terre (même fichier, même n° de titre foncier
lu par l'IA, ou contours qui se chevauchent), le document le plus récent garde la terre ; l'autre terrain est
retiré (refusé avec le motif) et les deux sont prévenus. Un terrain dont le justificatif est accepté ou en examen
n'en accepte pas d'autre.
On peut entrer avec un terrain en attente ou pré-vérifié ; un terrain refusé ou sans justificatif est bloqué,
et ses déclarations ne comptent pas dans les statistiques. L'équipe décide dans l'admin (panneau « Justificatifs »).
Les terrains créés avant cette version passent « à fournir ».

### Langues

Français (par défaut), arabe (de droite à gauche) et anglais (`App\Langue\Langues`,
`translations/messages.ar.yaml` et `messages.en.yaml`, clé = texte français ; pour ajouter un texte, l'entourer de
`|trans` dans le template puis l'ajouter dans les deux fichiers). Choix dans la barre du haut ou dans Paramètres ; gardé dans un cookie et dans le compte.
Traduits : menu, accueil visiteur et membre, Mes terrains, fenêtre du terrain, tableau du robot, classement,
Formations, Paramètres. Encore en français : les messages renvoyés par le serveur, le guide, l'admin et quelques pages.

### Formateurs

- **Devenir formateur** (`/devenir-formateur`, bouton sur l'accueil visiteur et dans Formations) : présentation et
  candidature. Un visiteur choisit son mot de passe ; un membre connecté postule avec son compte.
- L'équipe accepte ou refuse dans l'admin (panneau « Candidatures de formateurs ») : accepter crée le compte formateur
  (ou ajoute le rôle `ROLE_FORMATEUR` au compte existant).
- **Déjà formateur ?** → `/formateur/connexion` → **espace formateur** (`/espace-formateur`) : publier une formation
  (titre, thème, culture, niveau, durée, description, vidéo MP4/WEBM/MOV et/ou PDF). Elle apparaît aussitôt dans
  Formations avec le nom du formateur ; le formateur peut la masquer ou la supprimer. Fichiers : `public/uploads/formations/`.
- **Taille des vidéos** : PHP limite les envois (`upload_max_filesize = 2M` par défaut). Dans `php.ini`, mettre par exemple
  `upload_max_filesize = 300M` et `post_max_size = 310M`, puis redémarrer le serveur.

### Fenêtre du terrain (accueil)

Elle s'ouvre seule tant qu'elle n'est pas remplie pour la campagne (1er septembre → 31 août), et depuis le menu du profil :
1. **Cette saison** : cultures et surfaces, élevages et effectifs prévus → cultivations `origine = plan`
   (elles alimentent la progression de la saison, donc l'offre à venir de la zone) ;
2. **Mes résultats** : les cultures de cette saison terminées, puis la saison passée : culture, surface/effectif, irrigation, dates, production, prix de vente, canal de vente,
   coût total ou détaillé par poste, résultat → cultivations `origine = historique` + prix observé + apprentissage ;
(les cultures de cette saison arrivées en fin de cycle apparaissent en tête de la partie 2, pré-remplies :
il reste la quantité, le prix et le résultat à donner). Pour annuler une culture prévue, on la retire de la partie 1.

Dans la barre du haut, l'icône de profil affiche « terrain · nom » ; son menu : formulaire du terrain, changer de terrain.

### Le robot PeasantGo.AI (`src/Robot`, `src/IA`, `src/Meteo`)

**Même tableau pour tous** (`public/js/robot-table.js`) : le visiteur voit les productions connues de sa région
(position du navigateur ou gouvernorat choisi, terrain type de 1 ha) avec réussite, production, prix et bénéfice
verrouillés ; le membre voit 1. ce qu'il a prévu (chiffré pour sa surface / son effectif), 2. les recommandations,
et la liste complète.

Pour le terrain actif, une ligne par culture (`Referentiel::CULTURES`, 12) et par élevage (`Referentiel::ANIMAUX`, 5 :
poulet de chair, pondeuses, agneaux à l'engraissement, vaches laitières, ruches) :
type, culture, n° saison, saisons/an, période de cultivation, période d'exploitation, **taux de réussite**,
**production estimée**, **exportation**, **coût**, **prix**, bénéfice estimé ; les 3 meilleurs bénéfices sont marqués
« Recommandé ». Le bouton ⓘ montre tout le calcul.

1. **Saison** (`Saisons`) : dates du catalogue officiel (`DateCultivation`) si l'admin les a saisies, sinon calendrier de référence.
2. **Climat** (`ClimatAnalyse`) : la même fenêtre de culture rejouée sur 8 années de météo au point du terrain
   (eau : pluie utile vs besoin, ou irrigation si le terrain a un compteur d'eau ; gel ; chaleur ; degrés-jours),
   corrigée par la prévision à 16 jours, puis par les récoltes déclarées dans la zone (mise à jour bayésienne).
3. **Production** : rendement de référence (sec / irrigué) × score climatique × vigueur satellite × facteur appris.
4. **Coût** (`EstimateurCout`) : intrants × prix de la zone (veille des vendeurs ou saisie admin, sinon prix par défaut)
   + main-d'œuvre × SMAG de l'année + tracteur + moisson + eau.
5. **Prix** (`EstimateurPrix`), au niveau de la **localité** : prix de référence ; couverture de la consommation du
   gouvernorat ; s'il manque de produit, coût de revient dans la zone excédentaire la plus proche + transport (t·km) ;
   indice SMIG ; historique des prix observés à la même période (≤ 150 km, toutes années) ; correction apprise des ventes déclarées.
6. **Exportation** (`BilanZones`) : la Tunisie aura-t-elle un surplus ? Le bilan tient compte de la progression des
   déclarations de la campagne en cours comparée à la même date l'an dernier.
7. **Élevage** (`ClimatAnimal`) : jours de stress thermique sur la bande, froid (volailles), pluie des mois précédents
   (pâturage, nectar) ; production par animal × effectif ; coûts par tête (aliment, poussins/poulettes/agneaux maigres,
   soins, main-d'œuvre) ; prix des agneaux majoré avant l'Aïd al-Adha (dates approximatives dans `Referentiel::AID_ADHA`).
8. **Anti-saturation** : le niveau de prix suit le bilan **national** ; la situation du gouvernorat n'ajoute que le
   transport. La production déjà **recommandée aux autres terrains du gouvernorat** compte à 25 % comme offre à venir :
   si trop d'agriculteurs reçoivent la même recommandation, son prix estimé baisse et le robot en propose une autre.

**Classement** (`/robot/classement`, `App\Robot\Classement`) : du plus rentable au moins rentable — votre terrain
(par ha), un gouvernorat (terrain type), la Tunisie (moyenne des gouvernorats producteurs), le monde (demande non
couverte dans les pays suivis, faute de prix mondiaux fiables). Badge « marché local » : saturé / équilibré / en manque.

**Alertes** (`App\Robot\AlerteService`) : la cloche ne sert qu'aux informations de l'application. Les alertes du
robot partent par **email et/ou SMS seulement si l'utilisateur les active** dans Paramètres : meilleure culture qui
change, prix ou réussite d'une culture prévue qui chute (≥ 15 %), bénéfice qui devient négatif, gel / chaleur / forte
pluie dans les 3 jours, baisse du NDVI. Chaque alerte a une clé anti-doublon (table `alerte_estimation`).

**Offre et demande par zone** (`/robot/zones/{culture}?gouvernorat=TN-…`, **réservé à l'équipe**, bouton dans le panneau admin → Robot) : monde (somme des pays suivis), Tunisie, gouvernorat,
localité — coût, production, population, consommation par habitant et totale, solde, et pour chaque zone
*vers où exporter* (zones les plus proches en manque) ou *d'où importer* (zones les plus proches en excès).
Chaque chiffre affiche sa source : officiel (veille) > équipe (admin) > PeasantGo > modèle > démo.

**Apprentissage** (`Apprentissage`) : chaque récolte déclarée (quantité, prix de vente, coût réel, résultat)
compare le réel à l'estimation faite avant ; la moyenne géométrique des écarts corrige ensuite rendement, prix et coût
par culture × gouvernorat (et au niveau national), avec un poids qui grandit avec le nombre de récoltes.

**Veille** (`VeilleService`) : les sites de l'onglet admin « Robot : sources de veille » (INS, ministère, ONAGRI au départ ;
ajoutez les vendeurs d'intrants de votre région) sont lus chaque nuit ; Gemini extrait prix, prix d'intrants et
statistiques officielles (sinon extraction simple par motifs).

### Profil (`/profil`, `/parametres`)

- **Profil** : infos personnelles (ou infos société ; code fiscal / SIRET / code société en lecture seule), photo.
- **Profil agricole** : terrains (partagés avec le formulaire), fermes et compteurs par terrain, machines.
- **Sécurité** : changement de mot de passe (ancien mot de passe vérifié), suppression du compte
  (mot de passe + taper « SUPPRIMER » ; supprime aussi terrains, cultures, machines, favoris).
- **Paramètres** : notifications, mode sombre, langue, newsletter (sauvegarde automatique).
- **Contact** : message à l'administration (visible dans l'onglet « Messages » de l'admin).

### Mot de passe oublié (`/password/forgetten`)

Email ou téléphone → code à 6 chiffres (haché en base, valable 5 min, 5 essais max) envoyé **sur l'email du compte**
→ vérification → nouveau mot de passe. Il n'y a pas de passerelle SMS : avec la méthode « téléphone », le code part
sur l'email associé à ce numéro.

### Panneau admin (`/Equipe_PeasantGo`)

Un CRUD générique (`AdminController`) gère toutes les tables à partir des métadonnées Doctrine : produits,
utilisateurs, terrains, compteurs, cultures, saisons, productions, dates, régions, consommation, coûts, exportation,
état de production, prix, taux de réussite, terre désignée, formations (avec envoi de vidéo / affiche / PDF), outils,
partenariats, messages. Recherche, pagination, validation, clés composites (`idP-idS`).
« Gérer les colonnes » : masquer / renommer / réordonner et ajouter des **colonnes calculées** (ex. `prixOutils * 1.19`),
mémorisées dans le navigateur.

### Notifications (pour tous les utilisateurs)

- **Dans le site** : cloche dans la barre de navigation (mise à jour chaque minute), tout le monde la reçoit.
- **Par email** : tout le monde par défaut ; chacun peut se désinscrire (Paramètres → « Notifications par email »),
  un lien de désinscription est dans chaque email.
- **Navigateur** : si « Notifications push » est activé dans les paramètres, une notification système s'affiche
  pour chaque nouveauté pendant que le site est ouvert.
- **Ce qui est envoyé** :
  - chaque jour, `app:statistiques:notifier` compare les statistiques avec la dernière publication
    (`statistique_snapshot`) et envoie la liste des variations ≥ 5 % (ex. « Tomate : prix 1,80 → 2,40 DT/kg (+33,3 %) ») ;
  - l'admin peut publier à la main ou envoyer une **annonce** (panneau admin → « Notifications à tous les utilisateurs ») ;
  - bienvenue à l'inscription ; alerte satellite à l'agriculteur si la végétation de son terrain chute.

### Satellites et reconnaissance des cultures (IA)

1. Dans **Profil agricole**, l'agriculteur dessine le contour de chaque terrain sur une carte (vue satellite Esri,
   recherche de lieu, géolocalisation). La surface dessinée est comparée à la surface déclarée.
2. `app:satellite:sync` (ou le bouton « Actualiser ») interroge toutes les sources et enregistre les observations
   (`observation_satellite` : NDVI moyen/min/max, EVI, NDMI, nuages, liens des images).
3. **Reconnaissance de la culture** (`src/Satellite/ClassificateurCultures.php`) à partir de la courbe NDVI de la
   campagne (1er septembre → 31 août) :
   - comparaison avec des **profils de référence** par culture pour la Tunisie (`ProfilsCultures.php` : blé, orge,
     pois chiche, colza, betterave, pomme de terre, tomate, pastèque, maïs, tournesol, olivier, sol nu), en testant
     des décalages de semis de ±3 semaines et des densités différentes ;
   - **apprentissage** sur les terrains réellement déclarés par les agriculteurs (k plus proches voisins) : plus il y a de
     déclarations, plus elles comptent (jusqu'à 70 %) ;
   - résultat : culture probable, top 5 des probabilités, **confiance calibrée**, et vérification de la déclaration
     (« conforme » / « différente » / « trop tôt pour dire » si la confiance < 50 %).
4. Le profil affiche la courbe NDVI, les profils des cultures candidates, les probabilités et les dernières images.

**Précision mesurée sur des terrains simulés** (bruit, nuages, dates de semis et densités variables, et une « réalité »
volontairement décalée par rapport aux profils théoriques) :

| Date dans la campagne | Profils seuls | Avec déclarations |
|---|---|---|
| fin janvier | 46 % (top 2 : 68 %) | 45 % (top 2 : 76 %) |
| mi-mars | 50 % | 65 % (top 2 : 86 %) |
| mi-mai | 63 % | 78 % (top 2 : 93 %) |
| fin juillet | 70 % | 94 % (top 2 : 100 %) |

En tout début de saison (novembre-décembre) les cultures d'hiver viennent de lever et les cultures d'été ne sont pas
semées : elles ressemblent toutes à du sol nu, la confiance reste donc basse — c'est attendu, elle monte avec les images.
Blé et orge restent difficiles à distinguer avec le NDVI seul. Ces chiffres viennent de données simulées : la vraie
précision devra être mesurée sur de vrais terrains déclarés.

---

## 3. Routes principales

| URL | Page |
|---|---|
| `/` | Accueil visiteur |
| `/login`, `/logout`, `/sign/up`, `/password/forgetten` | Authentification |
| `/mes-terrains` | Choix du terrain + compte (après connexion) |
| `/guide` | Guide d'utilisation |
| `/home/user` | Accueil membre : tableau du robot pour le terrain actif |
| `/robot/zones/{culture}` | Offre et demande par zone (monde → gouvernorat) — équipe seulement |
| `/robot/classement` | Cultures et élevages les plus rentables (localité, gouvernorat, Tunisie, monde) |
| `/projets-agricoles`, `/bonification-des-terres`, `/recrutement`, `/marketplace`, `/tourisme-agricole`, `/actualites`, `/syndicat` | Pages en préparation (titre seulement) |
| `/formation/no/user`, `/formation/user` | Formations |
| `/profil`, `/parametres` | Compte |
| `/rejoingner/nos/partenaires` | Demande de partenariat |
| `/Equipe_PeasantGo` | Administration |

API JSON appelée par le JavaScript des pages : `/api/register`, `/api/password/*`, `/api/profil*`, `/api/terrains*`,
`/api/fermes/*`, `/api/compteurs/*`, `/api/machines*`, `/api/cultivations/{id}/recolte`, `/api/favoris/*`,
`/api/formations/*/vue`, `/api/contact`, `/api/partenariat`, `/api/robot/*`,
`/api/cultivations/{id}/recolte`, `/api/terrain-actif/questionnaire[/1|/2]`, `/api/terrains/localiser`,
`/api/terrains/{id}/justificatif`, `/api/admin/*` (dont `/api/admin/robot/veille|apprendre|analyser` et
`/api/admin/justificatifs`). Publiques : `/api/robot/visiteur`, `/api/robot/classement/*`.
Toutes exigent l'en-tête `X-Requested-With: XMLHttpRequest` et la même origine (protection CSRF) ; `public/js/app.js`
(fonction `PG.api`) s'en charge.

---

## 4. Organisation du code

```
src/
  Command/        app:seed, app:create-admin
  Controller/     une page = un contrôleur (+ JsonApiTrait pour les réponses JSON)
  Entity/         Utilisateur (+ Admin, Societe, RegularUtilisateur), CodeTerrain, CodeCompteurs, Cultivation,
                  Ferme, Machine, Favori, DemandePartenariat, MessageContact, Formation, ProduitsAgricole, Saison,
                  Production, Prix, Cout, Exportation, EtatProduction, TauxReussite, DateCultivation,
                  Consommation, Region, TerreDesignee, Outils
  Repository/
  Satellite/      sources (Agromonitoring, Copernicus, EarthSearch, Demo), ProfilsCultures,
                  ClassificateurCultures (IA), SatelliteService
  Security/       LoginAuthenticator (email ou code société)
  Service/        CatalogueService (tableaux de l'accueil), NotificationService, StatistiquesService
templates/
  _partials/      navbar, sidebar, flash, catalogue, formations, rappel saisonnier
  <page>/         une page par dossier
public/
  js/app.js       utilitaires JS communs (PG.api, PG.toast, PG.esc...)
  js/satellite.js carte des terrains + panneau satellite du profil
  Images/, Video/, uploads/ (avatars et documents envoyés)
migrations/       Version20261112000000 : schéma complet ; Version20261112210000 : notifications + satellites
```

---

## 5. Ce qui a été corrigé / complété

- **Authentification** : il n'y en avait pas (le bouton « Connecter » était un simple lien). Ajout du fournisseur
  d'utilisateurs Doctrine, du `LoginAuthenticator`, du logout, du remember-me, du jeton CSRF réel, des rôles et
  des `access_control`. Le lien `app_dashboard` (route inexistante) est remplacé.
- **Inscription** : `/api/register` n'existait pas → créé avec validation serveur (email/téléphone/CIN/SIRET/code
  fiscal/code société uniques, mot de passe fort, mot de passe haché). Champ **CIN** ajouté pour les personnes.
- **Entités** :
  - `cin` déclaré `unique` à la fois dans `Admin` et `RegularUtilisateur` → conflit d'index ; déplacé dans `Utilisateur`.
  - `CodeUtil.php` contenait la classe `CompteurUtil`, `SocietRepository.php` contenait `SocieteRepository`
    (chargement automatique cassé) → corrigés.
  - `TerreDesignee` utilisait les anciennes annotations → attributs PHP 8.
  - `Production` / `Prix` : clé (`id_p`, `id_s`) incomplète → clé composite.
  - Getters/setters manquants (`DateCultivation`, `Region`) générés ; `$this->_em` (supprimé dans Doctrine 3) remplacé.
  - `TerrainUtil` / `CompteurUtil` (liés par CIN, impossible pour une société) remplacés par de vraies relations :
    `CodeTerrain → Utilisateur`, `CodeCompteurs → CodeTerrain`.
  - `Cultivation` stockait toutes les cultures d'un CIN dans des listes texte → une ligne par culture planifiée.
  - Nouvelles entités : `Ferme`, `Machine`, `Favori`, `DemandePartenariat`, `MessageContact`.
- **Pages** : toutes les données en dur (Youssef Thabet, Terrain Principal 2.5 ha, lignes Blé/Tomate...) et les
  actions simulées (`alert`, `console.log` du code de vérification, « Simulation de la suppression »...) sont
  remplacées par la base de données et l'API.
- **Admin** : seuls « Produits » existait ; les 15 autres onglets plantaient (`getElementById` null) et rien n'était
  enregistré. Remplacé par le CRUD générique ; `eval()` des formules remplacé par une évaluation restreinte.
- **Liens et fichiers** : logout pointait vers `/`, liens `#`, chemins Windows `\Images\image2.jpg`, images
  inexistantes, chemins de vidéos qui ne correspondaient pas aux fichiers (404), noms de fichiers abîmés par le zip (`t#U00e9l#U00e9charger.jpeg`...)
  renommés (`avatar-defaut.jpeg`, `logo.png`, `fond-ferme.avif`, `culture-mais.mp4`, `culture-pasteque.mp4`).
- **Divers** : boutons menu sans menu (erreur JS) retirés, coquille « 35 » dans l'inscription, `PeasanGoGo`,
  emails envoyés sans worker Messenger, pages d'erreur 403/404 en français, boilerplates `index.html.twig` supprimés.

## 6. Limites connues

- Connexion Facebook / Google : boutons désactivés (« Bientôt disponible ») — il faudrait `knpuniversity/oauth2-client-bundle`.
- « Services payants / Marché » : page non développée (marquée « bientôt »).
- Envoi de SMS non configuré (le code part par email).
- Les API satellites (Agromonitoring, Copernicus, Earth Search) sont codées d'après leur documentation mais n'ont pas
  pu être appelées pendant le développement (pas d'accès internet) : à vérifier avec vos clés. Le mode
  `SATELLITE_DEMO=1` a servi à tester toute la chaîne.
- Notifications push « téléphone fermé » (Web Push) : non incluses ; il faudrait `composer require minishlink/web-push`
  et un service worker. Les notifications navigateur actuelles s'affichent quand le site est ouvert.
- **Robot** : Open-Meteo, Gemini et les sites de veille n'ont pas pu être appelés pendant le développement (pas
  d'internet) ; toute la chaîne a été testée avec `METEO_DEMO=1` / `SATELLITE_DEMO=1`. À la première vraie veille,
  vérifiez dans l'admin ce qui a été extrait.
- Les chiffres par pays fournis par `app:robot:initialiser` sont **marqués « démo »** : remplacez-les par les chiffres
  officiels (admin → « Robot : statistiques par zone », source « officiel ») ; le robot les préférera automatiquement.
  Les populations des gouvernorats viennent du recensement INS 2024.
- Les estimations sont des **aides à la décision**, pas des garanties : elles deviennent plus justes avec les
  récoltes déclarées par les agriculteurs.
- Open-Meteo est gratuit pour un usage **non commercial** ; une version payante de l'application devra prendre leur offre commerciale.
- Carte : tuiles OpenStreetMap et imagerie Esri World Imagery, avec attribution ; Nominatim (OpenStreetMap) limite à
  1 requête/s et demande de mettre les résultats en cache (fait : 90 jours). Pour un fort trafic, prévoir un service
  de géocodage et de tuiles payant ou auto-hébergé.
- **Justificatifs** : le robot ne fait qu'une pré-vérification ; il ne peut pas prouver qu'un document est authentique.
  La validation définitive reste humaine. Sans clé Gemini, tout passe par l'équipe.
- Élevage : les valeurs du référentiel (production par animal, aliment, prix) sont des ordres de grandeur ;
  les chiffres de démonstration par pays aussi. Les déclarations « saison passée » les corrigent.
- SMS : aucun fournisseur n'est configuré (`SMS_DSN=null://null`) ; les alertes SMS sont journalisées mais pas envoyées
  tant qu'un fournisseur n'est pas branché.
- Les vidéos sont dans `public/Video` : la page verrouille l'affichage pour les visiteurs, mais un fichier reste
  téléchargeable si on connaît son URL exacte.

## Formations payantes

- Le formateur choisit **Gratuite** ou **Payante** et fixe le prix (DT). Document accepté : PDF ou Word (DOC, DOCX, ODT).
- Avant l'achat, l'agriculteur connecté voit **25 % du contenu** : 25 % de la durée de la vidéo, 25 % des pages du PDF (affichées par pdf.js, `public/vendor/pdfjs`), 25 % du texte d'un DOCX/ODT (lu par le serveur). Un ancien `.doc` n'a pas d'aperçu.
- Les fichiers des formations payantes passent par `/formation/{id}/media/...` : le document entier n'est servi qu'après l'achat. Limite : la vidéo de l'aperçu est envoyée en entier au navigateur (le lecteur s'arrête à 25 %), une personne très technique pourrait la récupérer.
- L'achat est lié au **compte** : la formation reste ouverte avec tous les terrains de l'agriculteur.
- Paiement : l'agriculteur paie (D17, Flouci, virement, mandat) et donne la référence ; l'équipe confirme dans l'administration (« Achats de formations à vérifier »). Le client et le formateur sont notifiés.
- `.env` : `PAIEMENT_COMPTE` (où payer, affiché au client) ; `PAIEMENT_DEMO=1` valide l'achat tout de suite (essais seulement).
- Migrations : `Version20261114050000` (prix), `Version20261114060000` (achats).
