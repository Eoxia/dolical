# ChangeLog

## Non publié

- Prise de rendez-vous en ligne : une page publique sans compte par personne, où un client choisit un créneau libre
  de son agenda (rendez-vous, congés validés et jours fériés écartés) et laisse ses coordonnées. Le rendez-vous est un
  événement natif de la personne, le client un contact ; il reçoit une confirmation avec le `.ics` et un lien pour
  l'annuler, la personne est prévenue. Réglages : personnes proposées, durée, jours et horaires, et trois textes
  facultatifs (titre de la page, présentation, titre des créneaux), communs ou propres à chaque personne ; « Ma page
  de prise de rendez-vous » sous la liste des agendas donne son lien signé et ses textes ; avec Saturne, un lien
  court (`saturne/s/?code`) à partager.
- Pages publiques (prise de rendez-vous, réponse à une invitation) : le logo de la société seul en en-tête, plus le
  logo ni le « Propulsé par » de Dolibarr.
- Calendrier et créneaux déjà pris rapides sur un grand agenda : seuls les événements commencés au plus 92 jours avant
  la période sont lus (réglage caché `DOLICAL_LONGEST_EVENT_DAYS`), au lieu de tout l'historique (plusieurs secondes
  pour 800 000 événements).
- Formulaire d'un rendez-vous : choisir un contact d'un tiers remplit le champ « Tiers » quand il est encore vide (un
  tiers déjà choisi est gardé). Le texte des champs « Tiers » et « Projet » est centré dans le champ.
- Lien de visio d'un rendez-vous (Teams, Meet, Zoom, Jitsi…) : un champ du formulaire, envoyé avec les invitations
  (bouton « Rejoindre la visio », `.ics` avec le lien en lieu et en conférence, jeton `__DOLICAL_EVENT_VISIO__` des
  modèles), sur la fiche, la page publique de réponse et le téléphone synchronisé. Ajouté ou changé seul, il part dans
  un e-mail à lui (« Lien de la visio »). Prise de rendez-vous en ligne : chaque personne donne le lien de sa salle
  sur sa page, il est mis sur chaque rendez-vous pris et envoyé dans la confirmation. Le client d'un rendez-vous pris
  en ligne reçoit les modifications sous le titre de sa page et avec le lien de son rendez-vous. Champ complémentaire
  `dolical_visio` créé à l'activation : **réactiver le module** après la mise à jour.
- Prise de rendez-vous en ligne : « Réservable jusqu'à N semaines à l'avance » dans la configuration (4 par défaut),
  à la place de la constante cachée `DOLICAL_BOOKING_HORIZON_DAYS` (30 jours).
- Rappels au client avant un rendez-vous pris en ligne : 1 semaine, 2 jours, 1 jour, 2 heures ou 1 heure avant, au
  choix dans la configuration (aucun par défaut), par la nouvelle tâche planifiée « Rappels des rendez-vous en ligne »
  (toutes les 15 minutes, créée à l'activation du module), modèle d'e-mail modifiable.
- Prise de rendez-vous en ligne réglée comme les rendez-vous de Nextcloud, dans son propre onglet de la configuration
  (sections Agendas et planification, Informations de contact, E-mails et notifications, Formulaire, Sécurité,
  Rappels, Visioconférence, Avancé) :
  - **modèle hebdomadaire** des créneaux, dans un onglet à lui et dans la grille de la vue Semaine du calendrier : on
    y pose, déplace, étire et supprime les créneaux à la souris, un menu par jour ajoute une série, copie la journée ou
    la vide. Il remplace les jours cochés et les plages horaires ; une configuration d'avant est lue comme un modèle ;
  - tampons avant et après un rendez-vous, préavis minimum ;
  - e-mail de l'organisateur (réponse du client, organisateur du `.ics`), lieu, téléphone (sur la page et dans les
    e-mails) ;
  - choix des e-mails au client et à la personne, texte en plus de la confirmation, du `.ics` et des rappels ;
  - champ « Téléphone » facultatif, obligatoire ou masqué, case de consentement (RGPD) gardée dans le rendez-vous ;
  - adresses e-mail ou domaines bloqués ; code anti-robot et limites par adresse e-mail et par IP réglables ;
  - lien de visio commun, ou une salle Jitsi nouvelle par rendez-vous ;
  - modèle du titre de l'événement, page de redirection après réservation.
- Pages de configuration (générale et prise de rendez-vous) au design de DoliCal : une carte par section, une ligne
  par réglage avec une aide courte, interrupteurs, sections listées sur le côté et bouton « Enregistrer » toujours
  visible ; un réglage qui dépend d'un autre n'apparaît que lorsqu'il sert.
- Rendez-vous planifié depuis un mail du module Inbox : les dates écrites dans le mail (« mardi 24 novembre à 14h30 »,
  « 26/11 de 9h à 10h30 », « demain 15h », « jeudi prochain », « November 19th at 2:30 pm »…) sont proposées sous les
  dates du formulaire, la première avec ses heures déjà reprise. Seul le dernier message compte (pas les réponses citées
  dessous), une date passée n'est pas proposée. Le mail est lu par Inbox avec le droit « lire un mail » de l'utilisateur.
