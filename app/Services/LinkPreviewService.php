<?php

namespace App\Services;

use App\Models\Tenant\LinkPreview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkPreviewService
{
    /** Never fetch more than this many links out of a single message body. */
    private const MAX_URLS_PER_MESSAGE = 3;

    private const MAX_REDIRECTS = 3;

    /** Cap on how much of a page body we bother parsing. */
    private const MAX_HTML_BYTES = 500_000;

    /**
     * Pull out up to MAX_URLS_PER_MESSAGE distinct http(s) URLs from a message body.
     *
     * @return string[]
     */
    public function extractUrls(string $body): array
    {
        if (! preg_match_all('/https?:\/\/[^\s<>"\')]+/i', $body, $matches)) {
            return [];
        }

        return array_slice(array_values(array_unique($matches[0])), 0, self::MAX_URLS_PER_MESSAGE);
    }

    /**
     * Fetch (or return the cached) preview for a URL.
     *
     * Every message that repeats a URL hits this cache instead of refetching,
     * and a URL that fails to resolve to a safe preview is still cached as
     * `fetch_failed` so a spammy/broken link isn't refetched on every message.
     */
    public function fetch(string $url): LinkPreview
    {
        $urlHash = hash('sha256', $url);

        $existing = LinkPreview::where('url_hash', $urlHash)->first();
        if ($existing) {
            return $existing;
        }

        $meta = $this->safeFetchHtml($url, self::MAX_REDIRECTS);

        return LinkPreview::create([
            'url'           => $url,
            'url_hash'      => $urlHash,
            'title'         => $meta['title'] ?? null,
            'description'   => $meta['description'] ?? null,
            'image_url'     => $meta['image'] ?? null,
            'site_name'     => $meta['site_name'] ?? null,
            'fetched_at'    => now(),
            'fetch_failed'  => $meta === null,
        ]);
    }

    /**
     * True when a URL is safe to have the server fetch: http(s) only, and the
     * host does not resolve to a loopback/private/link-local/reserved IP.
     * Used both for the initial OG-tag fetch and for proxying the resulting
     * image, since the "og:image" tag on an attacker-controlled page is just
     * as much attacker-controlled input as the original URL.
     */
    public function isUrlSafe(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if (! $host) {
            return false;
        }

        // Host is already a literal IP — validate it directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // Resolve the hostname and reject if ANY resolved address is
        // private/reserved — DNS rebinding / multi-A-record tricks both land here.
        $ips = @gethostbynamel($host);
        if (! $ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch a URL and parse its OG tags, manually following redirects (with a
     * safety check on every hop) rather than letting the HTTP client auto-follow —
     * an SSRF-safe URL can still redirect to an internal one.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    private function safeFetchHtml(string $url, int $redirectsLeft): ?array
    {
        if ($redirectsLeft < 0 || ! $this->isUrlSafe($url)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'TeamCollabLinkPreview/1.0'])
                ->timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning("LinkPreviewService: request failed for {$url}: {$e->getMessage()}");
            return null;
        }

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $location = $response->header('Location');
            if (! $location) {
                return null;
            }

            return $this->safeFetchHtml($this->resolveUrl($url, $location), $redirectsLeft - 1);
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = $response->header('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        return $this->parseOgTags(substr($response->body(), 0, self::MAX_HTML_BYTES), $url);
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}
     */
    private function parseOgTags(string $html, string $baseUrl): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $og = fn (string $property) => $xpath
            ->query("//meta[@property='og:{$property}']/@content")
            ->item(0)?->nodeValue;

        $title       = $og('title') ?? $xpath->query('//title')->item(0)?->textContent;
        $description = $og('description') ?? $xpath->query("//meta[@name='description']/@content")->item(0)?->nodeValue;
        $image       = $og('image');
        $siteName    = $og('site_name');

        if ($image && ! parse_url($image, PHP_URL_SCHEME)) {
            $image = $this->resolveUrl($baseUrl, $image);
        }

        return [
            'title'       => $title ? Str::limit(trim($title), 200, '') : null,
            'description' => $description ? Str::limit(trim($description), 500, '') : null,
            'image'       => $image,
            'site_name'   => $siteName ? trim($siteName) : null,
        ];
    }

    private function resolveUrl(string $base, string $relativeOrAbsolute): string
    {
        if (parse_url($relativeOrAbsolute, PHP_URL_SCHEME)) {
            return $relativeOrAbsolute;
        }

        $baseParts = parse_url($base);
        $scheme    = $baseParts['scheme'] ?? 'https';
        $host      = $baseParts['host'] ?? '';
        $port      = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

        if (str_starts_with($relativeOrAbsolute, '//')) {
            return "{$scheme}:{$relativeOrAbsolute}";
        }

        if (str_starts_with($relativeOrAbsolute, '/')) {
            return "{$scheme}://{$host}{$port}{$relativeOrAbsolute}";
        }

        $dir = rtrim(dirname($baseParts['path'] ?? '/'), '/');

        return "{$scheme}://{$host}{$port}{$dir}/{$relativeOrAbsolute}";
    }
}
