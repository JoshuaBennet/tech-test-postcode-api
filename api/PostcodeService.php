<?php

declare(strict_types=1);

class PostcodeService
{
    private const CACHE_TTL = 14400; // 4 hours, reduces repeated remote lookups and rate limit pressure
    private const CACHE_DIR = __DIR__ . '/cache';
    private const POSTCODE_API_URL = 'https://api.getthedata.com/postcode/';

    public function handleRequest(): void
    {
        header('Content-Type: application/json');

        try {
            $postcode = $this->getRequestedPostcode();
            $geocode = $this->fetchPostcodeCoordinates($postcode);
            $attractions = $this->loadAttractions();
            $results = $this->buildResults($geocode, $attractions);

            echo json_encode([
                'postcode' => strtoupper($geocode['postcode']),
                'results' => $results,
            ]);
        } catch (RuntimeException $exception) {
            $this->respondError($exception->getMessage());
        }
    }

    private function getRequestedPostcode(): string
    {
        $postcode = trim((string)($_GET['postcode'] ?? ''));

        if ($postcode === '') {
            throw new RuntimeException('Postcode is required');
        }

        if (!preg_match('/^[A-Z0-9 ]{5,8}$/i', $postcode)) {
            throw new RuntimeException('Invalid postcode format');
        }

        return strtoupper($postcode);
    }

    private function fetchPostcodeCoordinates(string $postcode): array
    {
        $cacheKey = str_replace(' ', '', $postcode);
        $cached = $this->getCachedGeocode($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $payload = @file_get_contents(self::POSTCODE_API_URL . rawurlencode($cacheKey));
        if ($payload === false) {
            if ($cached = $this->getStaleCachedGeocode($cacheKey)) {
                return $cached;
            }

            throw new RuntimeException('Unable to resolve postcode. Please try a different postcode.');
        }

        $response = json_decode($payload, true);
        $data = $response['data'] ?? $response['result'] ?? null;

        if (!is_array($data) || empty($data['latitude']) || empty($data['longitude'])) {
            throw new RuntimeException('Unable to resolve postcode. Please try a different postcode.');
        }

        $geocode = [
            'postcode' => $data['postcode'] ?? $postcode,
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
        ];

        $this->storeGeocodeCache($cacheKey, $geocode);
        return $geocode;
    }

    private function getCachedGeocode(string $cacheKey): ?array
    {
        $cacheFile = $this->getCacheFile($cacheKey);
        if (!is_file($cacheFile) || (time() - filemtime($cacheFile)) > self::CACHE_TTL) {
            return null;
        }

        $cached = json_decode(file_get_contents($cacheFile) ?: '', true);
        return is_array($cached) ? $cached : null;
    }

    private function getStaleCachedGeocode(string $cacheKey): ?array
    {
        $cacheFile = $this->getCacheFile($cacheKey);
        if (!is_file($cacheFile)) {
            return null;
        }

        $cached = json_decode(file_get_contents($cacheFile) ?: '', true);
        return is_array($cached) ? $cached : null;
    }

    private function storeGeocodeCache(string $cacheKey, array $geocode): void
    {
        if (!is_dir(self::CACHE_DIR) && !mkdir(self::CACHE_DIR, 0755, true) && !is_dir(self::CACHE_DIR)) {
            return;
        }

        $cacheFile = $this->getCacheFile($cacheKey);
        file_put_contents($cacheFile, json_encode($geocode));
    }

    private function getCacheFile(string $cacheKey): string
    {
        return self::CACHE_DIR . '/' . rawurlencode($cacheKey) . '.json';
    }

    private function loadAttractions(): array
    {
        $csvPath = __DIR__ . '/../assets/data.csv';
        if (!is_file($csvPath) || ($handle = fopen($csvPath, 'r')) === false) {
            throw new RuntimeException('Attraction data unavailable');
        }

        $rows = [];
        fgetcsv($handle); // ignore header row

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 5) {
                continue;
            }

            $rows[] = [
                'title' => trim($data[0]),
                'description' => trim($data[1]),
                'address' => trim($data[2]),
                'postcode' => trim($data[3]),
                'link' => trim($data[4]),
            ];
        }

        fclose($handle);
        return $rows;
    }

    private function buildResults(array $geocode, array $attractions): array
    {
        $results = [];
        $coordsByPostcode = $this->getAttractionCoordinates();

        foreach ($attractions as $attraction) {
            $key = strtoupper(trim($attraction['postcode']));
            $coords = $coordsByPostcode[$key] ?? null;

            if ($coords === null) {
                continue; // skip attractions whose postcode cannot be geocoded
            }

            $results[] = [
                'title' => $attraction['title'] ?: 'Unknown attraction',
                'description' => $attraction['description'] ?: 'No description available.',
                'address' => $attraction['address'] ?: $attraction['postcode'],
                'link' => $attraction['link'] ?: '#',
                'distance' => $this->calculateDistanceMiles(
                    $geocode['latitude'],
                    $geocode['longitude'],
                    $coords['latitude'],
                    $coords['longitude']
                ),
            ];
        }

        usort($results, static function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return array_slice($results, 0, 5);
    }

    private function calculateDistanceMiles(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 3958.8;
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
    }

    private function getAttractionCoordinates(): array
    {
        return [
            'EH6 6JJ' => ['latitude' => 55.980053, 'longitude' => -3.179670],
            'OX1 3BG' => ['latitude' => 51.755125, 'longitude' => -1.254930],
            'N6 6PJ' => ['latitude' => 51.568609, 'longitude' => -0.147475],
            'WC1B 3DG' => ['latitude' => 51.519362, 'longitude' => -0.126873],
            'EH1 2NG' => ['latitude' => 55.948965, 'longitude' => -3.201478],
            'SE1 2UP' => ['latitude' => 51.503326, 'longitude' => -0.076613],
            'BA1 1LZ' => ['latitude' => 51.381128, 'longitude' => -2.360105],
            'SE10 9NF' => ['latitude' => 51.480285, 'longitude' => -0.006019],
            'SW7 5BD' => ['latitude' => 51.496563, 'longitude' => -0.176892],
            'SP4 7DE' => ['latitude' => 51.184342, 'longitude' => -1.857404],
            'YO1 7HH' => ['latitude' => 53.961573, 'longitude' => -1.081910],
            'PO1 3TT' => ['latitude' => 50.796178, 'longitude' => -1.107970],
            'PL24 2SG' => ['latitude' => 50.359718, 'longitude' => -4.743157],
        ];
    }

    private function respondError(string $message): void
    {
        echo json_encode(['error' => $message]);
        exit;
    }
}
