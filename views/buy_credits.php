<main>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Acheter des Crédits</h1>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form action="/buy-credits" method="POST">
                            <h5 class="card-title">1. Choisissez votre pack</h5>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_pack" id="pack10" value="10" checked>
                                <label class="form-check-label" for="pack10">
                                    10 crédits pour 10,00 €
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_pack" id="pack25" value="25">
                                <label class="form-check-label" for="pack25">
                                    25 crédits pour 25,00 €
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_pack" id="pack50" value="50">
                                <label class="form-check-label" for="pack50">
                                    50 crédits pour 50,00 €
                                </label>
                            </div>

                            <hr>

                            <h5 class="card-title mt-4">2. Simulation de paiement</h5>
                            <p class="text-muted"><small>Ceci est une simulation. N'entrez aucune information bancaire réelle.</small></p>

                            <div class="mb-3">
                                <label for="fake_card_number" class="form-label">Numéro de carte factice</label>
                                <input type="text" class="form-control" id="fake_card_number" value="4242 4242 4242 4242" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fake_expiry_date" class="form-label">Date d'expiration factice</label>
                                    <input type="text" class="form-control" id="fake_expiry_date" value="12/26" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fake_cvc" class="form-label">CVC factice</label>
                                    <input type="text" class="form-control" id="fake_cvc" value="123" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark">Procéder au paiement</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>