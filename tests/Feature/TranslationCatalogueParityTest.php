<?php

/**
 * The two client catalogues, `lang/en.json` and `lang/ar.json`, are one file per
 * locale that every agent and every feature branch appends to, and nothing about
 * that is coordinated. Three agents edited both files in one phase of the UI
 * port; they happened not to collide, and the only thing that established it was
 * somebody diffing the key lists by hand afterwards.
 *
 * The invariant these guard is in .ai/rules/lang.md, and it is a user-visible
 * one: an English key with no Arabic counterpart renders as the raw dotted key —
 * an Arabic reader gets `home.vaccinated_only` where a sentence should be. The
 * client's `t()` has no English fallback, so this degrades silently and only for
 * the locale nobody writing the key is reading in.
 *
 * The catalogue's *content* is BuildTranslationCatalogueTest's; these tests are
 * about the files as files, which is why they read them off disk rather than
 * through the Action.
 */
const TRANSLATION_CATALOGUE_LOCALES = ['en', 'ar'];

/**
 * The catalogue as PHP sees it. `JSON_THROW_ON_ERROR` is what establishes that
 * the file parses at all — a truncated or trailing-comma file fails here, by
 * name, rather than as a confusing null further down.
 *
 * @return array<string, string>
 */
function translationCatalogue(string $locale): array
{
    return json_decode(
        file_get_contents(base_path("lang/{$locale}.json")),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * The keys as they are *written*, duplicates and all.
 *
 * `json_decode()` cannot answer this: a repeated key is legal JSON and the last
 * one silently wins, so a merge that lands the same key twice with two different
 * sentences decodes to a catalogue of the ordinary size with one string quietly
 * replaced. The file has to be read as text to see it.
 *
 * The pattern relies on the format both files are actually in — one entry per
 * line, keys indented, values flat strings. If that ever stops being true the
 * scanner stops seeing keys, which is why the test below also checks this list
 * against the decoded one instead of trusting it.
 *
 * @return list<string>
 */
function translationCatalogueKeysAsWritten(string $locale): array
{
    preg_match_all(
        '/^\s+"((?:[^"\\\\]|\\\\.)*)"\s*:/m',
        file_get_contents(base_path("lang/{$locale}.json")),
        $matches,
    );

    return array_map(
        fn (string $key): string => json_decode('"'.$key.'"', flags: JSON_THROW_ON_ERROR),
        $matches[1],
    );
}

/**
 * One direction only. `ar.json` is deliberately the larger file — backend
 * toasts and domain exceptions use the English sentence itself as the key, so
 * they need an Arabic entry and no English one (.ai/rules/lang.md). Asserting
 * the reverse would fail on the day it was written, on 35 keys that are correct.
 *
 * The failure names the keys rather than the counts, because "650 is not 685" is
 * not something anyone can act on.
 */
test('every key in the English catalogue has an Arabic counterpart', function () {
    $missing = array_keys(array_diff_key(
        translationCatalogue('en'),
        translationCatalogue('ar'),
    ));

    expect($missing)->toBe([]);
});

/**
 * A repeated key is the failure mode of the merge that produced these files —
 * two branches adding `home.filters.title` in different places of the same file
 * resolve cleanly at the text level and leave one of the two sentences
 * unreachable.
 *
 * The second expectation is the scanner's own guard: the set of keys read as
 * text, deduplicated, has to be the set of keys the JSON parser found. If the
 * file is ever reformatted — nested, or several entries to a line — that fails
 * here rather than turning the duplicate check into a silent pass over zero
 * keys.
 */
test('the catalogue declares no key twice', function (string $locale) {
    $written = translationCatalogueKeysAsWritten($locale);

    $duplicated = array_keys(array_filter(
        array_count_values($written),
        fn (int $occurrences): bool => $occurrences > 1,
    ));

    expect($duplicated)->toBe([]);

    expect(array_values(array_unique($written)))
        ->toEqualCanonicalizing(array_keys(translationCatalogue($locale)));
})->with(TRANSLATION_CATALOGUE_LOCALES);
