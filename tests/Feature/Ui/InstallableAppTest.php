<?php

// FoodLoop can be added to a phone's home screen and opens like an app
// (public/manifest.webmanifest, public/sw.js, public/icons).

it('links the app manifest, icons and colour on every page', function () {
    dealAt(0.4);

    $this->get('/')
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('<meta name="theme-color" content="#0e2a20">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">', false);
});

it('has a manifest that opens full screen on the deals, with icons that exist', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray(['name' => 'FoodLoop', 'start_url' => '/', 'display' => 'standalone'])
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->filter()->all())->toContain('maskable');

    foreach ([...array_column($manifest['icons'], 'src'), '/icons/apple-touch-icon.png'] as $icon) {
        expect(public_path(ltrim($icon, '/')))->toBeFile();
        [$width] = getimagesize(public_path(ltrim($icon, '/')));
        expect($width)->toBeGreaterThanOrEqual(180);
    }
});

it('only steps in for page loads, never for form submits, and caches nothing personal', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("event.request.method !== 'GET'")
        ->and($worker)->toContain("cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])")
        ->and($worker)->not->toContain('cache.put')
        ->and(public_path('offline.html'))->toBeFile();
});

it('trusts the https tunnel, so links stay on https when opened on a phone', function () {
    dealAt(0.4);

    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'foodloop-demo.trycloudflare.com'])
        ->assertSee('https://foodloop-demo.trycloudflare.com/map', false);
});
