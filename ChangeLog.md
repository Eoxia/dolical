# ChangeLog

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
