    </div> <!-- închid containerul-fluid (sau row, după caz) -->

    <style>
      html, body {
        height: 100%;
        margin: 0;
      }
      body {
        display: flex;
        flex-direction: column;
      }
      /* wrapper pentru conținut: tot ce e înainte de footer */
      .page-content {
        flex: 1 0 auto;
      }

      /* footer will be pushed to bottom if page-content isn't tall enough */
      footer {
        flex-shrink: 0;
        background: #f8f9fa;
      }
    </style>

    <footer class="text-center mt-4 py-3">
      <div class="container">
        <div class="mb-2">
          <a href="/pages/terms.php" class="text-muted small mx-2">Termeni &amp; condiții</a>
          <span class="text-muted">|</span>
          <a href="/pages/privacy.php" class="text-muted small mx-2">Politica de confidențialitate</a>
        </div>
        <div class="mb-2">
          <a href="#" class="text-muted mx-2"><i class="fab fa-facebook fa-lg"></i></a>
          <a href="#" class="text-muted mx-2"><i class="fab fa-twitter fa-lg"></i></a>
          <a href="#" class="text-muted mx-2"><i class="fab fa-instagram fa-lg"></i></a>
        </div>
        <div class="text-muted small">
          &copy; 2025 Manager Auto. Toate drepturile rezervate.
        </div>
      </div>
    </footer>

    <!-- Bootstrap JS, Popper.js, și jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- FontAwesome -->
    <script src="https://kit.fontawesome.com/xxx.js" crossorigin="anonymous"></script>

    <!-- Custom -->
    <script src="../assets/js/sidebar.js"></script>
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <script src="../assets/js/scripts.js"></script>
  </body>
</html>
