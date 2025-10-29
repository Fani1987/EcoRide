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
     <!-- Modal de confirmation de réservation -->
     <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h5 class="modal-title" id="bookingModalLabel">Confirmer la réservation</h5>
                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <p>Vous êtes sur le point de réserver le trajet :</p>
                     <p><strong id="modalTrajetInfo"></strong></p>
                     <p>Prix : <strong id="modalTrajetPrix"></strong> crédits.</p>
                     <p class="text-danger">Cette action est irréversible et vos crédits seront débités.</p>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                     <button type="button" class="btn btn-dark" id="confirmBookingBtn">Confirmer et payer</button>
                 </div>
             </div>
         </div>
     </div>
 </footer>

 <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

 </body>

 </html>