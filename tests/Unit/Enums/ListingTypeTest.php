<?php

use App\Enums\ListingType;

it('maps every listing type to one of the three permitted badge tokens', function (ListingType $type, string $surface, string $text) {
    expect($type->badgeColor())->toBe($surface)
        ->and($type->badgeTextColor())->toBe($text);
})->with([
    'adoption is the brand violet' => [ListingType::Adoption, 'primary', 'primary-foreground'],
    'sale is the success emerald'  => [ListingType::Sale, 'success', 'success-foreground'],
    'mating is the warning amber'  => [ListingType::Mating, 'warning', 'warning-foreground'],
]);

it('pairs every badge surface with its own foreground token', function () {
    foreach (ListingType::cases() as $case) {
        expect($case->badgeTextColor())->toBe($case->badgeColor().'-foreground');
    }
});
