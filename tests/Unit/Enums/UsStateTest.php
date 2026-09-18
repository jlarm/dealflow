<?php

use App\Enums\UsState;

test('normalizes state names and codes to two-letter codes', function (string $input, string $expected) {
    expect(UsState::normalize($input))->toBe($expected);
})->with([
    'full name' => ['Texas', 'TX'],
    'lowercase name' => ['new mexico', 'NM'],
    'code' => ['OK', 'OK'],
    'lowercase code' => ['tx', 'TX'],
    'surrounding spaces' => ['  Oklahoma ', 'OK'],
    'district of columbia' => ['District of Columbia', 'DC'],
    'not a US state' => ['Ontario', 'Ontario'],
]);
