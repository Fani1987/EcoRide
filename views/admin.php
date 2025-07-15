<main>
  <div class="container mt-5">
    <h2 class="card border-danger bg-primary text-center text-black mb-4">
      ADMINISTRATION
    </h2>

    <h1 class="mb-4">Gestion des comptes</h1>

    <!-- Section création d'employé -->
    <section class="mb-5">
      <h2 class="text-dark">Créer un compte employé</h2>

      <form class="row g-3" method="post" id="formCreateEmployee">
        <div class="col-md-4">
          <label for="nomEmploye" class="text-black">Nom</label>
          <input type="text" class="form-control" id="nomEmploye" name="nom" required />
        </div>
        <div class="col-md-4">
          <label for="emailEmploye" class="text-black">Email</label>
          <input type="email" class="form-control" id="emailEmploye" name="email" required />
        </div>
        <div class="col-md-4">
          <label for="mdpEmploye" class="text-black">Mot de passe</label>
          <input
            type="password"
            class="form-control"
            id="mdpEmploye"
            name="mot_de_passe"
            required />
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-dark">Créer le compte</button>
        </div>
      </form>
    </section>

    <!-- Section suspension de comptes -->
    <section class="mb-5">
      <h2 class="text-dark">Suspendre un compte</h2>
      <form class="row g-3" id="formSuspendAccount">
        <div class="col-md-6">
          <label for="emailCompte" class="text-black">Email du compte</label>
          <input type="email" class="form-control" id="emailCompte" required />
        </div>
        <div class="col-md-6">
          <label for="typeCompte" class="text-black">Type de compte</label>
          <select class="form-select text-black" id="typeCompte">
            <option value="utilisateur">Utilisateur</option>
            <option value="employe">Employé</option>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-danger">Suspendre</button>
        </div>
      </form>
    </section>

    <!-- Section réactivation de comptes -->
    <section class="mb-5">
      <h2 class="text-dark">Réactiver un compte</h2>
      <form class="row g-3" id="formReactivateAccount">
        <div class="col-md-6">
          <label for="emailReactiver" class="text-black">Email du compte</label>
          <input type="email" class="form-control" id="emailReactiver" required />
        </div>
        <div class="col-md-6">
          <label for="typeReactiver" class="text-black">Type de compte</label>
          <select class="form-select text-black" id="typeReactiver">
            <option value="utilisateur">Utilisateur</option>
            <option value="employe">Employé</option>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-success">Réactiver</button>
        </div>
      </form>
    </section>

    <!-- Section graphiques -->
    <h1 class="mb-4">Statistiques</h1>
    <section class="text-center">
      <div class="row">
        <div class="col-md-6 mb-4">
          <h5>Covoiturages par jour</h5>
          <canvas id="graphCovoiturages"></canvas>
        </div>
        <div class="col-md-6 mb-4">
          <h5>Crédits gagnés par jour</h5>
          <canvas id="graphCredits"></canvas>
        </div>
      </div>
      <div class="alert bg-dark text-white">
        <strong>Total de crédits gagnés :</strong>
        <span id="totalCredits">0</span>
      </div>
    </section>
  </div>


  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Gestion des formulaires de création d'employé
    document.getElementById('formCreateEmployee').addEventListener('submit', function(e) {
      e.preventDefault();
      fetch('/api/createEmployee', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          nom: document.getElementById('nomEmploye').value,
          email: document.getElementById('emailEmploye').value,
          mot_de_passe: document.getElementById('mdpEmploye').value
        })
      }).then(res => location.reload());
    });
    // Gestion de la suspension des comptes
    document.getElementById('formSuspendAccount').addEventListener('submit', function(e) {
      e.preventDefault();
      fetch('/api/suspendAccount', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          email: document.getElementById('emailCompte').value,
          type: document.getElementById('typeCompte').value
        })
      }).then(res => location.reload());
    });
    // Gestion de la réactivation des comptes
    document.getElementById('formReactivateAccount').addEventListener('submit', function(e) {
      e.preventDefault();
      fetch('/api/reactivateAccount', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          email: document.getElementById('emailReactiver').value,
          type: document.getElementById('typeReactiver').value
        })
      }).then(res => location.reload());
    });
    // Chargement des statistiques
    fetch('/api/stats')
      .then(response => response.json())
      .then(data => {
        const jours = data.covoiturages_par_jour.map(item => item.jour);
        const nbCovoiturages = data.covoiturages_par_jour.map(item => item.nombre_covoiturages);
        const creditsParJour = data.credits_par_jour.map(item => item.credits_gagnes);

        new Chart(document.getElementById('graphCovoiturages'), {
          type: 'bar',
          data: {
            labels: jours,
            datasets: [{
              label: 'Covoiturages par jour',
              data: nbCovoiturages,
              backgroundColor: 'rgba(75, 192, 192, 0.6)',
              borderColor: 'rgba(75, 192, 192, 1)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Nombre de trajets'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Date'
                }
              }
            }
          }
        });

        new Chart(document.getElementById('graphCredits'), {
          type: 'line',
          data: {
            labels: jours,
            datasets: [{
              label: 'Crédits gagnés par jour',
              data: creditsParJour,
              fill: false,
              borderColor: 'rgba(255, 99, 132, 1)',
              tension: 0.1
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Crédits'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Date'
                }
              }
            }
          }
        });

        document.getElementById('totalCredits').textContent = data.total_credits;
      })
      .catch(error => {
        console.error('Erreur lors du chargement des statistiques :', error);
      });
  </script>
</main>