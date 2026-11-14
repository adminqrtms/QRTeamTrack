<?php

namespace App\Support;

/**
 * BARMM provinces, cities/municipalities and barangays
 * (public/data/ph-addresses.json, from the PSA's PSGC, 30 June 2026).
 * The admin address dropdowns load the same file.
 */
class PhAddress
{
    private static ?array $index = null;

    /** True when the barangay is in that city and the city in that province. */
    public static function exists(?string $province, ?string $city, ?string $barangay): bool
    {
        $index = self::index();

        return isset($index[$province][$city][$barangay]);
    }

    /** "Street, Barangay, City, Province" for the combined address column. */
    public static function format(?string $street, ?string $barangay, ?string $city, ?string $province): string
    {
        return implode(', ', array_filter(array_map('trim', [
            (string) $street,
            $barangay ? "Brgy. {$barangay}" : '',
            (string) $city,
            // Independent cities (e.g. Davao City) are their own "province": don't repeat it
            $province !== $city ? (string) $province : '',
        ])));
    }

    private static function index(): array
    {
        if (self::$index === null) {
            $data = json_decode((string) @file_get_contents(public_path('data/ph-addresses.json')), true) ?: [];
            self::$index = [];
            foreach ($data['provinces'] ?? [] as $province) {
                foreach ($province['cities'] as $city) {
                    self::$index[$province['name']][$city['name']] = array_flip($city['barangays']);
                }
            }
        }

        return self::$index;
    }
}
