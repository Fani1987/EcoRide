<main>
    <div class="container my-5">
        <h1 class="text-center mb-4">Modifier mon profil</h1>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">

                <form action="/api/updateFullProfile" method="POST">

                    <div class="card border-dark mb-4">
                        <div class="card-header bg-dark text-white">Informations générales</div>
                        <div class="card-body bg-primary">
                            <div class="mb-3">
                                <label for="pseudo" class="form-label">Pseudo</label>
                                <input type="text" class="form-control" id="pseudo" name="pseudo" value="<?= htmlspecialchars($user['pseudo']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($user['description']) ?></textarea>
                            </div>
                        </div>
                    </div>


                    <div class="card border-dark mb-4">
                        <div class="card-header bg-dark text-white">Préférences de voyage</div>
                        <div class="card-body bg-primary">
                            <p>Préférences communes :</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="prefs[]" value="Fumeur" id="pref_fumeur" <?= in_array('Fumeur', $preferences) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pref_fumeur">Fumeurs acceptés</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="prefs[]" value="Animal" id="pref_animal" <?= in_array('Animal', $preferences) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pref_animal">Animaux acceptés</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="prefs[]" value="Musique" id="pref_musique" <?= in_array('Musique', $preferences) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pref_musique">Musique</label>
                            </div>

                            <hr>

                            <div class="mt-3">
                                <label for="custom_prefs" class="form-label">Autres préférences (séparées par une virgule) :</label>
                                <?php
                                $standardPrefs = ['Fumeur', 'Animal', 'Musique'];
                                $customPrefs = array_diff($preferences, $standardPrefs);
                                $customPrefsString = implode(', ', $customPrefs);
                                ?>
                                <input type="text" class="form-control" id="custom_prefs" name="custom_prefs" value="<?= htmlspecialchars($customPrefsString) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button type="submit" class="btn btn-dark">Mettre à jour le profil</button>
                        <a href="/profile" class="btn btn-secondary">Retour au profil</a>
                    </div>

                </form>

            </div>
        </div>
    </div>
</main>