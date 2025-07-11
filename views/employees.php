<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
// Chemin vers le fichier de configuration de la base de données
$pdo = Database::getInstance();
// Requête pour récupérer les incidents non traités
$query = "
    SELECT 
        i.id AS incident_id,
        c.id AS covoiturage_id,
        u_conducteur.pseudo AS conducteur_pseudo,
        u_passager.pseudo AS passager_pseudo,
        DATE_FORMAT(c.date_depart, '%d/%m/%Y') AS date_trajet,
        CONCAT(c.depart, ' → ', c.arrivee) AS lieu,
        i.commentaire AS description
    FROM incidents i
    JOIN reservations r ON i.reservation_id = r.id
    JOIN covoiturages c ON r.covoiturage_id = c.id
    JOIN utilisateurs u_conducteur ON c.chauffeur_id = u_conducteur.id
    JOIN utilisateurs u_passager ON r.utilisateur_id = u_passager.id
    WHERE i.statut = 'ouvert'
    ORDER BY i.date_creation DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
  <div class="container mt-5">
    <h2 class="card border-dark bg-primary text-center text-black mb-4">
      ESPACE EMPLOYES
    </h2>

    <h1 class="mb-4">Gestion des avis</h1>

    <!-- Section validation des avis -->
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
            // Requête pour les avis en attente
            $stmt = $pdo->prepare("SELECT a.id, u.pseudo AS nom, a.note, a.commentaire FROM avis a JOIN utilisateurs u ON a.utilisateur_id = u.id WHERE a.statut = 'en_attente'");
            $stmt->execute();
            $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($avis) === 0) {
              echo '<tr><td colspan="4" class="text-center text-muted">Aucun avis en attente de validation.</td></tr>';
            } else {
              foreach ($avis as $avisItem) {
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
    <!-- Section avis validés -->
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
            // Requête pour les avis déjà validés
            $stmt = $pdo->prepare("SELECT u.pseudo AS nom, a.note, a.commentaire FROM avis a JOIN utilisateurs u ON a.utilisateur_id = u.id WHERE a.statut = 'validé'");
            $stmt->execute();
            $avisValides = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($avisValides as $avisItem) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars($avisItem['nom']) . '</td>';
              echo '<td>' . htmlspecialchars($avisItem['note']) . '/5</td>';
              echo '<td>' . htmlspecialchars($avisItem['commentaire']) . '</td>';
              echo '</tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>

    <h1 class="mb-4">Gestion des incidents</h1>
    <!-- Section covoiturages problématiques -->
    <section class="mt-5">
      <h2 class="text-dark">Covoiturages signalés</h2>
      <div class="table-responsive">
        <?php if (empty($incidents)): ?>
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
              <?php foreach ($incidents as $incident): ?>
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

  <!-- Script JS pour actions dynamiques gestion des avis -->
  <script>
    function validateAvis(button) {
      const row = button.closest('tr');
      fetch('/api/validateAvis', {
          method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) row.remove();
          else alert("Erreur : " + data.message);
        });
    }

    function refuseAvis(button) {
      const row = button.closest('tr');
      fetch('/api/refuseAvis', {
          method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) row.remove();
          else alert("Erreur : " + data.message);
        });
    }

    function markIncidentHandled(button) {
      const row = button.closest('tr');
      fetch('/api/markIncidentHandled', {
          method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) row.remove();
          else alert("Erreur : " + data.message);
        });
    }
  </script>
</main>