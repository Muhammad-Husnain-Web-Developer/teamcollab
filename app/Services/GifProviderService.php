<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin server-side proxy to Giphy — the API key must never reach the client
 * bundle, so every lookup goes through this service and GifController rather
 * than being called directly from the browser.
 */
class GifProviderService
{
    private const BASE_URL = 'https://api.giphy.com/v1/gifs';

    private const RESULT_LIMIT = 24;

    private const TRENDING_CACHE_TTL_MINUTES = 30;

    public function isConfigured(): bool
    {
        return filled(config('services.giphy.key'));
    }

    /**
     * @return array<int, array{id: string, provider: string, url: string, preview_url: string, width: int, height: int, title: string}>
     */
    public function search(string $query): array
    {
        if (! $this->isConfigured() || trim($query) === '') {
            return [];
        }

        return $this->fetch(self::BASE_URL . '/search', ['q' => $query]);
    }

    /**
     * @return array<int, array{id: string, provider: string, url: string, preview_url: string, width: int, height: int, title: string}>
     */
    public function trending(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember(
            'gif-provider:trending',
            now()->addMinutes(self::TRENDING_CACHE_TTL_MINUTES),
            fn () => $this->fetch(self::BASE_URL . '/trending', []),
        );
    }

    private function fetch(string $url, array $params): array
    {
        try {
            $response = Http::timeout(5)->get($url, array_merge($params, [
                'api_key' => config('services.giphy.key'),
                'limit'   => self::RESULT_LIMIT,
                'rating'  => 'pg-13',
            ]));
        } catch (\Throwable $e) {
            Log::warning("GifProviderService: request failed for {$url}: {$e->getMessage()}");
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data', []))
            ->map(fn (array $gif) => $this->mapResult($gif))
            ->filter(fn (?array $gif) => $gif !== null)
            ->values()
            ->all();
    }

    private function mapResult(array $gif): ?array
    {
        $full    = $gif['images']['fixed_height']['url'] ?? $gif['images']['original']['url'] ?? null;
        $preview = $gif['images']['fixed_height_small']['url'] ?? $full;

        if (! $full) {
            return null;
        }

        return [
            'id'          => (string) ($gif['id'] ?? ''),
            'provider'    => 'giphy',
            'url'         => $full,
            'preview_url' => $preview,
            'width'       => (int) ($gif['images']['fixed_height']['width'] ?? 0),
            'height'      => (int) ($gif['images']['fixed_height']['height'] ?? 0),
            'title'       => (string) ($gif['title'] ?? ''),
        ];
    }
}
