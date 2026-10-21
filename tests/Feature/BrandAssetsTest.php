<?php

/**
 * The brand files the document head points at, pinned to the files that
 * exist under `public/`. The head is written by hand in
 * `resources/views/app.blade.php`, so renaming or dropping any of these under
 * `public/` or `public/brand/` would ship a broken favicon, touch icon or
 * share card with nothing failing — the same reason the verification mail
 * pins `brand/sniffpal-mark-128.png`.
 */
test('the document head links the favicon, touch icon and share card to files that exist', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', escape: false)
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', escape: false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', escape: false)
        ->assertSee('<meta property="og:image" content="'.asset('brand/sniffpal-og.png').'">', escape: false);

    expect(public_path('favicon.ico'))->toBeFile()
        ->and(public_path('favicon.svg'))->toBeFile()
        ->and(public_path('apple-touch-icon.png'))->toBeFile()
        ->and(public_path('brand/sniffpal-og.png'))->toBeFile();
});

/**
 * `favicon.svg` and the brand icon are the same drawing, so a redraw that
 * touches one and not the other leaves the tab icon on the old mark.
 */
test('the favicon is the brand icon', function () {
    expect(file_get_contents(public_path('favicon.svg')))
        ->toBe(file_get_contents(public_path('brand/sniffpal-icon.svg')));
});
