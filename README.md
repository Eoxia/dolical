# DoliCal

Agenda d'équipe pour Dolibarr. Il s'appuie **uniquement sur les événements natifs** de l'agenda Dolibarr : aucune
table ajoutée, tout ce que l'on voit et modifie reste visible dans l'agenda standard. Il ne dépend d'aucun module
externe, pour pouvoir rejoindre le cœur de Dolibarr.

## Ce que fait le module

- **Plusieurs agendas en même temps** : on coche les personnes (ou un groupe d'utilisateurs entier) dans la colonne de
  gauche, chacune avec sa couleur (celle de sa fiche utilisateur, sinon une couleur stable). La palette au bout de la
  ligne change la couleur d'une personne pour soi seulement : les autres gardent les leurs, et le téléphone synchronisé
  la reprend.
- **Cinq vues** :
  - *Jour* : une colonne par personne, côte à côte ;
  - *Semaine* : les sept jours, tous les agendas superposés ; un rendez-vous de plusieurs personnes affichées y
    apparaît une fois pour chacune (sauf celles qui l'ont refusé), côte à côte, à sa couleur ;
  - *Équipe* : une ligne par personne, les jours de la semaine en colonnes ;
  - *Mois* et *Liste* (deux semaines, pratique sur téléphone).
- **Glisser-déposer** : déplacer un événement dans le temps, l'étirer par le bas, ou le faire passer de la colonne d'une
  personne à celle d'une autre (vues Jour et Équipe).
- **Création en un clic** sur un créneau vide, ou en faisant glisser la souris pour choisir les horaires (vues Jour et
  Semaine, au quart d'heure) ou plusieurs jours (vue Mois, bande « Toute la journée »), et bouton « Planifier dans
  l'agenda » sur les fiches devis, commande,
  facture, contrat, intervention, ticket, expédition, documents fournisseurs, tiers, projet et contact : le formulaire
  s'ouvre déjà rempli.
- **Formulaire complet** : organisateur, collègues invités, contacts externes, tiers, projet, plusieurs documents liés
  (devis, commandes, factures, contrats, tickets…), description et pièces jointes (glisser-déposer).
- **Carte au clic** sur un événement : organisateur, dates et durée, lieu (copie, carte), participants avec leur réponse
  (acceptée, peut-être, refusée, en attente), copie des e-mails et e-mail groupé, tiers, projet, **facturation** (devis
  et factures, montant, reste à payer, retard de paiement), autres éléments liés, pièces jointes (dépôt direct sur la
  carte), description, auteur. Réponse à l'invitation, passage à « réalisé », duplication, suppression.

## Invitations par e-mail

- Dans le formulaire, on choisit un contact ou on **tape une adresse e-mail** (« Jean Dupont <jean@exemple.fr> ») :
  le contact est retrouvé, ou créé et rattaché au tiers de l'événement.
- Case « Envoyer les invitations par e-mail » (cochée d'office dès qu'on ajoute quelqu'un ou qu'on change la date ou
  le lieu) : les nouveaux participants reçoivent l'invitation, et tous reçoivent une « Modification » si la date ou
  le lieu change. Bouton « Inviter » et envoi par personne sur la carte. Une même personne ne reçoit pas deux fois
  l'invitation ou la relance d'un événement en 10 minutes, et les envois sont limités par heure (voir *Limites*).
- L'e-mail contient un bouton vers une **page publique sans compte** (Oui / Peut-être / Non) et un fichier `.ics`
  pour l'ajouter à Outlook, Gmail ou Apple Calendrier. Le lien est signé avec la clé de l'instance, pour une personne
  et un événement : rien à stocker, impossible à deviner. Une fois l'événement passé, le `.ics` n'est plus proposé ;
  30 jours après (réglable), le lien n'est plus valable.
- La réponse s'enregistre dans le champ natif `answer_status`, l'organisateur reçoit un e-mail (réglable), au plus un
  toutes les 10 minutes pour une même personne qui changerait sa réponse.
- **Annulation** : supprimer l'événement, ou en retirer quelqu'un avec la case cochée, envoie un e-mail « Annulé » et
  un `.ics` d'annulation qui retire l'événement de l'agenda des personnes invitées.
- **Relance** : bouton « Relancer (N) » sur la carte pour les invités sans réponse, et relance automatique optionnelle
  quelques jours avant (tâche planifiée « Relance des invités DoliCal », une seule fois par personne).
- **Réponses dans le calendrier** : un événement refusé est barré dans la colonne de la personne, « peut-être » est en
  pointillés, et chaque événement affiche le décompte ✓ / ? / ✗ / en attente.
- **Disponibilités** : le formulaire avertit quand un collègue invité est déjà pris sur le créneau.
- Chaque envoi et chaque réponse laissent un événement automatique natif (onglet Événements du tiers et du contact).
- Les textes sont modifiables dans *Configuration > E-mails > Modèles* (types « DoliCal – … »), sinon un texte par
  défaut traduit est utilisé.

## Rappels

- Interrupteur « Me rappeler l'événement » dans le formulaire, allumé par défaut (réglable) : délai en minutes, heures,
  jours ou semaines, par **notification du navigateur** et/ou **e-mail**, pour soi seul ou pour tous les participants
  internes.
- Ce sont les rappels natifs (`llx_actioncomm_reminder`) : la notification s'affiche sur toutes les pages de Dolibarr,
  l'e-mail part par la tâche planifiée native « SendEmailsReminders ». Ils suivent l'événement quand on le déplace.
- Le formulaire demande l'autorisation des notifications au navigateur, et prévient quand elles sont bloquées.

## Événements récurrents

- « Ne se répète pas », tous les jours, jours ouvrés, chaque semaine le lundi, toutes les deux semaines, chaque mois
  (le 2, ou le premier lundi), chaque année, jusqu'à une date. Les libellés suivent le jour choisi.
- Comme dans l'agenda natif, chaque date est un événement à part entière (ses propres réponses, son compte-rendu, ses
  pièces jointes), les dates d'une série partageant `recurid` ; la règle est écrite en syntaxe iCalendar dans
  `recurrule`, la fin dans `recurdateend`. Une série de l'agenda natif est relue.
- Modifier ou supprimer une date d'une série demande « Cet événement / Cet événement et les suivants / Tous les
  événements ». Changer la règle reconstruit les dates à venir, jamais celles déjà passées ; « et les suivants » coupe
  la série en deux. Glisser-déposer une date ne déplace qu'elle.
- Une série est l'œuvre d'un seul auteur : un événement d'un autre auteur portant le même `recurid` n'en fait pas
  partie, et les dates ajoutées par un collègue qui reconstruit la série restent de son auteur. Les droits sont
  vérifiés sur chaque date : une date attribuée à une autre personne, que l'on ne peut pas modifier ou supprimer,
  reste telle quelle et le bilan le signale.
- Les invités reçoivent **un seul e-mail pour toute la série** (`.ics` avec `RRULE` et fuseau horaire), une
  exception (`RECURRENCE-ID`) quand une seule date change, et leur réponse vaut pour toutes les dates à venir.

## Compte-rendu après le rendez-vous

- Une fois le rendez-vous terminé, sa carte demande « comment s'est-il passé ? », et le calendrier le propose dans un
  encart le jour même : **Écrire le compte-rendu**.
- Le texte est le champ complémentaire « Compte-rendu » de l'événement natif (visible aussi dans l'agenda de
  Dolibarr) ; l'événement peut être marqué réalisé au passage. Les photos et documents du rendez-vous sont ses
  pièces jointes, ajoutées depuis le formulaire ou la carte. L'écrivent ceux qui peuvent modifier l'événement, et les
  participants qui ont le droit « Écrire le compte-rendu ».

## Synchronisation avec les téléphones (CalDAV)

- Fermée par défaut : un administrateur l'ouvre dans la configuration de DoliCal.
- Menu *Synchroniser mon téléphone* : l'adresse, l'identifiant, les étapes pour **DAVx⁵ (Android)**, **iPhone / iPad /
  Mac** et **Thunderbird**. Chacun y crée son **mot de passe de synchronisation** (affiché une fois, gardé haché dans
  son paramètre utilisateur `DOLICAL_CALDAV_PASSWORD`), le renouvelle ou désactive sa synchronisation. Le mot de passe
  Dolibarr n'ouvre pas CalDAV, et celui de synchronisation n'ouvre pas Dolibarr.
- **Connexion en un scan**, affichée une fois avec le mot de passe juste créé : un QR code pour **DAVx⁵**
  (`davx5://identifiant:motdepasse@serveur/…`, `caldav://` en http), qui s'ouvre avec la connexion remplie, et un QR code
  pour l'**iPhone / iPad**, lien d'un profil de configuration `.mobileconfig` (compte CalDAV complet : serveur,
  identifiant, mot de passe). Sur le téléphone lui-même, un bouton à la place du QR code, avant les valeurs à recopier.
  Le profil est servi par `public/caldavprofile.php`, sans session Dolibarr, **en HTTPS seulement**, **une seule fois** et
  pendant **10 minutes** ; un nouveau mot de passe ou la désactivation l'annulent. Entre-temps, le mot de passe attend
  dans le paramètre utilisateur `DOLICAL_CALDAV_PROFILE`, chiffré (AES-256-GCM) avec une clé que seul le lien porte.
  QR codes dessinés par la bibliothèque TCPDF livrée avec Dolibarr, sans service externe.
- Connexion protégée comme la page de connexion : hooks `beforeLoginAuthentication` et `afterLoginFailed` (modules de
  double authentification, de bannissement, de limitation), échecs dans les événements de sécurité, et au plus 5 échecs
  par identifiant et 20 par adresse en 15 minutes (réglable) : au-delà, même le bon mot de passe est refusé un moment.
- Refusée en http, sauf option « installation de test » de la configuration.
- Serveur CalDAV `dav/caldav.php`, sur la bibliothèque SabreDAV livrée avec Dolibarr. Dans les deux sens : créer,
  déplacer, renommer, supprimer un rendez-vous sur le téléphone met à jour l'événement natif ; accepter ou refuser
  une invitation remplit `answer_status` ; une alarme du téléphone devient un rappel, et inversement.
- Une répétition créée sur le téléphone devient une série native. Un rendez-vous refusé quitte le téléphone ;
  supprimer un rendez-vous dont on n'est qu'invité le refuse.
- Les agendas de collègues choisis sur la page s'ajoutent, chacun avec sa couleur, avec les mêmes droits que dans le
  calendrier : modifiables avec le droit natif « Créer/modifier les événements des autres »
  (`agenda->allactions->create`), en lecture seule sinon. Un rendez-vous créé dans l'agenda d'un collègue lui
  appartient ; une suppression y demande le droit de supprimer ses événements (pas de refus à sa place) ; l'alarme du
  téléphone n'y devient pas un rappel (les rappels d'un collègue restent les siens).
