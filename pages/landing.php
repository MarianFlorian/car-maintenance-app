<?php
// pages/landing.php

// Include header (careful la cale)
include __DIR__ . '/../includes/header.php';
?>

<!-- Custom styles for landing page -->
<style>
  /* Reset & base */
  * { margin:0; padding:0; box-sizing:border-box; }
  body.landing { background: #f0f2f5; }
  .hero {
    background: linear-gradient(135deg,#2575fc,#6a11cb);
    color:#fff; text-align:center; padding:100px 20px;
  }
  .hero h1 { font-size:2.75rem; margin-bottom:0.5rem; }
  .hero p  { font-size:1.25rem; margin-bottom:1.5rem; }
  .btn-landing {
    display:inline-block; padding:0.75rem 1.5rem;
    border-radius:0.5rem; font-weight:600;
    background:#fff; color:#2575fc;
    transition:background .3s, transform .2s;
  }
  .btn-landing:hover {
    background:#f0f0f0; transform:translateY(-2px);
  }
  .features {
    padding:60px 20px; max-width:1200px; margin:0 auto;
    display:grid; grid-gap:40px;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
  }
  .feature-card {
    background:#fff; border-radius:0.75rem;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
    text-align:center; padding:30px 20px;
    transition:transform .3s;
  }
  .feature-card:hover { transform:translateY(-4px); }
  .feature-icon { font-size:3rem; color:#2575fc; margin-bottom:1rem; }
  .feature-card h3 { margin-bottom:0.75rem; }
  .feature-card p  { margin-bottom:1.25rem; color:#555; }
  .callout {
    background: #f7f9fc; padding:60px 20px; text-align:center;
  }
  .callout h2 { font-size:2rem; margin-bottom:0.75rem; }
  .callout p  { margin-bottom:1.5rem; color:#555; }
</style>

<main class="landing">
  <!-- Hero -->
  <section class="hero">
    <h1>Condu cu încredere, economisește inteligent</h1>
    <p>Alătură-te astăzi și ai acces la toate instrumentele auto într-un singur loc!</p>
    <a href="../register.php" class="btn-landing">Crează-ți cont gratuit</a>
  </section>

  <!-- Features: calculators -->
  <section class="features">
    <div class="feature-card">
      <div class="feature-icon">🥂</div>
      <h3>Calculator Alcoolemie</h3>
      <p>Află rapid dacă poți conduce în siguranță după o ieșire cu prietenii.</p>
      <a href="calculator_alcoolemie.php" class="btn-landing">Încearcă acum</a>
    </div>

    <div class="feature-card">
      <div class="feature-icon">🚗</div>
      <h3>Calculator Cost Călătorie</h3>
      <p>Estimări rapide ale costului, consumului și emisiilor pentru orice călătorie.</p>
      <a href="calculator_cost.php" class="btn-landing">Încearcă acum</a>
    </div>
  </section>

  <!-- Call to action -->
  <section class="callout">
    <h2>De ce să te înregistrezi?</h2>
    <p>Salvează-ți istoricul, primește alerte pentru ITP/RCA, vezi statistici și rapoarte detaliate.</p>
    <a href="../register.php" class="btn-landing">Înregistrează-te acum</a>
  </section>
</main>

<?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