- Plusieurs dates pour un nouveau rendez-vous : les dates du mail se cochent ensemble, « Autre date » en ajoute à la
  main. Chaque date donne un événement, et plusieurs dates sont « à confirmer » : hachurés dans le calendrier, liés
  entre eux. « Confirmer ce créneau » sur la fiche de l'un retire les autres de l'agenda (les invités prévenus comme
  pour une suppression). La case « À confirmer » du formulaire vaut aussi pour un rendez-vous seul. Champ
  complémentaire `dolical_tentative` créé à l'activation : **réactiver le module** après la mise à jour.
- Fuseau horaire affiché avec son écart à UTC (« Europe/Paris (UTC+01:00) ») : liste de la configuration, prise de
  rendez-vous, modèle hebdomadaire et pages publiques ; l'écart est celui du jour du créneau ou du rendez-vous (heure
  d'été comprise).

## 1.3.0

- Page d'un événement seul (`view/dolical_event.php`) : le formulaire d'un nouveau rendez-vous, ou la fiche d'un
  rendez-vous, sans le calendrier derrière. Faite pour une petite fenêtre : ouverte dans la fenêtre d'une autre
  application, elle lui dit son titre, la saisie non enregistrée, puis l'enregistrement ou la suppression.
- Volet agenda d'une messagerie qui offre le hook `inboxAgendaProvider` : créer et ouvrir un rendez-vous passent par
  DoliCal, avec le tiers, les contacts, le projet et les documents reconnus sur le mail ouvert.
- « Planifier un rendez-vous » sur un mail (hook `inboxMailActions`) : la page d'un nouveau rendez-vous dans une
  fenêtre, avec le sujet du mail en titre, ses correspondants en contacts (le contact connu, sinon un nouveau contact
  proposé) et le lien vers le mail (`email_msgid`).
- Invitation de DoliCal lue dans une messagerie qui offre le hook `inboxInviteEvent` : « Déjà dans votre agenda »
  au lieu d'un ajout en double, et la réponse (Accepter, Peut-être, Refuser) enregistrée comme par le lien de
  l'invitation (réponse native, trace, organisateur prévenu).
- Bloc « Rendez-vous » dans le bandeau d'un mail (hook `inboxMailPanels`) : le prochain et le dernier rendez-vous avec
  le tiers du mail, un compte-rendu attendu à écrire ; rien quand il n'y en a aucun.
- Réactiver le module pour le volet agenda (nouveau contexte de hooks).

## 1.2.0

- Import d'un agenda depuis son fichier `.ics` (Nextcloud, Google Agenda, Outlook, Thunderbird…) : page « Importer un
  agenda », dans son propre agenda ou, avec le droit sur les événements des autres, dans celui d'un collègue. Les
  participants (organisateur compris) sont retrouvés par leur e-mail, ou à défaut par leur nom, avec leur réponse, et
  le tiers est relié ; les répétitions deviennent des séries, les rendez-vous annulés sont laissés de côté, aucun
  e-mail n'est envoyé. Un fichier réimporté ne crée pas de doublon et complète les rendez-vous déjà importés des
  participants et du tiers retrouvés depuis ; le bilan liste les personnes introuvables. Les imports précédents sont
  listés (clé native `import_key` sur leurs événements) et chacun se supprime d'un coup en cas d'erreur.
- Import fidèle à l'agenda d'origine : dates supplémentaires (`RDATE`) importées, date modifiée seule avec son propre
  titre, lieu et description, journées entières répétées justes au changement d'heure, statut « Non applicable » (plus
  de rendez-vous passés « en retard »), et sous la description ce que l'événement ne peut garder : titre ou lieu
  au-delà de 128 caractères, personnes introuvables, catégories, lien de visio.
- Synchronisation : un rendez-vous créé sur le téléphone garde les réponses déjà données par les participants,
  retrouve participants et tiers de la même façon, et une série dont la première date est annulée commence à la
  suivante.
- Le lieu d'un événement est coupé à 128 caractères, la taille du champ natif : au-delà, une base stricte refusait
  l'enregistrement.
- Calendrier : créer un rendez-vous en faisant glisser la souris sur des créneaux vides (horaires au quart d'heure en
  vues Jour et Semaine, plusieurs jours en vue Mois et dans la bande « Toute la journée ») ; le clic garde son effet.
- Fiche d'un événement : le statut d'une facture liée est celui de sa fiche (une facture sans règlement n'est plus
  « Commencée » mais « Impayée »).
- Vue Semaine avec plusieurs agendas : un rendez-vous de plusieurs personnes affichées apparaît une fois pour chacune,
  côte à côte et à sa couleur ; le déplacer garde tous les participants.
- Couleur des personnes pour soi : la palette au bout de chaque ligne de la liste des agendas change la couleur d'un
  collègue (ou la sienne) pour soi seulement, sans toucher à celle des autres ; retour possible à la couleur par défaut.
