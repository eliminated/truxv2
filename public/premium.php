<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

$pageKey = 'premium';
$pageLayout = 'app';

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--studio">
  <section class="pageBand pageBand--studio">
    <div class="pageBand__main">
      <span class="pageBand__eyebrow">Premium roadmap</span>
      <h2 class="pageBand__title">Planned premium tiers</h2>
      <p class="pageBand__copy">Premium memberships are not live yet. These remain placeholders for future rollout.</p>
    </div>
  </section>

  <section class="bandSurface">
    <div class="bandSurface__head">
      <div>
        <span class="bandSurface__eyebrow">Roadmap</span>
        <h3>Tier structure</h3>
      </div>
    </div>

    <div class="premiumTierGrid">
      <article class="premiumTier premiumTier--basic">
        <h3 class="premiumTier__title">Basic Premium</h3>
        <p class="premiumTier__desc muted">Entry-level account customization and convenience features.</p>
        <ul class="premiumTier__features">
          <li>Animated profile photo (GIF avatar)</li>
          <li>Extra profile themes and badge styles</li>
          <li>Higher avatar and banner limits</li>
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
          <li>Higher automation and API limits</li>
        </ul>
      </article>
    </div>

    <p class="muted premiumPlans__footnote">No billing or upgrades are available right now.</p>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