- Déplacer un rendez-vous d'un agenda à l'autre sur le téléphone (PUT dans le nouveau puis DELETE dans l'ancien, ou
  MOVE) transfère l'événement natif, sans copie : la personne du nouvel agenda y participe (propriétaire si l'autre
  l'était), celle de l'ancien le quitte avec ses rappels. Le téléphone garde pour cela l'identifiant (`UID`) de
  l'événement ; les deux envois sont rapprochés pendant 15 minutes (fichier dans `documents/dolical/temp/caldav`).
- **Rappels du téléphone** : à côté de son agenda, une liste « Rappels de … » à son nom (`VTODO`) pour l'app Rappels
  de l'iPhone, Tasks.org ou jtx Board avec DAVx⁵. Chaque rappel est un événement natif du type « Rappel »
  (`AC_DOLICAL_TODO`, ajouté au dictionnaire des types à l'activation ou à la première synchronisation) : son échéance
  est la date de l'événement (aucune date sans échéance), coché il est réalisé (100 %), décoché à faire (0 %) ; la
  priorité et la description suivent, l'alerte devient un rappel de Dolibarr avant l'échéance. Seuls les événements de
  ce type vont dans la liste, ils quittent l'agenda ; les rappels réalisés depuis plus de 180 jours quittent la liste.
  Les listes des collègues dont l'agenda est synchronisé, avec les droits de leur agenda, seulement si la case
  « Ajouter aussi leurs listes de rappels » est cochée (paramètre utilisateur `DOLICAL_CALDAV_SHARED_REMINDERS`) :
  sinon le serveur ne les donne pas, même par leur adresse directe. Le téléphone ne crée pas d'autre liste
  (`MKCALENDAR` refusé) ; un rappel qui se répète sur le téléphone est gardé comme un seul rappel.
- Rien de nouveau n'est stocké : le nom de fichier et l'identifiant qu'un téléphone donne à un événement qu'il crée vont
  dans la référence externe de l'événement natif (`ref_ext = dav:<fichier>|<uid>`).
- Les rendez-vous des 180 derniers jours et tous ceux à venir. HTTPS indispensable en production (l'iPhone refuse un
  compte en http).

## Import d'un agenda (.ics)

- Menu *Importer un agenda* (et lien sous la liste des agendas du calendrier) : on envoie le fichier `.ics` exporté
  de **Nextcloud**, **Google Agenda**, **Outlook** ou **Thunderbird** ; la page rappelle où le trouver dans chacun.
- Les rendez-vous vont dans son propre agenda, ou dans celui d'un collègue avec le droit natif « créer les événements
  des autres ». Il faut le droit de créer des événements.
- Chaque rendez-vous est créé comme ceux d'un téléphone : alarme reprise en rappel, répétition transformée en série
  jusqu'à un an après aujourd'hui (au plus 366 dates, les plus récentes), dates supplémentaires (`RDATE`) comprises ;
  une date modifiée seule garde son titre, son lieu et sa description. Les rendez-vous annulés sont laissés de côté
  et aucun e-mail n'est envoyé. Statut « Non applicable » : un rendez-vous importé n'est ni à faire ni en retard.
- Une répétition plus fréquente que le jour (toutes les heures, plusieurs heures par jour…) n'est pas une série de
  l'agenda : le rendez-vous est gardé seul. Le fichier fait au plus 10 Mo et 5 000 rendez-vous (réglables).
- Ce que l'événement n'a pas de champ pour garder est ajouté sous sa description : titre ou lieu complet au-delà de
  128 caractères (`ActionComm::create()` les coupe), personnes introuvables ou sans adresse avec leur réponse,
  catégories, lien de visio absent de la description.
- Participants (organisateur compris), parmi les utilisateurs actifs et les contacts visibles : par leur e-mail (pour
  un utilisateur, le professionnel ou le personnel), sinon par leur nom — celui affiché par l'agenda, ou le
  « prenom.nom » de l'adresse — quand une seule personne le porte. Un nom d'un seul mot ne suffit pas.
- Réponses reprises : celle de la personne qui importe ou synchronise, et celle qu'un contact a donnée avec son
  adresse. Un collègue garde sa propre réponse (« en attente » s'il n'a pas répondu) : l'autre agenda ne répond pas
  pour lui, pas plus qu'un contact retrouvé par son seul nom.
- Tiers : celui qui a l'adresse d'un participant, sinon celui du premier contact retrouvé, sinon le seul tiers (e-mail
  ou site) du domaine des adresses extérieures, quand elles mènent toutes à lui. Les domaines des messageries grand
  public et ceux des utilisateurs de l'instance ne comptent pas.
- L'identifiant d'origine est gardé dans `ref_ext` (`dav:import-<personne>-<empreinte>.ics|<uid>`) : réimporter le
  même fichier ne recrée pas les rendez-vous déjà présents dans l'agenda de la personne, comme organisatrice ou
  participante. Qui peut modifier le rendez-vous lui ajoute (à toute la série) les participants et le tiers retrouvés
  depuis ; rien n'est retiré. Un simple invité le laisse tel quel.
- Le bilan liste les adresses de personnes introuvables : renseigner l'adresse sur la fiche puis réimporter les relie.
- Chaque import marque les événements qu'il crée (séries comprises) d'une même clé, dans le champ natif `import_key`.
  La page liste les 20 derniers imports dont il reste des événements (les siens et ceux faits dans son agenda ; tous
  avec le droit de supprimer les événements des autres) : *Supprimer cet import* efface d'un coup ses événements,
  comme la suppression native et sans prévenir les participants. Les participants ajoutés par une réimportation à des
  rendez-vous déjà présents ne sont pas retirés.

## Un événement seul, dans une fenêtre

`view/dolical_event.php` montre un événement sans le calendrier : son formulaire (`action=create` avec les mêmes
paramètres que le calendrier : `socid`, `projectid`, `contactids`, `elements`, `start`, `end`, `allday` ; ou
`action=edit&id=`) ou sa fiche (`id=`). Seule, la page revient au calendrier après l'enregistrement ou à la fermeture.

Ouverte dans la fenêtre d'une autre page du même site (iframe), elle la prévient par `postMessage` (même origine) :
`{source: 'inbox-panel', action}` avec `title` (titre de la fenêtre), `dirty` (saisie non enregistrée, `on` et
`text`), `done` (enregistré ou supprimé, `message` à afficher) ou `close`.

**Volet agenda d'une messagerie** : une application qui offre le hook `inboxAgendaProvider` (contexte `inbox`) reçoit
de DoliCal les adresses de cette page pour créer un rendez-vous (tiers, contacts, projet et documents reconnus sur le
mail ouvert) et ouvrir un rendez-vous, et celle du calendrier pour tout voir. Rien n'est appelé sans cette application.

**Planifier un rendez-vous depuis un mail** : avec le hook `inboxMailActions`, le mail ouvert reçoit l'action
« Planifier un rendez-vous ». Elle ouvre cette page avec le sujet du mail en titre (sans « Re: », « TR: »…), ses
correspondants en contacts (le contact de l'adresse s'il existe et qu'on peut le voir, sinon un nouveau contact
proposé, créé à l'enregistrement) et le Message-ID du mail gardé sur l'événement (`email_msgid`). Le sujet, les
adresses et le Message-ID arrivent après le `#` de l'adresse, que le navigateur n'envoie jamais au serveur, puis
partent au formulaire par POST : ils ne sont écrits dans aucun journal du serveur web.

**Invitations lues dans la messagerie** : avec le hook `inboxInviteEvent`, une invitation envoyée par DoliCal à un
utilisateur déjà participant est reconnue par son identifiant (`dolical-<entité>-<id>@<hôte>`, une série, ou
l'identifiant choisi par un téléphone) et par son organisateur. La messagerie affiche « Déjà dans votre agenda » au
lieu de proposer un ajout en double ; la réponse donnée depuis le mail est enregistrée comme par le lien de
l'invitation (réponse native, trace, organisateur prévenu).

**Rendez-vous avec le tiers du mail** : avec le hook `inboxMailPanels`, le bandeau du mail montre un bloc
« Rendez-vous » quand le tiers reconnu en a : le prochain, le dernier, et « Compte-rendu attendu » quand le dernier,
fini depuis moins de 30 jours, n'en a pas (le bouton ouvre sa fiche pour l'écrire). Seulement les rendez-vous que
l'utilisateur peut lire ; aucun bloc sans rendez-vous. Résumé lu par `ajax/meetings.php` (lecture seule).

## Prise de rendez-vous en ligne

Une page publique, sans compte, où un client prend rendez-vous dans l'agenda d'une personne de l'entreprise : **un
lien par personne** (`public/booking.php?entity=<entité>&p=<clé>`). La clé est signée avec la clé de l'instance,
comme les liens d'invitation : l'identifiant de l'utilisateur n'apparaît nulle part, et le lien d'un collègue ne se
devine pas à partir du sien. Retirer une personne des personnes proposées ferme son lien. Quand le module Saturne est
activé, chaque lien a aussi son **lien court** (`saturne/s/?code`, créé une fois puis réutilisé, raccourci encore avec
`SATURNE_SHORT_URL_ROOT`). La page publique n'a pas l'en-tête de Dolibarr : le logo de la société s'il y en a un, rien
sinon (de même pour la page de réponse aux invitations). La page sans personne ne liste personne pour l'instant
(constante cachée `DOLICAL_BOOKING_HUB` à 1 pour afficher la liste des personnes proposées).

**Configuration** : fermée par défaut. Deux onglets de la configuration de DoliCal, sur le modèle des rendez-vous de
Nextcloud.

- **Prise de rendez-vous en ligne**, en sections :
  - *Agendas et planification* : ouverture de la page, personnes proposées, durée par défaut d'un créneau, tampons
    avant et après un rendez-vous, préavis minimum (2 heures par défaut), réservable jusqu'à N semaines (4 par
    défaut ; 5 semaines un lundi : jusqu'au lundi 35 jours plus tard, compris) ;
  - *Informations de contact* : titre de la page, e-mail de l'organisateur, lieu, téléphone, présentation ;
  - *E-mails et notifications* : e-mails au client (confirmation, annulation), e-mails à la personne (réservation,
    annulation), texte en plus de la confirmation, description en plus du `.ics` ;
  - *Paramètres du formulaire* : titre des créneaux, champ « Téléphone » (facultatif, obligatoire ou masqué), case de
    consentement (RGPD) ;
  - *Sécurité* : adresses e-mail bloquées, code anti-robot, rendez-vous à venir par adresse e-mail, réservations par
    heure et par connexion ;
  - *Rappels* : rappels au client et leur texte en plus ;
  - *Visioconférence* : lien de visio commun, ou une salle Jitsi par rendez-vous ;
  - *Avancé* : modèle du titre de l'événement, page de redirection après réservation.
- **Modèle hebdomadaire** : les créneaux proposés chaque jour, posés dans une semaine type comme sur Nextcloud :
  - glisser dans une journée crée un créneau, un clic en crée un de la durée par défaut ;
  - un créneau se déplace (aussi vers un autre jour) et s'étire à la souris ; clic ou clic droit pour changer ses
    heures ou le supprimer ;
  - le menu « … » d'une journée ajoute une série de créneaux (début, durée, nombre), copie la journée vers d'autres
    jours ou la vide ;
  - deux créneaux d'un même jour ne se chevauchent pas.

Une configuration enregistrée avant le modèle (jours cochés et plages horaires) est lue comme un modèle : les plages
découpées à la durée choisie, jusqu'au premier enregistrement du modèle.

Le titre de la page, la présentation et le titre des créneaux sont communs, et **chaque personne peut écrire les
siens** sur « Ma page de prise de rendez-vous » (sous la liste des agendas : son lien à copier, ses textes, les textes
communs en grisé, son lien de visio) ; l'administrateur fait de même sur la page de chacun (crayon à côté de son lien
dans la configuration). Un texte laissé vide reprend le texte commun. La présentation se met en forme dans l'éditeur
WYSIWYG de Dolibarr (gras, couleurs, listes, liens ; nettoyée de tout script à l'enregistrement et à l'affichage). Les
emojis sont gardés, même sur une base en latin1 : ils sont enregistrés en entités numériques.

- **Le client** choisit un créneau libre de la semaine (semaines suivantes en flèches), puis donne son nom, son e-mail
  et, selon la configuration, son téléphone, sa société, un message et son consentement.
- **Créneaux libres** : les créneaux du modèle hebdomadaire, après le préavis et jusqu'au nombre de semaines choisi.
  Sont écartés les créneaux pris, tampons compris, par un événement de la personne (organisatrice ou invitée, sauf si
  elle l'a refusé ; les événements automatiques ne comptent pas), ses congés validés (module Congés) et les jours
  fériés du pays de l'entreprise. Un événement marqué *disponible* qui dure une journée ou plus ne bloque pas (journée
  de télétravail, tâche étalée sur des semaines) ; plus court, il bloque même marqué disponible.
- **Le rendez-vous** est un événement natif de type « Rendez-vous », de la durée de son créneau, dans l'agenda de la
  personne, avec le rappel par défaut, le lieu et le titre de la configuration. Le client devient un contact (retrouvé
  par son e-mail, sinon créé et rattaché au tiers du domaine de son adresse quand il n'y en a qu'un), participant avec
  la réponse « acceptée » ; ses coordonnées, son message et le texte du consentement accepté sont dans la description.
- **E-mails** : le client reçoit la confirmation avec le `.ics` et un lien signé (comme celui d'une invitation) pour
  revoir ou **annuler** son rendez-vous ; la personne reçoit « Nouveau rendez-vous », avec la réponse directe au
  client. Le client répond à l'e-mail de l'organisateur de la configuration, à défaut à celui de la personne. Le texte
  de la confirmation est modifiable (modèle « DoliCal – Confirmation d'un rendez-vous pris en ligne »,
  `__DOLICAL_ANSWER_LINK__` étant le lien du client). Déplacer ou supprimer le rendez-vous dans le calendrier prévient
  le client comme un invité, sous le titre de sa page et avec le lien de son rendez-vous.
- **Rappels au client** : 1 semaine, 2 jours, 1 jour, 2 heures, 1 heure avant (aucun par défaut). La tâche planifiée
  « Rappels des rendez-vous en ligne » (toutes les 15 minutes, module Travaux planifiés) envoie au client un e-mail
  avec la date, la visio et le lien pour annuler. Un rappel dont l'heure était passée quand le rendez-vous a été pris
  n'est pas envoyé ; plusieurs dus d'un coup (tâche arrêtée un moment) n'en font qu'un. Modèle modifiable « DoliCal –
  Rappel d'un rendez-vous pris en ligne » ; chaque envoi laisse une trace `AC_DOLICAL_BOOKREMIND` sur le rendez-vous.
- **Visio** : le lien de la personne (« Ma page de prise de rendez-vous »), sinon le lien commun de la configuration,
  ou une salle Jitsi nouvelle par rendez-vous (au nom de la page et de la date, suivie d'une partie aléatoire) quand la
  configuration le demande. Il est mis sur chaque rendez-vous pris et envoyé dans la confirmation (« Rejoindre la
  visio », et dans le `.ics`). Pour un seul rendez-vous, le champ « Lien de visio » du formulaire le change :
  enregistré avec « Envoyer les invitations par e-mail » coché, le client reçoit l'e-mail « Lien de la visio ».
- **Annulation par le client** : l'événement est supprimé, le créneau se libère, la personne est prévenue, le client
  reçoit le `.ics` qui retire le rendez-vous de son agenda, et une trace reste dans l'historique du contact.
- **Protections** :
  - deux visiteurs sur le même créneau : le second est renvoyé aux créneaux ;
  - un double envoi rend le même rendez-vous ;
  - formulaire signé, refusé s'il est renvoyé en moins de 2 secondes ou après 2 heures, champ piège pour les robots ;
  - adresses bloquées, rendez-vous à venir par adresse e-mail et réservations par heure et par adresse IP limités ;
  - code anti-robot de Dolibarr en option.


## Où sont stockées les données

| Donnée | Stockage natif |
| --- | --- |
| Événement | `llx_actioncomm` |
| Collègues et contacts invités, réponses | `llx_actioncomm_resources` (`user`, `socpeople`, `answer_status`) |
| Premier document lié | `llx_actioncomm.fk_element` / `elementtype` (onglet *Événements* du document) |
| Autres documents liés | `llx_element_element` (type `action`) |
| Pièces jointes | `documents/agenda/<id>` + index ECM |
| Rappels | `llx_actioncomm_reminder` |
| Série | `llx_actioncomm.recurid`, `recurrule`, `recurdateend` |
| Compte-rendu | champ complémentaire `dolical_report` de l'événement |
| Lien de visio | champ complémentaire `dolical_visio` de l'événement (le lieu natif ne tient que 128 caractères) |
| Événement créé sur un téléphone | `llx_actioncomm.ref_ext` : `dav:` + nom du fichier + `\|` + identifiant |
| Import d'un agenda (.ics) | `llx_actioncomm.import_key` : même clé pour tous les événements d'un import |
| Rendez-vous pris en ligne | `llx_actioncomm.ref_ext` = `dolical-booking` ; le client est un contact natif |
| Textes de la page d'une personne | paramètres utilisateur `DOLICAL_BOOKING_TITLE`, `_INTRO`, `_FORM_TITLE` (communs : constantes du même nom) |
| Lien de visio d'une personne | paramètre utilisateur `DOLICAL_BOOKING_VISIO` (commun : constante du même nom) |
| Modèle hebdomadaire des créneaux | constante `DOLICAL_BOOKING_SLOTS` : `{"<jour ISO>": [[début en minutes, durée en minutes], …]}` |
| Agendas qui bloquent les créneaux | constante `DOLICAL_BOOKING_CONFLICT_USERS` (tout le monde) et paramètre utilisateur du même nom (une personne) : ids d'utilisateurs séparés par des virgules |
| Réglages de la prise de rendez-vous | constantes `DOLICAL_BOOKING_*` de l'entité ; textes en entités numériques pour les emojis |
| Lien court d'une page (avec Saturne) | table de Saturne `llx_saturne_object_redirection` |
| Réservations récentes par adresse IP | `documents/dolical/temp/booking/` (quelques octets, oubliés après une heure) |
| Agendas de collègues synchronisés | paramètre utilisateur `DOLICAL_CALDAV_SHARED` |
| Rappels du téléphone (`VTODO`) | événements natifs du type `AC_DOLICAL_TODO` (`llx_c_actioncomm`) |
| Couleurs choisies pour les autres | paramètre utilisateur `DOLICAL_COLORS` (`{"id": "rrggbb"}`) |

## Droits

Ceux du module Agenda : lire / créer / supprimer *mes* événements ou *ceux de tous*. Le module ajoute « Consulter
DoliCal », l'accès à sa page de configuration, et « Écrire le compte-rendu » : un participant qui ne peut pas modifier
l'événement écrit son compte-rendu et y joint des fichiers avec ce droit (donné d'office aux nouveaux utilisateurs ;
réactiver le module pour le voir, puis l'accorder aux utilisateurs existants qui en ont besoin).

Sur la carte d'un événement, l'e-mail et le téléphone d'un contact ne s'affichent qu'avec le droit de lire ce contact,
ceux d'un utilisateur avec le droit de lire les utilisateurs, comme sur leurs fiches natives.

## Limites

Constantes facultatives (*Configuration > Divers*), 0 pour lever une limite :

| Constante | Par défaut | Rôle |
| --- | --- | --- |
| `DOLICAL_MAIL_RESEND_DELAY` | 10 | Minutes avant de renvoyer une invitation ou une relance à la même personne |
| `DOLICAL_MAIL_MAX_PER_EVENT` | 50 | E-mails par heure qu'une personne envoie pour un même événement |
| `DOLICAL_MAIL_MAX_PER_USER` | 200 | E-mails par heure qu'une personne envoie depuis le calendrier, tous événements confondus |
| `DOLICAL_ANSWER_MAIL_DELAY` | 10 | Minutes entre deux e-mails à l'organisateur pour les réponses d'une même personne |
| `DOLICAL_INVITATION_VALIDITY` | 30 | Jours pendant lesquels le lien d'invitation reste lisible après l'événement |
| `DOLICAL_IMPORT_MAX_SIZE` | 10240 | Taille maximale d'un fichier importé, en Ko (dans la limite du serveur) |
| `DOLICAL_IMPORT_MAX_EVENTS` | 5000 | Rendez-vous lus au plus dans un fichier importé |
| `DOLICAL_IMPORT_TIME_LIMIT` | 600 | Durée maximale d'un import ou de sa suppression, en secondes |
| `DOLICAL_CALDAV_MAX_FAILS_LOGIN` | 5 | Échecs de connexion CalDAV permis par identifiant dans la fenêtre |
| `DOLICAL_CALDAV_MAX_FAILS_IP` | 20 | Échecs de connexion CalDAV permis par adresse IP dans la fenêtre |
| `DOLICAL_CALDAV_FAIL_WINDOW` | 15 | Fenêtre des échecs CalDAV, en minutes |
| `DOLICAL_BOOKING_HUB` | 0 | 1 : la page de prise de rendez-vous sans personne liste les personnes proposées |

## Prérequis

Dolibarr 23 ou 24, modules Agenda et Tiers. Aucun module externe.

## Style et scripts

Le style est dans `css/dolical.css`, les scripts dans `js/dolical.js` et `js/modules/`. Ils sont chargés tels quels,
sans compilation ; leur adresse porte la date du fichier, les navigateurs reprennent donc une version modifiée.
