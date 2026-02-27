<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    require_once __DIR__ . '/_header.php';
    ?>
    <section class="card">
      <div class="card__body">
        <h1>Logout</h1>
        <p class="muted">To log out, use the logout button in the top navigation.</p>
        <a class="btn" href="/">Back</a>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/_footer.php';
    exit;
}

trux_logout_user();
trux_flash_set('success', 'You have been logged out.');
trux_redirect('/login.php');