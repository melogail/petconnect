<?php

use App\Actions\Content\SanitizeContent;

/**
 * The word list is passed in on every call rather than read from config, which
 * is what lets these cases state a tiny, explicit list and assert exactly what
 * it does. The list the comment flow actually runs is pinned where the flow is
 * exercised, in tests/Feature/Actions/Comments/CreateCommentTest.php.
 */
test('trims the text and collapses every run of whitespace to a single space', function () {
    $sanitized = app(SanitizeContent::class)->handle("  Such   a\n\ncalm\tcat  ");

    expect($sanitized)->toBe('Such a calm cat');
});

test('returns the text unchanged when the word list is empty', function () {
    $sanitized = app(SanitizeContent::class)->handle('A cat of any name.', []);

    expect($sanitized)->toBe('A cat of any name.');
});

test('masks a listed word whatever its case', function (string $content) {
    $sanitized = app(SanitizeContent::class)->handle($content, ['bitch'], '****');

    expect($sanitized)->toBe('What a **** of a day');
})->with([
    'lower case' => ['What a bitch of a day'],
    'title case' => ['What a Bitch of a day'],
    'upper case' => ['What a BITCH of a day'],
]);

test('leaves a word that merely contains a listed word intact', function (string $content) {
    $sanitized = app(SanitizeContent::class)->handle($content, ['ass', 'cock', 'anal']);

    expect($sanitized)->toBe($content);
})->with([
    'ass inside class' => ['A class for puppies'],
    'ass inside grass' => ['She rolls in the grass'],
    'cock inside cocktail' => ['Cocktail hour at the shelter'],
    'anal inside analysis' => ['The blood analysis came back clear'],
]);

test('masks a listed word standing on its own even when a longer word shares its letters', function () {
    $sanitized = app(SanitizeContent::class)->handle('A class, an ass.', ['ass']);

    expect($sanitized)->toBe('A class, an ****.');
});

test('masks a listed phrase once rather than masking its prefix inside it', function () {
    $sanitized = app(SanitizeContent::class)->handle('He wrote anal sex here', ['anal', 'anal sex']);

    expect($sanitized)->toBe('He wrote **** here');
});

test('leaves text in a script the word list is not written in alone', function () {
    $content = 'قطة جميلة جدا';

    $sanitized = app(SanitizeContent::class)->handle($content, ['ass', 'cock']);

    expect($sanitized)->toBe($content);
});

test('returns an empty string for text that is nothing but whitespace', function () {
    $sanitized = app(SanitizeContent::class)->handle("   \n\t ", ['ass']);

    expect($sanitized)->toBe('');
});
