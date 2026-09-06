<?php

use App\Concerns\HasOptions;
use App\Enums\PetGender;

/**
 * Every enum in App\Enums that composes HasOptions, keyed by its short name so
 * a failing case names itself. The list is read from the directory rather than
 * written out, so an eleventh enum is covered the day it is added.
 *
 * @return array<string, class-string>
 */
function enumsWithOptions(): array
{
    $enums = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Enums/*.php') ?: [] as $file) {
        $shortName = basename($file, '.php');
        $enum = 'App\\Enums\\'.$shortName;

        if (in_array(HasOptions::class, class_uses($enum), true)) {
            $enums[$shortName] = $enum;
        }
    }

    return $enums;
}

test('the option list holds every case of the enum, in declaration order', function (string $enum) {
    expect(array_column($enum::options(), 'value'))
        ->toBe(array_column($enum::cases(), 'value'));
})->with(enumsWithOptions());

test('every option is exactly a value and a label, both non-empty strings', function (string $enum) {
    foreach ($enum::options() as $option) {
        expect(array_keys($option))->toBe(['value', 'label']);

        expect($option['value'])->toBeString()->not->toBeEmpty();
        expect($option['label'])->toBeString()->not->toBeEmpty();
    }
})->with(enumsWithOptions());

test('an option pairs the backing value with the human label', function () {
    expect(PetGender::options())->toBe([
        ['value' => 'male', 'label' => 'Male'],
        ['value' => 'female', 'label' => 'Female'],
    ]);
});
