<main>
  <div class="container bg-primary text-black text-center">
    <!--Formulaire de recherche de covoiturage-->

    <h1 class="mb-3">En route !</h1>

    <p>
      Que vous soyez conducteur ou passager, EcoRide facilite vos déplacements
      tout en respectant l'environnement. <br />
      Commencez votre voyage dès maintenant en recherchant un covoiturage adapté
      à vos besoins.
    </p>
    <div>
      <form action="/covoiturage" method="get">
        <div class="mb-3">
          <label class="TextForm" for="depart">Ville de départ :</label>
          <input class="form" id="depart" name="depart" type="text" required />
        </div>

        <div class="mb-3">
          <label class="TextForm" for="arrivee">Ville d'arrivée :</label>
          <input
            class="form"
            id="arrivee"
            name="arrivee"
            type="text"
            required />
        </div>

        <div class="mb-3">
          <label class="Date" for="date">Date :</label>
          <input class="form" id="date" name="date" type="date" required />
        </div>

        <input class="btn btn-dark" type="submit" value="Rechercher" />
      </form>
    </div>
    <br />

    <!-- Bandeau Notre mission-->
    <h1 class="bg-secondary text-center text-black">Notre mission</h1>

    <!-- Article Notre mission-->
    <article>
      <!--Contenu Notre mission-->
      <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3">
          <div class="order-2 order-md-1 col-md-6">
            <p class="p-lg-4 pt-4">
              EcoRide est plus qu'une simple plateforme de covoiturage.
              <br />
              EcoRide est une plateforme de covoiturage dédiée à la mobilité
              durable.<br />
              Notre mission est de faciliter les déplacements tout en réduisant
              l'empreinte carbone.<br />
              En connectant les conducteurs et les passagers, nous contribuons à
              un avenir plus vert et plus responsable.
            </p>
          </div>

          <div class="order-1 order-md-2 col-md-6">
            <img
              class="imgIllustration"
              src="/assets/Images/Environment.jpg"
              alt="Notre mission" />
          </div>
        </div>
      </div>
    </article>

    <!--Bandeau Nos services-->
    <h1 class="bg-secondary text-center text-black">Nos services</h1>
    <!--Contenu Nos services-->
    <div>
      <p class="text-center p-lg-4 pt-4">
        EcoRide propose une plateforme intuitive pour faciliter le
        covoiturage.<br />
        Que vous soyez conducteur ou passager, vous pouvez facilement trouver un
        trajet qui correspond à vos besoins.<br />
        Nos services incluent la recherche de trajets, la réservation et la
        gestion des trajets en ligne via le site internet ou via l'application
        mobile, ainsi que des options de paiement sécurisées.<br />
        De plus, nous offrons une assistance 24/7 pour garantir une expérience
        sans souci.
      </p>
    </div>

    <!--Bandeau Rejoignez-->
    <h1 class="bg-secondary text-center text-black">Rejoignez le mouvement!</h1>

    <!--Article Rejoignez-->
    <article>
      <div>
        <img
          class="imgIllustration"
          src="/assets/Images/EcoRide logo.jpeg"
          alt="Rejoignez-nous" />
      </div>
      <div>
        <p class="text-center p-lg-4">
          Rejoindre EcoRide, c'est faire partie d'une communauté engagée pour un
          avenir plus vert.
          <br />
          Que vous soyez conducteur ou passager, chaque trajet compte.
          <br />
          Ensemble, nous pouvons réduire les émissions de CO2 et promouvoir une
          mobilité plus durable. Vous disposez d’un véhicule avec lequel vous
          faites des trajets réguliers?
          <br />
          Vous souhaitez diminuer les coûts relatifs à ce déplacement, un voyage
          à venir ou juste rendre vos déplacements plus conviviaux
          <br />
          tout en restant dans une logique d’éco-responsabilité?<br /><br />

          Faites la différance dès à présent en rejoignant la communauté
          fleurissante d'EcoRiders et recevez 20 crédits de bienvenue pour
          commencer à covoiturer!
        </p>
      </div>

      <div class="col text-center mb-4">
        <a href="/register" class="btn btn-dark"> Inscription </a>
      </div>
    </article>
  </div>
</main>