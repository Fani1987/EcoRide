<main>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <h1 class="text-center mb-4">Contactez-nous</h1>
                <p class="text-center text-muted mb-4">
                    Une question, une suggestion ou un problème ? N'hésitez pas à nous envoyer un message.
                </p>

                <div class="card shadow-sm border-dark">
                    <div class="card-body bg-primary  p-4">
                        <form action="/contact" method="POST">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Votre nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Votre adresse e-mail</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="sujet" class="form-label">Sujet</label>
                                <input type="text" class="form-control" id="sujet" name="sujet" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Votre message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark">Envoyer le message</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>