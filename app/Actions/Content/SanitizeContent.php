<?php

namespace App\Actions\Content;

/**
 * Clean a piece of user-written text before it is stored.
 *
 * Deliberately domain-agnostic and outside app/Actions/Comments: the legacy app
 * ran TrimContentPipeline + FilterBadWordsPipeline on comments and let private
 * messages through untouched, and the messaging vertical is meant to call this
 * rather than grow a second copy of the list.
 *
 * Trimming and masking are one Action rather than two, because they are never
 * wanted apart and their order matters: masking first would leave the mask's
 * own spacing behind, and any caller that wanted only half of this would be
 * storing text the other half was written to keep out.
 *
 * The word list arrives as an argument rather than being read from config()
 * here, so the pipeline step calling this reads no configuration either (see
 * .ai/rules/pipelines.md) and a test can drive it with a known list.
 *
 * This is a mask, not a moderation decision. The text is still published; the
 * report flow stays the escalation path for content the mask cannot judge.
 */
class SanitizeContent
{
    /**
     * Trim, collapse runs of whitespace, and mask the given words.
     *
     * Matching is whole-word and case-insensitive. The legacy filter used
     * str_ireplace(), which has no notion of a word boundary and turned
     * "class" into "cl****", "grass" into "gr****" and "Cocktail" into
     * "****tail" — every one of them a false positive on ordinary text about
     * pets. Entries are sorted longest first so a phrase ("anal sex") is
     * consumed before its own prefix ("anal") can match half of it.
     *
     * Boundaries are the Unicode-aware \b under the `u` modifier, so an Arabic
     * comment is never mangled by a Latin word list; a word that survives
     * preg_quote() but cannot be compiled is dropped rather than throwing.
     *
     * @param  list<string>  $bannedWords
     * @param  string  $mask  What a match is replaced with.
     */
    public function handle(string $content, array $bannedWords = [], string $mask = '****'): string
    {
        $content = trim((string) preg_replace('/\s+/u', ' ', $content));

        $pattern = $this->pattern($bannedWords);

        if ($pattern === null || $content === '') {
            return $content;
        }

        return (string) preg_replace($pattern, $mask, $content);
    }

    /**
     * Compile the word list into one alternation, or null when there is nothing
     * to mask.
     *
     * @param  list<string>  $bannedWords
     */
    protected function pattern(array $bannedWords): ?string
    {
        $words = collect($bannedWords)
            ->filter(fn (mixed $word): bool => is_string($word) && trim($word) !== '')
            ->map(fn (string $word): string => trim($word))
            ->unique()
            ->sortByDesc(fn (string $word): int => mb_strlen($word))
            ->map(fn (string $word): string => preg_quote($word, '/'))
            ->values();

        if ($words->isEmpty()) {
            return null;
        }

        return '/\b(?:'.$words->implode('|').')\b/iu';
    }
}
