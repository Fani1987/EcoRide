<main>
  <div class="container bg-primary text-black text-center">
    <h1 class="mb-3">Recherche de covoiturages</h1>
    <div class="container mt-4">
      <button
        class="btn btn-outline-secondary filter-toggle bg-dark align-self-lg-center"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#filtreCollapse">
        ☰ Filtres
      </button>

      <div class="collapse d-md-block" id="filtreCollapse">
        <div
          class="filter-bar p-3 bg-secondary rounded shadow-sm mb-4 d-flex justify-content-center">
          <form
            action="/covoiturage"
            method="get"
            class="row gy-3 gx-4 justify-content-center align-items-center w-100"
            style="max-width: 1200px">
            <div class="col-12 col-md-6 col-lg-3">
              <label for="depart" class="form-label bg- rgba(217, 217, 217, 0.5)">Départ</label>
              <input class="form-control" id="depart" name="depart" type="text" value="<?php if (isset($_GET['depart'])) echo htmlspecialchars($_GET['depart']); ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label for="arrivee" class="form-label bg- rgba(217, 217, 217, 0.5)">Arrivée</label>
              <input class="form-control" id="arrivee" name="arrivee" type="text" value="<?php if (isset($_GET['arrivee'])) echo htmlspecialchars($_GET['arrivee']); ?>">
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label for="date" class="form-label bg- rgba(217, 217, 217, 0.5)">Date</label>
              <input class="form-control" id="date" name="date" type="date" value="<?php if (isset($_GET['date'])) echo htmlspecialchars($_GET['date']); ?>">
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label
                for="typeTrajet"
                class="form-label bg- rgba(217, 217, 217, 0.5)">type de trajet</label>
              <select class="form-select" id="typeTrajet" name="typeTrajet">
                <option>Toutes</option>
                <option>Ecologique</option>
                <option>Standard</option>
              </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label for="prixMax" class="form-label bg- rgba(217, 217, 217, 0.5)">Prix maximum (Crédits)</label>
              <input
                class="form-control"
                id="prixMax"
                name="prix_max"
                type="number"
                placeholder="Ex : 20"
                min="0" />
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label for="dureeMax" class="form-label bg- rgba(217, 217, 217, 0.5)">Durée maximum (Heures)</label>
              <input
                class="form-control"
                id="dureeMax"
                name="duree_max"
                type="number"
                placeholder="Ex : 1"
                min="0" />
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label for="noteMin" class="form-label bg- rgba(217, 217, 217, 0.5)">Note minimale</label>
              <input
                class="form-control"
                id="noteMin"
                name="note_min"
                type="number"
                max="10"
                min="0" />
            </div>

            <div class="col-12 text-center">
              <button class="btn btn-dark" type="submit">Appliquer les filtres</button>
            </div>
          </form>
        </div>
      </div>
      <div id="covoiturages-results">
        <?php if (!empty($covoiturages)): ?>
          <?php foreach ($covoiturages as $trajet): ?>
            <div class="card mb-3 p-3 bg-primary border-black text-black text-center">
              <h5><?= htmlspecialchars($trajet['chauffeur_pseudo']) ?></h5>
              <p>
                Note :
                <?= htmlspecialchars($trajet['chauffeur_note'] ?? 'Non noté') ?>
                ★
              </p>
              <p>
                Départ :
                <?= htmlspecialchars($trajet['depart']) ?>
              </p>
              <p>
                Arrivée :
                <?= htmlspecialchars($trajet['arrivee']) ?>
              </p>
              <p>
                Date :
                <?= date('d/m/Y H:i', strtotime($trajet['date_depart'])) ?>
              </p>
              <p>
                Prix :
                <?= htmlspecialchars(floatval($trajet['prix'])) ?>
                Crédits
              </p>
              <p>
                Places disponibles :
                <?= htmlspecialchars($trajet['places_disponibles']) ?>
              </p>
              <p>
                Véhicule :
                <?= htmlspecialchars($trajet['vehicule_marque']) ?>
                <?= htmlspecialchars($trajet['vehicule_modele']) ?>
                <?= htmlspecialchars($trajet['vehicule_energie']) ?>
              </p>
              <p>
                Type de trajet :
                <?= $trajet['est_ecologique'] ? 'Écologique' : 'Standard' ?>
              </p>
              <div class="card mb-3 p-3 bg-primary border-black text-black text-center">
                <div class="mt-3">
                  <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // On vérifie si une réservation existe pour ce trajet et on récupère son statut
                    $statut_ma_reservation = $mes_reservations[$trajet['id']] ?? null;
                    ?>

                    <?php if ($statut_ma_reservation === 'en_attente'): ?>
                      <button class="btn btn-warning" disabled>Réservation en attente</button>
                    <?php elseif ($statut_ma_reservation === 'confirmée'): ?>
                      <button class="btn btn-success" disabled>Réservé</button>
                    <?php elseif ($statut_ma_reservation === 'refusée'): ?>
                      <button class="btn btn-danger" disabled>Réservation refusée</button>
                    <?php elseif ($trajet['places_disponibles'] > 0 && $trajet['chauffeur_id'] != $_SESSION['user_id']): ?>
                      <button type="button" class="btn btn-dark" onclick="openBookingModal(this)"
                        data-trajet-id="<?= $trajet['id'] ?>"
                        data-trajet-info="<?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?>"
                        data-trajet-prix="<?= htmlspecialchars(floatval($trajet['prix'])) ?>">
                        Réserver
                      </button>
                    <?php endif; ?>

                    <a href="/profile?id=<?= htmlspecialchars($trajet['chauffeur_id']) ?>" class="btn btn-secondary">Profil Chauffeur</a>

                  <?php else: ?>
                    <a href="/login" class="btn btn-info">Connectez-vous pour réserver</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-warning text-center">
              <p class="mb-0">Aucun covoiturage trouvé avec vos critères de recherche actuels.</p>

              <?php if (isset($prochaine_date) && $prochaine_date): ?>
                <hr>
                <p class="mb-0">
                  Le prochain départ disponible pour cet itinéraire est le
                  <strong><?= htmlspecialchars(date('d/m/Y', strtotime($prochaine_date))) ?></strong>.
                </p>
                <p>Essayez une nouvelle recherche avec cette date !</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
            </div>



</main>