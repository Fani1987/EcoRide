<main>
  <div class="bg-primary container mb-5 ">
    <br />
    <div class="row">
      <div class="col-md-4 text-center text-black">
        <h3 class="bg-secondary text-center"><?= htmlspecialchars($user['pseudo'] ?? 'PseudoUtilisateur') ?></h3>
        <p class="text-muted"><?= htmlspecialchars($type_utilisateur ?? '') ?></p>
        <p><i class="fas fa-star text-warning"></i> 4.8 / 5</p>
        <p class="fst-italic"><?= htmlspecialchars($user['description'] ?? 'Pas de description.') ?></p>
      </div>

      <div class="col-md-8">
        <?php if (!empty($vehicules)): ?>
          <?php foreach ($vehicules as $vehicule): ?>
            <div class="card border-dark mb-4">
              <div class="card-header bg-dark text-white b">Véhicule</div>
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
          <?php if ($user['est_chauffeur']): ?>
            <div class="alert alert-info">Aucun véhicule enregistré.</div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="card border-dark mb-4">
          <div class="card-header bg-dark text-white">Proposer un nouveau trajet</div>
          <div class="card-body bg-primary">
            <form action="/api/ajouterTrajet" method="POST" class="row g-3">
              <div class="col-md-6">
                <label for="vehicule" class="form-label">Véhicule utilisé</label>
                <select class="form-select" id="vehicule" name="vehicule">
                  <option selected disabled>Choisir un véhicule</option>
                  <?php foreach ($vehicules as $vehicule): ?>
                    <option value="<?= htmlspecialchars($vehicule['id']) ?>">
                      <?= htmlspecialchars($vehicule['marque']) ?> - <?= htmlspecialchars($vehicule['modele']) ?> (<?= htmlspecialchars($vehicule['energie']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-dark">Ajouter le trajet</button>
              </div>
            </form>
          </div>
        </div>

        <?php if ($user['est_chauffeur'] && !empty($trajets_proposes)): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Historique des trajets proposés</div>
            <div class="card-body bg-primary">
              <ul class="list-group">
                <?php foreach ($trajets_proposes as $trajet): ?>
                  <li class="list-group-item bg-primary border-black text-black">
                    <?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?> - <?= date('d M Y', strtotime($trajet['date_depart'])) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($user['est_passager'] && !empty($trajets_reserves)): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Historique des trajets réservés</div>
            <div class="card-body bg-primary">
              <ul class="list-group">
                <?php foreach ($trajets_reserves as $trajet): ?>
                  <li class="list-group-item bg-primary border-black text-black">
                    <?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?> - <?= date('d M Y', strtotime($trajet['date_depart'])) ?> (Conducteur: <?= htmlspecialchars($trajet['chauffeur_pseudo']) ?>)
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>


        <?php if (!empty($avis)): ?>
          <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">Avis des utilisateurs</div>
            <div class="card-body bg-primary text-black">
              <?php foreach ($avis as $a): ?>
                <div class="mb-3">
                  <p><strong><?= htmlspecialchars($a['auteur']) ?> :</strong> <?= str_repeat('⭐', $a['note']) ?></p>
                  <p><?= htmlspecialchars($a['commentaire']) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-info">Aucun avis pour le moment.</div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</main>