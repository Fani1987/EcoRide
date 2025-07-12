 <footer class="bg-primary text-black text-center footer">
     <div class="row">
         <div class="col-6 col-lg-4">
             <p>
                 <a href="/legalNotice">Mentions légales</a>
             </p>
         </div>

         <div class="col-6 col-lg-4 ms-auto">
             <a href="/contact"><i class="bi bi-envelope"></i></a>
         </div>
     </div>
     <div class="modal fade" id="bookingConfirmationModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h5 class="modal-title" id="bookingModalLabel">Confirmation de la réservation</h5>
                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <p>Êtes-vous sûr(e) de vouloir réserver ce trajet ?</p>
                     <p><strong>Trajet :</strong> <span id="modal-trip-info"></span></p>
                     <p><strong>Coût :</strong> <span id="modal-trip-price"></span> crédits</p>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                     <button type="button" class="btn btn-dark" id="confirm-booking-btn">Confirmer et réserver</button>
                 </div>
             </div>
         </div>
     </div>
 </footer>

 <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

 <script>
     // On récupère la modale et son bouton de confirmation une seule fois
     const bookingModal = new bootstrap.Modal(document.getElementById('bookingConfirmationModal'));
     const confirmBtn = document.getElementById('confirm-booking-btn');

     // Cette fonction est appelée quand on clique sur "Réserver"
     function openBookingModal(button) {
         // On récupère les infos depuis les data-attributes du bouton
         const trajetId = button.getAttribute('data-trajet-id');
         const trajetInfo = button.getAttribute('data-trajet-info');
         const trajetPrix = button.getAttribute('data-trajet-prix');

         // On remplit le contenu de la modale avec ces infos
         document.getElementById('modal-trip-info').textContent = trajetInfo;
         document.getElementById('modal-trip-price').textContent = trajetPrix;

         // On stocke l'ID du trajet sur le bouton de confirmation pour le retrouver plus tard
         confirmBtn.setAttribute('data-trajet-id-to-book', trajetId);

         // On affiche la modale
         bookingModal.show();
     }

     // On ajoute un écouteur d'événement sur le bouton de confirmation de la modale
     confirmBtn.addEventListener('click', function() {
         // On récupère l'ID qu'on avait stocké
         const trajetIdToBook = this.getAttribute('data-trajet-id-to-book');

         // On cache la modale
         bookingModal.hide();

         // On exécute la réservation via l'API
         fetch('/reserver', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json'
                 },
                 body: JSON.stringify({
                     trajet_id: parseInt(trajetIdToBook)
                 })
             })
             .then(response => response.json())
             .then(data => {
                 alert(data.message);
                 if (data.success) {
                     window.location.reload();
                 }
             })
             .catch(error => {
                 console.error('Erreur lors de la réservation:', error);
                 alert("Une erreur est survenue.");
             });
     });
 </script>
 </body>

 </html>