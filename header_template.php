<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="/assets/main.min.css" />

    <title>EcoRide</title>
</head>

</header>

<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="bg-dark">
            <div class="container-fluid" class="">
                <a class="navbar-brand" href="/">
                    <div class="d-flex align-items-center">
                        <img
                            src="/assets/Images/EcoRide logo.jpeg"
                            alt="Logo"
                            width="50"
                            height="50" />
                        <span class="ms-2 text-black" style="font-size: 1rem; color: #2a5b3f">EcoRide<br />
                            Roulez vert, Partagez mieux</span>
                    </div>
                </a>
                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent"
                    aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Accueil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/covoiturage">Covoiturages</a>
                        </li>


                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php
                            // On détermine le bon lien pour le profil en fonction du rôle
                            $profileLink = '/profile'; // Lien par défaut pour un utilisateur normal
                            if (isset($_SESSION['user_role'])) {
                                if ($_SESSION['user_role'] === 'admin') {
                                    $profileLink = '/admin';
                                } elseif ($_SESSION['user_role'] === 'employe') {
                                    $profileLink = '/employees';
                                }
                            }
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $profileLink ?>">Mon Espace</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/logout">Déconnexion</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/login">Connexion</a>
                            </li>
                        <?php endif; ?>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/contact">Contact</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <?php
    // Affichage des messages flash
    // On met l'affichage des messages flash ici pour qu'il soit sur toutes les pages
    if (isset($_SESSION['message'])) {
        $messageType = $_SESSION['message']['type']; // 'success' ou 'danger'
        $messageText = $_SESSION['message']['text'];
        echo '<div class="alert alert-' . htmlspecialchars($messageType) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($messageText);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['message']); // Efface le message après l'affichage
    }
    ?>