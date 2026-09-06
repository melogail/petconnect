<?php

namespace App\Concerns;

/**
 * The `{value, label}` list a backed enum ships to a select control.
 *
 * Ten enums carried a byte-identical `options()` before this trait existed, and
 * five more verticals would have copied it again. The composing enum supplies
 * only the part that differs — `label()` — which the abstract declaration below
 * makes a compile-time requirement rather than a runtime surprise.
 *
 * Known gap, deliberately not fixed here: `label()` implementations return
 * hardcoded English. See .ai/rules/enums.md — localising them is a change to
 * every enum and every consumer, not a change to this trait.
 */
trait HasOptions
{
    /**
     * The human-readable name of this case.
     */
    abstract public function label(): string;

    /**
     * Every case as a select option, in declaration order.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
