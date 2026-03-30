<?php
declare(strict_types=1);

function trux_known_email_providers(): array {
    static $providers = null;
    if (is_array($providers)) {
        return $providers;
    }

    $providers = [
        'Gmail' => ['gmail.com', 'googlemail.com'],
        'Outlook' => ['outlook.com', 'outlook.co.uk', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'msn.com'],
        'Yahoo' => ['yahoo.com', 'yahoo.co.uk', 'ymail.com', 'rocketmail.com'],
        'iCloud' => ['icloud.com', 'me.com', 'mac.com'],
        'Proton' => ['protonmail.com', 'proton.me', 'pm.me'],
        'AOL' => ['aol.com'],
        'Zoho' => ['zoho.com', 'zohomail.com'],
        'Tutanota' => ['tutanota.com', 'tutamail.com', 'tuta.io'],
        'Fastmail' => ['fastmail.com', 'fastmail.fm'],
        'GMX' => ['gmx.com', 'gmx.us', 'gmx.net'],
        'Mail.com' => ['mail.com'],
        'HEY' => ['hey.com'],
    ];

    return $providers;
}

function trux_email_provider_domains(): array {
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [];
    foreach (trux_known_email_providers() as $provider => $domains) {
        foreach ($domains as $domain) {
            $normalized = strtolower(trim((string)$domain));
            if ($normalized === '') {
                continue;
            }
            $catalog[$normalized] = $provider;
        }
    }

    ksort($catalog);

    return $catalog;
}

function trux_email_domain_from_address(string $email): ?string {
    $normalizedEmail = trim(strtolower($email));
    if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $domain = substr(strrchr($normalizedEmail, '@') ?: '', 1);
    $domain = trim(strtolower((string)$domain));

    return $domain !== '' ? $domain : null;
}

function validate_email_domain(string $email): array {
    $domain = trux_email_domain_from_address($email);
    if ($domain === null) {
        return [
            'recognized' => false,
            'provider' => null,
            'warning' => 'We could not validate this email domain. Please double-check the address before continuing.',
        ];
    }

    $providerCatalog = trux_email_provider_domains();
    $provider = $providerCatalog[$domain] ?? null;
    if ($provider !== null) {
        return [
            'recognized' => true,
            'provider' => $provider,
            'warning' => null,
        ];
    }

    return [
        'recognized' => false,
        'provider' => null,
        'warning' => 'This email domain is not in our list of recognized providers. You can continue, but we recommend using Gmail, Outlook, Yahoo, iCloud, Proton, or another mainstream provider.',
    ];
}
