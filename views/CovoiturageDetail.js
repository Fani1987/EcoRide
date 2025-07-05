export default async function CovoiturageDetail() {
  const mainPageDiv = document.getElementById("main-page");
  const params = new URLSearchParams(window.location.search);
  const trajetId = params.get("id");

  if (!trajetId) {
    app.innerHTML = "<p>Trajet introuvable.</p>";
    return;
  }

  try {
    const response = await fetch(`/api/trajet.php?id=${trajetId}`);
    const data = await response.json();

    if (!data.trajet) {
      app.innerHTML = "<p>Aucun détail trouvé pour ce trajet.</p>";
      return;
    }

    const { trajet, conducteur, vehicule, preferences, avis } = data;

    mainPageDiv.innerHTML = `
      <div class="container mt-4">
        <h2>Détails du covoiturage</h2>
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">${trajet.depart} → ${trajet.arrivee}</h5>
            <p class="card-text">
              <strong>Date :</strong> ${trajet.date_depart}<br>
              <strong>Heure d'arrivée :</strong> ${trajet.date_arrivee}<br>
              <strong>Durée :</strong> ${trajet.duree}<br>
              <strong>Places disponibles :</strong> ${
                trajet.places_disponibles
              }<br>
              <strong>Prix :</strong> ${trajet.prix} €<br>
              <strong>Type :</strong> ${
                trajet.est_ecologique ? "🌱 Écologique" : "Standard"
              }
            </p>
          </div>
        </div>

        <h4>Conducteur</h4>
        <div class="card mb-3">
          <div class="card-body">
            <p><strong>${conducteur.pseudo}</strong> (Note : ${
      conducteur.note_moyenne ?? "Non noté"
    })</p>
            <p>${conducteur.description ?? ""}</p>
            <p><strong>Véhicule :</strong> ${vehicule.marque} ${
      vehicule.modele
    } (${vehicule.couleur}, ${vehicule.energie})</p>
            <p><strong>Préférences :</strong> ${
              preferences.join(", ") || "Aucune"
            }</p>
          </div>
        </div>

        <h4>Avis</h4>
        <ul class="list-group mb-4">
          ${
            avis.length > 0
              ? avis
                  .map(
                    (a) => `
            <li class="list-group-item">
              <strong>${a.auteur}</strong> : ${a.note}/5<br>
              ${a.commentaire}
            </li>
          `
                  )
                  .join("")
              : '<li class="list-group-item">Aucun avis pour ce conducteur.</li>'
          }
        </ul>

        <a href="/reserver?id=${
          trajet.id
        }" class="btn btn-primary">Réserver ce trajet</a>
      </div>
    `;
  } catch (error) {
    console.error(error);
    app.innerHTML = "<p>Erreur lors du chargement des données.</p>";
  }
}
