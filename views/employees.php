<main>
  <div class="container mt-5">
    <h2 class="card border-dark bg-primary text-center text-black mb-4">
      ESPACE EMPLOYES
    </h2>

    <h1 class="mb-4">Gestion des avis</h1>

    <section class="mb-5">
      <h2 class="text-dark">Avis à valider</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-hover responsive-table">
          <thead class="table-primary">
            <tr>
              <th>Nom</th>
              <th>Note</th>
              <th>Avis</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <?php
            // La logique SQL est maintenant dans le contrôleur.
            // On utilise la variable $avisEnAttente fournie par EmployeeController::showDashboard
            if (empty($avisEnAttente)) {
              echo '<tr><td colspan="4" class="text-center text-muted">Aucun avis en attente de validation.</td></tr>';
            } else {
              foreach ($avisEnAttente as $avisItem) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($avisItem['nom']) . '</td>';
                echo '<td>' . htmlspecialchars($avisItem['note']) . '/5</td>';
                echo '<td>' . htmlspecialchars($avisItem['commentaire']) . '</td>';
                echo '<td>
            <form method="POST" action="/api/validateAvis" style="display:inline;">
                <input type="hidden" name="avis_id" value="' . $avisItem['id'] . '">
                <button type="submit" class="btn btn-dark btn-sm">Valider</button>
            </form>
            <form method="POST" action="/api/refuseAvis" style="display:inline;">
                <input type="hidden" name="avis_id" value="' . $avisItem['id'] . '">
                <button type="submit" class="btn btn-danger btn-sm">Refuser</button>
            </form>
        </td>';
                echo '</tr>';
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>
    <section>
      <h2 class="text-dark">Avis validés</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-hover responsive-table">
          <thead class="table-primary">
            <tr>
              <th>Nom</th>
              <th>Note</th>
              <th>Avis</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // On utilise la variable $avisValides fournie par le contrôleur
            if (empty($avisValides)) {
              echo '<tr><td colspan="3" class="text-center text-muted">Aucun avis validé pour le moment.</td></tr>';
            } else {
              foreach ($avisValides as $avisItem) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($avisItem['nom']) . '</td>';
                echo '<td>' . htmlspecialchars($avisItem['note']) . '/5</td>';
                echo '<td>' . htmlspecialchars($avisItem['commentaire']) . '</td>';
                echo '</tr>';
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>

    <h1 class="mb-4">Gestion des incidents</h1>
    <section class="mt-5">
      <h2 class="text-dark">Covoiturages signalés</h2>
      <div class="table-responsive">
        <?php if (empty($incidentsOuverts)): // On utilise la variable $incidentsOuverts 
        ?>
          <div class="alert alert-info">Aucun incident signalé à traiter.</div>
        <?php else: ?>
          <table class="table table-bordered table-hover responsive-table">
            <thead class="table-primary">
              <tr>
                <th>#ID</th>
                <th>Conducteur</th>
                <th>Passager</th>
                <th>Date</th>
                <th>Lieu</th>
                <th>Description</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($incidentsOuverts as $incident): // On utilise la variable $incidentsOuverts 
              ?>
                <tr>
                  <td><?= htmlspecialchars($incident['covoiturage_id']) ?></td>
                  <td><?= htmlspecialchars($incident['conducteur_pseudo']) ?></td>
                  <td><?= htmlspecialchars($incident['passager_pseudo']) ?></td>
                  <td><?= htmlspecialchars($incident['date_trajet']) ?></td>
                  <td><?= htmlspecialchars($incident['lieu']) ?></td>
                  <td><?= htmlspecialchars($incident['description']) ?></td>
                  <td>
                    <form method="POST" action="/api/markIncidentHandled">
                      <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">
                      <button type="submit" class="btn btn-success btn-sm">Marquer comme traité</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </div>

</main>