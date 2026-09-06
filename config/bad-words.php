<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masked Words
    |--------------------------------------------------------------------------
    |
    | Words replaced with the mask below before user-written content is stored.
    | Ported verbatim from the legacy application, which applied it to comments
    | only; App\Actions\Content\SanitizeContent is deliberately domain-agnostic
    | so the messaging vertical can run the same list rather than growing a
    | second one.
    |
    | Matching is whole-word and case-insensitive. The legacy filter used
    | str_ireplace(), which has no word boundary, so "class" became "cl****",
    | "grass" became "gr****" and "Cocktail" became "****tail". Multi-word
    | entries are matched as a phrase and are tried before their own
    | single-word prefixes, so "anal sex" is masked once rather than twice.
    |
    | This is a mask, not a moderation decision: a masked comment is still
    | published, and reporting it stays the user-facing escalation path.
    |
    | Known false positives on a pet marketplace, kept knowingly rather than
    | overlooked: `cock` is a rooster and `bitch` is a female dog, so "the cock
    | crowed at dawn" and "a bitch from a working line" both come out masked.
    | The list is a verbatim legacy port and dropping either word is a product
    | call about the most common English insult in it, not a bug fix — take it
    | to the product owner before editing, and if it is dropped, drop it here
    | rather than special-casing the filter, which is domain-agnostic on
    | purpose.
    |
    */

    'words' => [
        'anal',
        'anal intercourse',
        'anal penetration',
        'anal sex',
        'anus',
        'ass',
        'bitch',
        'cock',
        'cum',
        'cunt',
        'dick',
        'fag',
        'faggot',
        'fuck',
        'nigger',
        'penis',
        'pussy',
        'rectal',
        'rectum',
        'shit',
        'slut',
        'vagina',
        'whore',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mask
    |--------------------------------------------------------------------------
    |
    | What a matched word is replaced with. Fixed width rather than one
    | character per letter, so the length of the original is not recoverable
    | from the stored text.
    |
    */

    'mask' => '****',

];