- Durcissement (#29) : une même personne ne reçoit pas deux fois l'invitation ou la relance d'un événement en 10
  minutes, et les e-mails envoyés depuis le calendrier sont limités par heure, par événement et par personne ;
  l'organisateur reçoit au plus un e-mail toutes les 10 minutes pour les réponses d'un même invité ; une fois
  l'événement passé, le lien d'invitation ne propose plus le `.ics`, et ne vaut plus 30 jours après. Sur la carte,
  l'e-mail et le téléphone d'un contact ou d'un utilisateur ne s'affichent qu'à qui peut lire sa fiche. Nouveau droit
  « Écrire le compte-rendu » pour le participant qui ne peut pas modifier l'événement (réactiver le module, puis
  l'accorder). Import limité à 10 Mo, 5 000 rendez-vous et 10 minutes ; une répétition plus fréquente que le jour
  reste un seul rendez-vous. Jeton comparé en temps constant, et plus envoyé dans l'adresse des lectures. La page de
  configuration signale que les éléments liés sur la fiche native sont un réglage du module Agenda. Toutes les
  limites sont réglables (voir le README).
- Contact créé depuis une adresse e-mail (#28) : rattaché au tiers de l'événement seulement si l'utilisateur peut voir
  ce tiers, sinon sans tiers ; le tiers et le projet sont contrôlés avant toute écriture, et un contact créé pour un
  événement finalement refusé est supprimé.
- Import et synchronisation (#27) : un rendez-vous déjà présent n'est complété (participants, tiers) que par qui peut
  le modifier ; seule la réponse de la personne qui importe ou synchronise est reprise pour les utilisateurs, les
  collègues gardent la leur (« en attente » s'ils n'ont pas répondu), et un contact retrouvé par son seul nom aussi.
- Synchronisation CalDAV (#26) : fermée par défaut ; chacun crée sur « Synchroniser mon téléphone » un mot de passe de
  synchronisation, affiché une fois et révocable, le mot de passe Dolibarr ne l'ouvre plus (les appareils déjà
  connectés doivent recevoir ce nouveau mot de passe) ; hooks de la page de connexion appelés (double
  authentification, bannissement…), au plus 5 échecs par identifiant et 20 par adresse en 15 minutes ; refusée en
  http, sauf option pour une installation de test.
- Séries (#25) : « Cette date et les suivantes » et « Toute la série » vérifient les droits sur chaque date ; une date
  d'une autre personne que l'on ne peut pas modifier ou supprimer reste intacte et le bilan l'indique. Une série est
  limitée à son auteur, aussi pour les séries créées par DoliCal : un événement d'un autre auteur portant le même
  `recurid` n'en fait plus partie.
- Nouvelle entrée de menu : réactiver le module pour la voir.

## 1.1.0

- Plus aucune dépendance à Saturne ni à un autre module externe : le module ne demande que Agenda et Tiers, pour
  pouvoir rejoindre le cœur de Dolibarr.
- Style et scripts livrés tels quels (`css/dolical.css`, `js/dolical.js`, `js/modules/`), sans compilation.
- L'icône du menu du haut garde ses couleurs, comme dans la liste des modules (`css/dolical_menu.css`).
- Le compte-rendu redevient un texte : la dictée, le bloc médias et la médiathèque sont retirés ; les fichiers du
  rendez-vous restent ses pièces jointes.

## 1.0.0

- Calendrier d'équipe sur les événements natifs : vues Jour (une colonne par personne), Semaine, Équipe, Mois et Liste.
- Sélection de plusieurs agendas, par personne ou par groupe d'utilisateurs, mémorisée par navigateur.
- Glisser-déposer : déplacement, durée, passage d'une personne à une autre.
- Formulaire : participants internes et externes, tiers, projet, plusieurs documents liés, pièces jointes.
- Carte de l'événement : participants et réponses, facturation (devis, factures, reste à payer), pièces jointes.
- Bouton « Planifier dans l'agenda » sur les fiches des documents, tiers, projets et contacts.
- Invitations par e-mail : contact créé depuis une simple adresse, lien de réponse signé vers une page publique,
  fichier `.ics`, e-mail de modification, notification de l'organisateur, modèles d'e-mails Dolibarr.
- Annulation envoyée aux invités (suppression, participant retiré), relance manuelle et automatique des invités sans
  réponse, réponses visibles dans les vues, avertissement de conflit de disponibilité dans le formulaire.
- Rappels natifs dans le formulaire : interrupteur, délai, notification du navigateur et/ou e-mail, pour soi ou pour
  tous ; ils suivent l'événement déplacé.
- Événements récurrents : séries natives (`recurid`), modification et suppression de « cet événement », « celui-ci et
  les suivants » ou « tous », un seul e-mail par série pour les invités (`RRULE`, fuseau, exceptions).
- Compte-rendu après le rendez-vous : proposé sur la carte et dans le calendrier, écrit ou dicté (mémo vocal rédigé
  par le module IA), photos, documents, médiathèque et glisser-déposer via le bloc médias de Saturne.
- Synchronisation CalDAV dans les deux sens (DAVx⁵, iPhone, Mac, Thunderbird), agendas de collègues en lecture
  seule, page « Synchroniser mon téléphone ».
