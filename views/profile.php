<main>
  <?php // SECTION NOTIFICATIONS : S'affiche uniquement si l'utilisateur est le propriétaire du profil et a des notifications non lues. 
  ?>
  <?php if ($isOwner && !empty($notificationsNonLues)): ?>
    <div class="container mt-3">
      <div class="alert alert-info">
        <h5 class="alert-heading">Notifications</h5>
        <ul class="mb-0">
          <?php foreach ($notificationsNonLues as $notif): ?>
            <li><?= htmlspecialchars($notif['message']) ?> <small class="text-muted">(le <?= date('d/m/Y', strtotime($notif['date_creation'])) ?>)</small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="bg-primary container mb-5 ">
    <br />
    <div class="row">
      <?php // COLONNE DE GAUCHE : Informations générales sur l'utilisateur. 
      ?>
      <div class="col-md-4 text-center text-black">
        <h3 class="bg-secondary text-center"><?= htmlspecialchars($user['pseudo'] ?? 'PseudoUtilisateur') ?></h3>
        <p class="text-muted"><?= htmlspecialchars($type_utilisateur ?? '') ?></p>

        <?php // Affiche la note moyenne uniquement si l'utilisateur est un chauffeur. 
        ?>
        <?php if ($user['est_chauffeur']): ?>
          <?php if (isset($user['note_moyenne']) && $user['note_moyenne'] !== null): ?>
            <p><i class="fas fa-star text-warning"></i> <?= htmlspecialchars($user['note_moyenne']) ?> / 5</p>
          <?php else: ?>
            <p class="text-muted">Aucun avis reçu pour le moment.</p>
          <?php endif; ?>
        <?php endif; ?>

        <p class="fst-italic"><?= htmlspecialchars($user['description'] ?? 'Pas de description.') ?></p>

        <?php // Affiche les préférences de voyage si elles existent. 
        ?>
        <?php if (!empty($preferences)): ?>
          <div class="mt-3">
            <h6 class="text-black">Préférences de voyage :</h6>
            <?php foreach ($preferences as $pref): ?>
              <span class="badge bg-dark m-1"><?= htmlspecialchars($pref) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php // Affiche les crédits et les boutons d'action uniquement au propriétaire du profil. 
        ?>
        <?php if ($isOwner): ?>
          <p>Crédits : <strong><?= htmlspecialchars(number_format($user['credit'], 2, ',', ' ')) ?></strong></p>
          <a href="/buy-credits" class="btn btn-dark btn-sm mb-3">Acheter des crédits</a>
          <div class="d-flex justify-content-center mt-3">
            <a href="/profile/edit" class="btn btn-secondary">Modifier le profil & Préférences</a>
          </div>
        <?php endif; ?>
      </div>

      <?php // COLONNE DE DROITE : Contenu principal (véhicules, formulaires, historiques). 
      ?>
      <div class="col-md-8">

        <?php // Affiche les véhicules de l'utilisateur s'il est chauffeur. 
        ?>
        <?php if ($user['est_chauffeur']): ?>
          <?php if (!empty($vehicules)): ?>
            <?php foreach ($vehicules as $vehicule): ?>
              <div class="card border-dark mb-4">
                <div class="card-header bg-dark text-white">Véhicule</div>
                <div class="card-body bg-primary text-black">
                  <p><strong>Marque :</strong> <?= htmlspecialchars($vehicule['marque']) ?></p>
                  <p><strong>Modèle :</strong> <?= htmlspecialchars($vehicule['modele']) ?></p>
                  <p><strong>Énergie :</strong> <?= htmlspecialchars($vehicule['energie']) ?></p>
                  <p><strong>Couleur :</strong> <?= htmlspecialchars($vehicule['couleur']) ?></p>
                  <p><strong>Plaque :</strong> <?= htmlspecialchars($vehicule['plaque_immatriculation']) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-info">Aucun véhicule enregistré.</div>
          <?php endif; ?>
        <?php endif; ?>

        <?php // Affiche le formulaire pour proposer un trajet uniquement au propriétaire du profil, s'il est chauffeur et a des véhicules. 
        ?>
        <?php if ($isOwner && $user['est_chauffeur']): ?>
          <?php if (!empty($vehicules)): ?>
            <div class="card border-dark mb-4">
              <div class="card-header bg-dark text-white">Proposer un nouveau trajet</div>
              <div class="card-body bg-primary">
                <form action="/api/ajouterTrajet" method="POST" class="row g-3">
                  <div class="col-md-6">
                    <label for="depart" class="form-label">Lieu de départ</label>
                    <input type="text" class="form-control" id="depart" name="depart" required>
                  </div>
                  <div class="col-md-6">
                    <label for="arrivee" class="form-label">Lieu d'arrivée</label>
                    <input type="text" class="form-control" id="arrivee" name="arrivee" required>
                  </div>
                  <div class="col-md-6">
                    <label for="date_depart" class="form-label">Date et heure de départ</label>
                    <input type="datetime-local" class="form-control" id="date_depart" name="date_depart" required>
                  </div>
                  <div class="col-md-6">
                    <label for="date_arrivee" class="form-label">Date et heure d'arrivée</label>
                    <input type="datetime-local" class="form-control" id="date_arrivee" name="date_arrivee" required>
                  </div>
                  <div class="col-md-6">
                    <label for="prix" class="form-label">Prix (crédits)</label>
                    <input type="number" step="1" class="form-control" id="prix" name="prix" required min="2">
                  </div>
                  <div class="col-md-6">
                    <label for="places_disponibles" class="form-label">Places disponibles</label>
                    <input type="number" class="form-control" id="places_disponibles" name="places_disponibles" required min="1">
                  </div>
                  <div class="col-md-6">
                    <label for="vehicule_id" class="form-label">Véhicule utilisé</label>
                    <select class="form-select" id="vehicule_id" name="vehicule_id" required>
                      <option selected disabled value="">Choisir un véhicule</option>
                      <?php foreach ($vehicules as $vehicule): ?>
                        <option value="<?= htmlspecialchars($vehicule['id']) ?>" data-energie="<?= htmlspecialchars($vehicule['energie']) ?>">
                          <?= htmlspecialchars($vehicule['marque']) ?> - <?= htmlspecialchars($vehicule['modele']) ?> (<?= htmlspecialchars($vehicule['energie']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 form-check form-switch d-flex align-items-center mt-4">
                    <input class="form-check-input" type="checkbox" id="est_ecologique" name="est_ecologique" value="1">
                    <label class="form-check-label ms-2" for="est_ecologique">Trajet écologique</label>
                  </div>
                  <div class="col-12 text-center">
                    <button type="submit" class="btn btn-dark">Ajouter le trajet</button>
                  </div>
                </form>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-warning">
              Veuillez d'abord enregistrer un véhicule sur la page "Modifier le profil" pour pouvoir proposer un trajet.
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php // Affiche les réservations en attente de confirmation pour le chauffeur propriétaire. 
        ?>
        <?php if ($isOwner && $user['est_chauffeur']): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Réservations en attente</div>
            <div class="card-body bg-primary">
              <?php if (!empty($reservationsEnAttente)): ?>
                <ul class="list-group">
                  <?php foreach ($reservationsEnAttente as $reservation): ?>
                    <li class="list-group-item bg-primary border-black text-black">
                      <strong><?= htmlspecialchars($reservation['passager_pseudo']) ?></strong> a réservé le trajet
                      <?= htmlspecialchars($reservation['depart']) ?> → <?= htmlspecialchars($reservation['arrivee']) ?>
                      (<?= date('d M Y H:i', strtotime($reservation['date_depart'])) ?>)
                      <form action="/confirmer-reservation" method="POST" class="mt-2 d-flex gap-2">
                        <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                        <select name="statut" class="form-select w-auto">
                          <option value="confirmée">Confirmer</option>
                          <option value="refusée">Refuser</option>
                        </select>
                        <button type="submit" class="btn btn-dark btn-sm">Valider</button>
                      </form>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="alert alert-info mb-0">Aucune réservation en attente.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php // Affiche l'historique des trajets proposés par le chauffeur. C'est visible par tout le monde. 
        ?>
        <?php // Affiche l'historique des trajets proposés par le chauffeur, visible par tous. 
        ?>
        <?php if ($user['est_chauffeur']): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Historique des trajets proposés</div>
            <div class="card-body bg-primary">
              <?php if (!empty($trajetsProposes)): ?>
                <ul class="list-group">
                  <?php foreach ($trajetsProposes as $trajet): ?>
                    <?php // Chaque trajet est un élément de la liste <li> 
                    ?>
                    <li class="list-group-item bg-primary border-black text-black">

                      <?php // Ligne 1 : Informations principales et statut du trajet (visible par tous) 
                      ?>
                      <div class="d-flex justify-content-between align-items-center">
                        <span>
                          <?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?>
                          <br>
                          <small><?= date('d M Y H:i', strtotime($trajet['date_depart'])) ?></small>
                        </span>
                        <span>
                          <?php
                          // On prépare la vignette de statut du TRAJET
                          $trajetBadgeClass = '';
                          $trajetBadgeText = '';
                          switch ($trajet['statut']) {
                            case 'planifié':
                              $trajetBadgeClass = 'bg-primary border border-dark text-dark';
                              $trajetBadgeText = 'Planifié';
                              break;
                            case 'en_cours':
                              $trajetBadgeClass = 'bg-warning text-dark';
                              $trajetBadgeText = 'En cours';
                              break;
                            case 'terminé':
                              $trajetBadgeClass = 'bg-secondary';
                              $trajetBadgeText = 'Terminé';
                              break;
                            case 'annulé':
                              $trajetBadgeClass = 'bg-danger';
                              $trajetBadgeText = 'Annulé';
                              break;
                          }
                          echo '<span class="badge ' . $trajetBadgeClass . '">' . $trajetBadgeText . '</span>';
                          ?>
                        </span>
                      </div>

                      <?php // Ligne 2 : Boutons d'action pour le propriétaire OU pour le visiteur 
                      ?>
                      <div class="text-end mt-2">
                        <?php if ($isOwner): ?>
                          <?php // Si le visiteur est le propriétaire, il voit les boutons de gestion 
                          ?>
                          <?php if ($trajet['statut'] == 'planifié'): ?>
                            <button class="btn btn-sm btn-success" onclick="startTrajet(<?= $trajet['id'] ?>)">Démarrer</button>
                            <button class="btn btn-sm btn-danger" onclick="cancelTrajet(<?= $trajet['id'] ?>)">Annuler</button>
                          <?php elseif ($trajet['statut'] == 'en_cours'): ?>
                            <button class="btn btn-sm btn-warning" onclick="endTrajet(<?= $trajet['id'] ?>)">Arrivée</button>
                          <?php endif; ?>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                          <?php // Si le visiteur est un autre utilisateur connecté, il voit le bouton pour réserver 
                          ?>
                          <?php
                          $statut_ma_reservation = $mes_reservations[$trajet['id']] ?? null;
                          ?>
                          <?php if ($statut_ma_reservation === 'en_attente'): ?>
                            <button class="btn btn-sm btn-warning" disabled>En attente</button>
                          <?php elseif ($statut_ma_reservation === 'confirmée'): ?>
                            <button class="btn btn-sm btn-success" disabled>Réservé</button>
                          <?php elseif ($trajet['statut'] == 'planifié' && $trajet['places_disponibles'] > 0): ?>
                            <button type="button" class="btn btn-sm btn-dark"
                              onclick="openBookingModal(this)"
                              data-trajet-id="<?= $trajet['id'] ?>"
                              data-trajet-info="<?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?>"
                              data-trajet-prix="<?= htmlspecialchars(floatval($trajet['prix'])) ?>">
                              Réserver
                            </button>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>

                      <?php // Ligne 3 : Liste des passagers (visible uniquement par le propriétaire et si le trajet n'est pas annulé) 
                      ?>
                      <?php if ($isOwner && !empty($trajet['passagers']) && $trajet['statut'] != 'annulé'): ?>
                        <div class="mt-3 ps-3 border-top pt-2">
                          <h6 class="h6 small">Passagers inscrits :</h6>
                          <ul class="list-unstyled mb-0">
                            <?php foreach ($trajet['passagers'] as $passager): ?>
                              <li>
                                <?= htmlspecialchars($passager['passager_pseudo']) ?>
                                <?php
                                $badgeClass = '';
                                $badgeText = '';
                                switch ($passager['statut']) {
                                  case 'en_attente':
                                    $badgeClass = 'bg-warning text-dark';
                                    $badgeText = 'En attente';
                                    break;
                                  case 'confirmée':
                                    $badgeClass = 'bg-info';
                                    $badgeText = 'Confirmé';
                                    break;
                                  case 'refusée':
                                    $badgeClass = 'bg-secondary';
                                    $badgeText = 'Refusé';
                                    break;
                                }
                                if ($badgeText) {
                                  echo '<span class="badge ms-2 ' . $badgeClass . '">' . $badgeText . '</span>';
                                }
                                ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        </div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="alert alert-info mb-0">Aucun trajet proposé pour le moment.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php // Affiche l'historique des réservations du passager, visible uniquement par lui-même. 
        ?>
        <?php if ($isOwner && $user['est_passager']): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Historique des trajets réservés</div>
            <div class="card-body bg-primary">
              <?php if (!empty($trajetsReserves)): ?>
                <ul class="list-group">
                  <?php foreach ($trajetsReserves as $trajet): ?>
                    <li class="list-group-item bg-primary border-black text-black">
                      <p>
                        <?= htmlspecialchars($trajet['depart'] ?? '') ?> → <?= htmlspecialchars($trajet['arrivee'] ?? '') ?> avec <strong><?= htmlspecialchars($trajet['chauffeur_pseudo'] ?? '') ?></strong>
                        <br>
                        <small>Le <?= date('d M Y H:i', strtotime($trajet['date_depart'])) ?></small>
                      </p>
                      <?php
                      // Cas prioritaire : si le trajet est terminé et que le passager n'a pas encore validé, on affiche le formulaire de notation.
                      if ($trajet['trajet_statut'] == 'terminé' && $trajet['reservation_statut'] == 'confirmée') {
                      ?>
                        <div class="mt-3 p-3 border rounded bg-light">
                          <p>Le trajet est terminé. Comment s'est-il passé ?</p>
                          <form onsubmit="validateTrajet(event, <?= $trajet['reservation_id'] ?>)">
                            <div class="mb-2">
                              <label for="note_<?= $trajet['reservation_id'] ?>">Votre note (sur 5) :</label>
                              <input type="number" id="note_<?= $trajet['reservation_id'] ?>" name="note" class="form-control" min="1" max="5" required>
                            </div>
                            <div class="mb-2">
                              <label for="commentaire_<?= $trajet['reservation_id'] ?>">Votre commentaire (optionnel) :</label>
                              <textarea id="commentaire_<?= $trajet['reservation_id'] ?>" name="commentaire" class="form-control"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-dark">Valider et envoyer l'avis</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="reportIncident(<?= $trajet['reservation_id'] ?>)">Signaler un problème</button>
                          </form>
                        </div>
                      <?php
                      } else {
                        // Pour tous les autres cas, on affiche une vignette de statut.
                        $badgeClass = '';
                        $badgeText = '';
                        switch ($trajet['reservation_statut']) {
                          case 'en_attente':
                            $badgeClass = 'bg-warning text-dark';
                            $badgeText = 'En attente de confirmation';
                            break;
                          case 'confirmée':
                            $badgeClass = 'bg-info';
                            $badgeText = 'Réservation confirmée';
                            break;
                          case 'validée':
                            $badgeClass = 'bg-success';
                            $badgeText = 'Trajet validé';
                            break;
                          case 'annulée':
                          case 'refusée':
                            $badgeClass = 'bg-danger';
                            $badgeText = 'Réservation annulée/refusée';
                            break;
                          case 'en_litige':
                            $badgeClass = 'bg-danger';
                            $badgeText = 'Litige en cours';
                            break;
                        }
                        if ($badgeText) {
                          echo '<span class="badge ' . $badgeClass . '">' . $badgeText . '</span>';
                        }

                        // Et on affiche le bouton d'annulation si les conditions sont remplies.
                        if ($trajet['trajet_statut'] == 'planifié' && $trajet['reservation_statut'] == 'confirmée') {
                          echo '<button class="btn btn-sm btn-outline-danger ms-3" onclick="cancelReservation(' . $trajet['reservation_id'] . ')">Annuler ma réservation</button>';
                        }
                      }
                      ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="alert alert-info text-center mb-0">
                  <p>Aucun trajet réservé pour le moment.</p>
                  <a href="/covoiturage" class="btn btn-dark">Rechercher un trajet</a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php // Affiche les avis sur le chauffeur, visibles par tous. 
        ?>
        <?php if (!empty($avis)): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Avis des utilisateurs</div>
            <div class="card-body bg-primary text-black">
              <?php foreach ($avis as $a): ?>
                <div class="mb-3">
                  <p><strong><?= htmlspecialchars($a['auteur'] ?? '') ?> :</strong> <?= str_repeat('⭐', $a['note']) ?></p>
                  <p><?= htmlspecialchars($a['commentaire'] ?? '') ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <?php if ($user['est_chauffeur']): ?>
            <div class="alert alert-info">Aucun avis pour le moment.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<script>
  // Ce script s'exécute quand la page est chargée.
  document.addEventListener('DOMContentLoaded', function() {
    // Logique pour cocher automatiquement la case "écologique" si un véhicule électrique est choisi.
    const vehiculeSelect = document.getElementById('vehicule_id');
    const estEcologiqueCheckbox = document.getElementById('est_ecologique');
    if (vehiculeSelect && estEcologiqueCheckbox) {
      function updateEcologiqueStatus() {
        const selectedOption = vehiculeSelect.options[vehiculeSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
          const energie = selectedOption.getAttribute('data-energie');
          if (energie === 'electrique') {
            estEcologiqueCheckbox.checked = true;
            estEcologiqueCheckbox.disabled = true;
          } else {
            estEcologiqueCheckbox.checked = false;
            estEcologiqueCheckbox.disabled = false;
          }
        }
      }
      vehiculeSelect.addEventListener('change', updateEcologiqueStatus);
    }
  });

  // Fonction générique pour traiter les réponses des appels API (fetch).
  function handleApiResponse(response) {
    return response.json().then(data => {
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert('Erreur: ' + (data.message || 'Une erreur est survenue.'));
      }
    }).catch(error => {
      console.error('Erreur:', error);
      alert('Une erreur de communication est survenue.');
    });
  }

  // Fonction pour démarrer un trajet (appelée par le bouton du chauffeur).
  function startTrajet(trajetId) {
    if (!confirm("Voulez-vous vraiment démarrer ce trajet ?")) return;
    fetch('/api/startTrajet', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        trajet_id: trajetId
      })
    }).then(handleApiResponse);
  }

  // Fonction pour terminer un trajet (appelée par le bouton du chauffeur).
  function endTrajet(trajetId) {
    if (!confirm("Confirmez-vous être arrivée à destination ?")) return;
    fetch('/api/endTrajet', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        trajet_id: trajetId
      })
    }).then(handleApiResponse);
  }

  // Fonction pour valider un trajet et laisser un avis (appelée par le formulaire du passager).
  function validateTrajet(event, reservationId) {
    event.preventDefault();
    const form = event.target;
    const note = form.querySelector('input[name="note"]').value;
    const commentaire = form.querySelector('textarea[name="commentaire"]').value;
    fetch('/api/validateTrajet', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        reservation_id: reservationId,
        note: note,
        commentaire: commentaire
      })
    }).then(handleApiResponse);
  }

  // Fonction pour signaler un incident (appelée par le bouton du passager).
  function reportIncident(reservationId) {
    const reason = prompt("Veuillez décrire brièvement le problème rencontré :");
    if (reason === null || reason.trim() === "") {
      alert("Le signalement a été annulé.");
      return;
    }
    fetch('/api/reportIncident', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        reservation_id: reservationId,
        commentaire: reason
      })
    }).then(handleApiResponse);
  }

  // Fonction pour qu'un chauffeur annule son trajet.
  function cancelTrajet(trajetId) {
    if (!confirm("Êtes-vous sûre de vouloir annuler ce trajet ? Tous les passagers seront remboursés et notifiés.")) return;
    fetch('/api/cancelTrajet', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        trajet_id: trajetId
      })
    }).then(handleApiResponse);
  }

  // Fonction pour qu'un passager annule sa réservation.
  function cancelReservation(reservationId) {
    if (!confirm("Êtes-vous sûre de vouloir annuler votre réservation ?")) return;
    fetch('/api/cancelReservation', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        reservation_id: reservationId
      })
    }).then(handleApiResponse);
  }
</script>