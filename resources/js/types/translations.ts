/**
 * The `translations` shared prop: `lang/{locale}.json`, flat, exactly as
 * `Actions\Localization\BuildTranslationCatalogue` reads it off disk.
 *
 * Keys are whatever `__()` takes, and two styles coexist on purpose
 * (.ai/rules/lang.md): dotted UI keys such as `notifications.liked_pet`, and
 * English sentences used as their own key, such as
 * `'Notifications marked as read.'`. Neither is more correct than the other and
 * neither is normalised on this side of the wire.
 *
 * Values may carry `:name` placeholders (Laravel's spelling) or `{name}` ones
 * (the ported legacy spelling). `useTranslations().t()` fills both.
 */
export type TranslationCatalogue = Record<string, string>;

/**
 * What gets substituted into a translated string's placeholders.
 *
 * Numbers are accepted and stringified — `:rate` and `:count` arrive as numbers
 * from a resource often enough that making every call site do it is noise.
 */
export type TranslationReplacements = Record<string, string | number>;
