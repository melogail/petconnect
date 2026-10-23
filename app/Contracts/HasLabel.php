<?php

namespace App\Contracts;

/**
 * An enum whose cases carry a human-readable label.
 *
 * App\Concerns\HasOptions already declares `label()` abstract, but a trait is
 * not a type: nothing could say "a class-string of an enum that has label()".
 * This interface is that type. App\Nova\Filters\EnumFilter::enum() returns
 * `class-string<\BackedEnum&HasLabel>`, so an enum a filter targets implements
 * it — the three report enums do today — and a filter over an enum without a
 * label is a static error rather than a runtime BadMethodCallException.
 */
interface HasLabel
{
    public function label(): string;
}
