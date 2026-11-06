<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Schema\SchemaContext;

/**
 * A point on a map (Orchid's Map): click, drag the marker, type coordinates or search an address.
 * The value is ['lat' => 55.7558, 'lng' => 37.6173] or null.
 *
 *     Map::make('form.location')->label('Office')->zoom(15)->height(360),
 *
 * Tiles and address search come from config('vanda.map'); OpenStreetMap by default.
 */
final class Map extends Field
{
    /** @var array{0: float, 1: float}|null */
    private ?array $center = null;

    private int $zoom = 13;

    private int $height = 320;

    private bool $search = true;

    public function type(): string
    {
        return 'map';
    }

    /**
     * Where the map opens while the field is empty (config('vanda.map.center') by default).
     */
    public function center(float $lat, float $lng): static
    {
        $this->center = [$lat, $lng];

        return $this;
    }

    public function zoom(int $zoom): static
    {
        $this->zoom = max(1, min(19, $zoom));

        return $this;
    }

    public function height(int $pixels): static
    {
        $this->height = $pixels;

        return $this;
    }

    /**
     * The address search box (on when config('vanda.map.geocoder') is set).
     */
    public function search(bool $enabled = true): static
    {
        $this->search = $enabled;

        return $this;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function sanitize(mixed $value, mixed $current = null): ?array
    {
        $lat = is_array($value) && is_numeric($value['lat'] ?? null) ? (float) $value['lat'] : null;
        $lng = is_array($value) && is_numeric($value['lng'] ?? null) ? (float) $value['lng'] : null;

        return $lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180
            ? ['lat' => round($lat, 7), 'lng' => round($lng, 7)]
            : null;
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        $config = (array) config('vanda.map', []);
        $center = $this->center ?? array_values((array) ($config['center'] ?? [55.7558, 37.6173]));

        return [
            'center' => ['lat' => (float) $center[0], 'lng' => (float) $center[1]],
            'zoom' => $this->zoom,
            'height' => $this->height,
            'tiles' => (string) ($config['tiles'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'attribution' => $config['attribution'] ?? null,
            'geocoder' => $this->search ? ($config['geocoder'] ?? null) : null,
        ];
    }
}
