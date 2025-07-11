<main>
  <div class="container">
    <h1 class="bg-secondary text-center text-black m-6">Connexion</h1>
  </div>

  <div class="bg-primary text-black register">
    <form method="post" action="/login">
      <section>
        <div>
          <label for="email">E-mail :</label>
        </div>
        <br />
        <div>
          <input class="form" id="email" name="email" type="email" required />
        </div>
      </section>

      <br /><br />

      <section>
        <div>
          <label for="password">Mot de passe :</label>
        </div>
        <br />
        <div>
          <input
            class="form"
            id="password"
            name="mot_de_passe"
            type="password"
            required />
        </div>
      </section>

      <br /><br />

      <section class="text-center">
        <div>
          <input class="btn btn-dark" type="submit" value="Valider" />
        </div>
      </section>

      <br /><br />

      <section class="text-center">
        <div>
          <a href="/register">Nouveau venu? Créez votre compte en 2 min</a>
        </div>
      </section>
    </form>
  </div>
</main>