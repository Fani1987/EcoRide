<!-- views/covoiturage-detail.html -->
<main>
<div id="main-page" class="bg-primary container mt-5">
  <h1 class="mb-4">Détails du Covoiturage</h1>

    <?php if (isset($trajet) && !empty($trajet)): ?>
        <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">
                <h2 class="h5 mb-0">Trajet de <?= htmlspecialchars($trajet['depart']) ?> à <?= htmlspecialchars($trajet['arrivee']) ?></h2>
            </div>
            <div class="card-body bg-primary text-black">
                <p><strong>Date :</strong> <?= htmlspecialchars($trajet['date_depart']) ?></p>
                <p><strong>Heure :</strong> <?php if (isset($trajet['heure_depart']) && $trajet['heure_depart'] !== null): ?>
    <p>Heure de départ : <?= htmlspecialchars($trajet['heure_depart']) ?></p>
<?php else: ?>
    <p>Heure de départ : Non spécifiée</p>
<?php endif; ?></p>
                <p><strong>Prix :</strong> <?= htmlspecialchars(number_format($trajet['prix'], 2, ',', ' ')) ?> crédits</p>
                <p><strong>Durée estimée :</strong> <?= htmlspecialchars($trajet['duree']) ?></p>
                <p><strong>Places disponibles :</strong> <?= htmlspecialchars($trajet['places_disponibles']) ?></p>
                <p><strong>Écologique :</strong> <?= $trajet['est_ecologique'] ? 'Oui <i class="bi bi-tree-fill text-success"></i>' : 'Non' ?></p>
            </div>
        </div>

        <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">
                <h2 class="h5 mb-0">Informations sur le Chauffeur</h2>
            </div>
            <div class="card-body bg-primary text-black">
                <p><strong>Pseudo :</strong> <?= htmlspecialchars($trajet['chauffeur_pseudo']) ?></p>
                <a href="/profile?id=<?= htmlspecialchars($trajet['chauffeur_id']) ?>" class="btn btn-dark btn-sm">Voir le profil</a>
            </div>
        </div>

        <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">
                <h2 class="h5 mb-0">Informations sur le Véhicule</h2>
            </div>
            <div class="card-body bg-primary text-black">
                <p><strong>Marque :</strong> <?= htmlspecialchars($trajet['vehicule_marque']) ?></p>
                <p><strong>Modèle :</strong> <?= htmlspecialchars($trajet['vehicule_modele']) ?></p>
                <p><strong>Couleur :</strong> <?= htmlspecialchars($trajet['vehicule_couleur']) ?></p>
                <p><strong>Immatriculation :</strong> <?= htmlspecialchars($trajet['vehicule_immatriculation']) ?></p>
                </div>
        </div>

        <div class="card border-dark mb-4">
            <div class="card-header bg-dark text-white">
                <h2 class="h5 mb-0">Passagers inscrits</h2>
            </div>
            <div class="card-body bg-primary text-black">
                <?php if (!empty($passagers)): ?>
                    <ul class="list-group">
                        <?php foreach ($passagers as $passager): ?>
                            <li class="list-group-item">
                                <a href="/profile?id=<?= htmlspecialchars($passager['id']) ?>">
                                    <?= htmlspecialchars($passager['pseudo']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Aucun passager n'est encore inscrit pour ce trajet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $trajet['chauffeur_id']): ?>
            <?php if ($trajet['places_disponibles'] > 0): ?>
                <form id="reserverTrajetForm" action="/api/reserverTrajet" method="POST" class="text-center mt-4">
                    <input type="hidden" name="trajet_id" value="<?= htmlspecialchars($trajet['id']) ?>">
                    <button type="submit" class="btn btn-dark btn-lg">Réserver ce trajet</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning text-center mt-4">Ce trajet n'a plus de places disponibles.</div>
            <?php endif; ?>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <div class="alert alert-info text-center mt-4">Connectez-vous pour réserver ce trajet.</div>
        <?php else: ?>
             <div class="alert alert-primary text-center mt-4">Vous êtes le chauffeur de ce trajet.</div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-danger" role="alert">
            Le trajet demandé n'a pas été trouvé.
        </div>
    <?php endif; ?>
</div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reserverTrajetForm = document.getElementById('reserverTrajetForm');
    if (reserverTrajetForm) {
        reserverTrajetForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Empêche le rechargement de la page

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload(); // Recharger la page pour voir les places mises à jour
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la réservation:', error);
                alert('Une erreur est survenue lors de la réservation.');
            });
        });
    }
});
</script>