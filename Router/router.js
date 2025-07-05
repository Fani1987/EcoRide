// public/Router/router.js
import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";

const route404 = new Route("404", "Page introuvable", "/404.php"); // Point vers votre fichier 404.php

const getRouteByUrl = (url) => {
  let currentRoute = null;
  allRoutes.forEach((element) => {
    if (element.url == url) {
      currentRoute = element;
    }
  });
  if (currentRoute != null) {
    return currentRoute;
  } else {
    return route404;
  }
};

const LoadContentPage = async () => {
  const path = window.location.pathname;
  const actualRoute = getRouteByUrl(path);

  // Récupération du contenu HTML de la route via l'endpoint PHP
  // Assurez-vous que votre serveur web redirige /get-view/* vers index.php
  const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
  document.getElementById("main-page").innerHTML = html; // Ajout du contenu HTML

  // Ajout du contenu JavaScript
  if (actualRoute.pathJS !== "") {
    // Supprimer l'ancien script s'il existe pour éviter des exécutions multiples
    const oldScript = document.querySelector(
      `script[src="${actualRoute.pathJS}"]`
    );
    if (oldScript) {
      oldScript.remove();
    }
    var scriptTag = document.createElement("script");
    scriptTag.setAttribute("type", "module"); // Utilisez type="module" pour les modules ES6
    scriptTag.setAttribute("src", actualRoute.pathJS);

    // Ajout de la balise script au corps du document
    document.querySelector("body").appendChild(scriptTag);
  }

  // Changement du titre de la page
  document.title = actualRoute.title + " - " + websiteName;
};

// Fonction pour gérer les événements de routage (clic sur les liens)
const routeEvent = (event) => {
  event = event || window.event;
  event.preventDefault();
  // Mise à jour de l'URL dans l'historique du navigateur
  window.history.pushState({}, "", event.target.href);
  // Chargement du contenu de la nouvelle page
  LoadContentPage();
};

// Gestion de l'événement de retour en arrière dans l'historique du navigateur
window.addEventListener("popstate", LoadContentPage);
// Chargement initial du contenu de la page au premier chargement
window.addEventListener("DOMContentLoaded", LoadContentPage);

// Attachez l'événement aux liens pertinents au chargement de la page.
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("a").forEach((link) => {
    // Ne pas intercepter les liens externes, les ancres (#) ou les mailto:
    if (
      link.getAttribute("href") &&
      !link.getAttribute("href").startsWith("#") &&
      !link.getAttribute("href").startsWith("mailto:") &&
      !link.getAttribute("href").startsWith("http") // Ne pas intercepter les liens http(s)
    ) {
      link.addEventListener("click", routeEvent);
    }
  });
});
