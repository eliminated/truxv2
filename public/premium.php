<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Premium</h1>
  <p class="muted">Premium memberships are not live yet. All plans below are placeholders for planned feature rollout.</p>
</section>

<section class="card settingsCard premiumPlans">
  <div class="card__body">
    <h2 class="h2">Planned Premium Tiers</h2>
    <p class="muted">Higher tiers include all features from lower tiers.</p>

    <div class="premiumTierGrid">
      <article class="premiumTier premiumTier--basic">
        <h3 class="premiumTier__title">Basic Premium</h3>
        <p class="premiumTier__desc muted">Entry-level account customization and convenience features.</p>
        <ul class="premiumTier__features">
          <li>Animated profile photo (GIF avatar)</li>
          <li>Extra profile themes and badge styles</li>
          <li>Higher avatar/banner media limits</li>
          <li>Shorter username change cooldown</li>
        </ul>
      </article>

      <article class="premiumTier premiumTier--standard">
        <h3 class="premiumTier__title">Premium</h3>
        <p class="premiumTier__desc muted">Enhanced social and messaging workflows.</p>
        <ul class="premiumTier__features">
          <li>Bookmark folders and saved-item organization</li>
          <li>Advanced notification filtering</li>
          <li>DM edit or unsend grace window</li>
          <li>Pinned conversations and profile link customization</li>
        </ul>
      </article>

      <article class="premiumTier premiumTier--advanced">
        <h3 class="premiumTier__title">Advanced Premium</h3>
        <p class="premiumTier__desc muted">Creator-focused publishing and analytics tools.</p>
        <ul class="premiumTier__features">
          <li>Post scheduling</li>
          <li>Engagement analytics dashboard</li>
          <li>Advanced feed and search filters</li>
          <li>Account data export tools</li>
        </ul>
      </article>

      <article class="premiumTier premiumTier--plus">
        <h3 class="premiumTier__title">Premium+</h3>
        <p class="premiumTier__desc muted">Power-user tier with early access and priority support.</p>
        <ul class="premiumTier__features">
          <li>Team inbox and moderation helpers</li>
          <li>Priority support queue</li>
          <li>Early access to beta features</li>
          <li>Higher automation/API limits (future)</li>
        </ul>
      </article>
    </div>

    <p class="muted premiumPlans__footnote">No billing or upgrades are available right now.</p>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
