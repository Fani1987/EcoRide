<main>
    <div id="main-page" class="bg-primary container mt-5">
        <h1 class="mb-4">Détails du Covoiturage</h1>
        <!-- Vérification si le trajet existe et affichage des informations -->
        <?php if (isset($trajet) && !empty($trajet)): ?>
            <div class="card border-dark mb-4">
                <div class="card-header bg-dark text-white">
                    <h2 class="h5 mb-0">Trajet de <?= htmlspecialchars($trajet['depart']) ?> à <?= htmlspecialchars($trajet['arrivee']) ?></h2>
                </div>
                <div class="card-body bg-primary text-black">
                    <p><strong>Départ :</strong> <?= htmlspecialchars(date('d M Y H:i', strtotime($trajet['date_depart']))) ?></p>
                    <p><strong>Arrivée :</strong> <?= htmlspecialchars(date('d M Y H:i', strtotime($trajet['date_arrivee']))) ?></p>
                    <p><strong>Durée estimée :</strong> <?= htmlspecialchars($trajet['duree']) ?></p>
                    <p><strong>Prix :</strong> <?= htmlspecialchars(floatval($trajet['prix'], 2, ',', ' ')) ?> crédits</p>
                    <p><strong>Places disponibles :</strong> <?= htmlspecialchars($trajet['places_disponibles']) ?></p>
                    <p><strong>Écologique :</strong> <?= $trajet['est_ecologique'] ? 'Oui <i class="bi bi bi-tree-fill text-success"></i>' : 'Non' ?></p>
                </div>
            </div>
            <!-- Informations sur le chauffeur et le véhicule -->
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
            <!-- Affichage des préférences du chauffeur -->
            <div class="card border-dark mb-4">
                <div class="card-header bg-dark text-white">
                    <h2 class="h5 mb-0">Préférences du Chauffeur</h2>
                </div>
                <div class="card-body bg-primary text-black">
                    <?php if (!empty($preferences)): ?>
                        <?php foreach ($preferences as $pref): ?>
                            <span class="badge bg-dark m-1"><?= htmlspecialchars($pref) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Le chauffeur n'a pas spécifié de préférences.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Affichage des avis sur le chauffeur -->
            <div class="card border-dark mb-4">
                <div class="card-header bg-dark text-white">
                    <h2 class="h5 mb-0">Avis sur le Chauffeur</h2>
                </div>
                <div class="card-body bg-primary text-black">
                    <?php if (!empty($avis)): ?>
                        <ul class="list-group">
                            <?php foreach ($avis as $a): ?>
                                <li class="list-group-item">
                                    <strong><?= htmlspecialchars($a['auteur']) ?></strong> (<?= str_repeat('⭐', $a['note']) ?>)
                                    <p class="mb-0 fst-italic">"<?= htmlspecialchars($a['commentaire']) ?>"</p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Aucun avis n'a encore été laissé pour ce chauffeur.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Affichage des passagers inscrits -->
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
            <!-- Bouton de réservation si l'utilisateur est connecté et n'est pas le chauffeur -->
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $trajet['chauffeur_id']): ?>
                <?php if ($trajet['places_disponibles'] > 0): ?>
                    <form id="reserverTrajetForm" action="/api/reserverTrajet" method="POST" class="text-center mt-4">
                        <input type="hidden" name="trajet_id" value="<?= htmlspecialchars($trajet['id']) ?>">
                        <button type="submit" class="btn btn-dark btn-lg">Réserver ce trajet</button>
                    </form>
                <?php else: ?> <!-- Si le trajet n'a plus de places disponibles -->
                    <div class="alert alert-warning text-center mt-4">Ce trajet n'a plus de places disponibles.</div>
                <?php endif; ?>
            <?php elseif (!isset($_SESSION['user_id'])): ?> <!-- Si l'utilisateur n'est pas connecté -->
                <div class="alert alert-info text-center mt-4">Connectez-vous pour réserver ce trajet.</div>
            <?php else: ?> <!-- Si l'utilisateur est le chauffeur du trajet -->
                <div class="alert alert-primary text-center mt-4">Vous êtes le chauffeur de ce trajet.</div>
            <?php endif; ?>

        <?php else: ?> <!-- Si le trajet n'existe pas -->
            <div class="alert alert-danger" role="alert">
                Le trajet demandé n'a pas été trouvé.
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
    // Script pour gérer la réservation du trajet
    document.addEventListener('DOMContentLoaded', function() {
        const reserverTrajetForm = document.getElementById('reserverTrajetForm');
        if (reserverTrajetForm) {
            reserverTrajetForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Empêche le rechargement de la page

                const formData = new FormData(this); // Récupère les données du formulaire

                fetch(this.action, { // Envoie les données au serveur
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