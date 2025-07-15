<main>


  <div class="container">
    <h1 class="bg-secondary text-center text-black m-6">Nouveau compte</h1>
  </div>

  <div class="bg-primary text-black register">
    <form action="/register" method="POST" autocomplete="off">
      <div>
        <label for="pseudo">Pseudo:</label>
        <input class="form" id="pseudo" name="pseudo" type="text" required />
      </div>

      <br />

      <div>
        <label for="email">E-mail :</label>
        <input class="form" id="email" name="email" type="email" required />
      </div>

      <br />

      <div>
        <p class="text-center">
          Pour protéger vos comptes, créez des mots de passe sécurisés. Utilisez
          au moins 12 caractères, mélangez lettres, chiffres et symboles, et
          évitez les informations personnelles.
        </p>
      </div>

      <div>
        <label for="mot_de_passe">Mot de passe :</label>
        <input
          class="form"
          id="mot_de_passe"
          name="mot_de_passe"
          type="password"
          required
          pattern="^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{12,}$"
          title="Le mot de passe doit contenir au moins 12 caractères, avec une lettre, un chiffre et un symbole." />
      </div>

      <div>
        <label for="confirm_pass">Confirmer mot de passe :</label>
        <input
          class="form"
          id="confirm_pass"
          name="confirm_pass"
          type="password"
          required />
      </div>

      <div>
        <label for="type">Type d'utilisateur :</label>
        <select class="form" id="type" name="type" required>
          <option value="">-- Sélectionnez un type --</option>
          <option value="Chauffeur">Chauffeur</option>
          <option value="Passager">Passager</option>
          <option value="Passager/Chauffeur">Passager/Chauffeur</option>
        </select>
      </div>

      <br />

      <div id="vehicleFields" style="display: none">
        <div class="text-center">
          <p>
            Vous souhaitez devenir Eco chauffeur! Merci de nous fournir quelques
            informations supplémentaires :
          </p>
        </div>

        <div>
          <label for="car">Marque du véhicule :</label>
          <input class="form" id="car" name="marque" type="text" />
        </div>

        <br />

        <div>
          <label for="model">Modèle du véhicule :</label>
          <input class="form" id="model" name="modele" type="text" />
        </div>

        <br />

        <div>
          <label for="color">Couleur du véhicule :</label>
          <input class="form" id="color" name="couleur" type="text" />
        </div>

        <br />

        <div>
          <label for="energy">Type de carburant</label>
          <select class="form" id="energy" name="energie">
            <option value="essence">Essence</option>
            <option value="diesel">Diesel</option>
            <option value="electrique">Électrique</option>
            <option value="hybride">Hybride</option>
            <option value="autre">Autre</option>
          </select>
        </div>

        <div>
          <label for="plate">Numéro d'immatriculation :</label>
          <input
            class="form"
            id="plate"
            name="plaque_immatriculation"
            type="text" />
        </div>

        <br />

        <div>
          <label for="immatriculation">Date de première immatriculation :</label>
          <input
            class="form"
            id="immatriculation"
            name="date_premiere_immat"
            type="date" />
        </div>

        <<fieldset class="mb-3">
          <legend>Préférences :</legend>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="prefs[]" value="Fumeur" id="pref_smoke" />
            <label class="form-check-label" for="pref_smoke">Fumeurs acceptés</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="prefs[]" value="Animal" id="pref_animals" />
            <label class="form-check-label" for="pref_animals">Animaux acceptés</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="prefs[]" value="Musique" id="pref_music" />
            <label class="form-check-label" for="pref_music">Musique</label>
          </div>
          </fieldset>

          <div>
            <label for="description">Un mot sur vous :</label>
            <textarea
              class="form"
              id="description"
              name="description"
              rows="4"
              placeholder="Parlez-nous de vous, de vos habitudes, de ce que vous
          aimez..."></textarea>
          </div>
      </div>

      <div class="text-center">
        <button class="btn btn-dark" type="submit">S'inscrire</button>
      </div>
    </form>
  </div>
</main>

<script>
  // Fonction pour définir les champs du véhicule comme requis
  function setVehicleFieldsRequired(isRequired) {
    const fields = [
      "car",
      "model",
      "color",
      "energy",
      "plate",
      "immatriculation",
      "places",
    ];
    fields.forEach((id) => {
      const field = document.getElementById(id);
      if (field) {
        if (isRequired) {
          field.setAttribute("required", "required");
        } else {
          field.removeAttribute("required");
        }
      }
    });
  }
  // Fonction pour afficher/masquer les champs du véhicule en fonction du type d'utilisateur sélectionné
  function toggleVehicleFields() {
    const typeSelect = document.getElementById("type");
    const vehicleFields = document.getElementById("vehicleFields");
    if (typeSelect && vehicleFields) {
      const selectedType = typeSelect.value;
      const showFields =
        selectedType === "Chauffeur" || selectedType === "Passager/Chauffeur";
      vehicleFields.style.display = showFields ? "block" : "none";
      setVehicleFieldsRequired(showFields); // Met à jour les champs requis
    }
  }
  // Initialiser l'affichage des champs du véhicule
  document.addEventListener("DOMContentLoaded", function() {
    const typeSelect = document.getElementById("type");
    if (typeSelect) {
      typeSelect.addEventListener("change", toggleVehicleFields);
      toggleVehicleFields(); // Initialiser à l'ouverture
    }

    // Désactiver la touche Entrée dans le formulaire
    const form = document.querySelector("form");
    if (form) {
      form.addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
          event.preventDefault();
        }
      });
    }
  });
</script>