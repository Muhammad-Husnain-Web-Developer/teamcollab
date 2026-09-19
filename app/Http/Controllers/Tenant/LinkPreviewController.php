<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LinkPreview;
use App\Services\LinkPreviewService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class LinkPreviewController extends Controller
{
    /**
     * Proxy a link preview's og:image. Never embeds the third-party image URL
     * directly in the page — every viewer's browser talks only to this app.
     */
    public function image(LinkPreview $linkPreview, LinkPreviewService $previews): Response
    {
        abort_unless($linkPreview->image_url, 404);
        abort_unless($previews->isUrlSafe($linkPreview->image_url), 404);

        try {
            $response = Http::timeout(5)->get($linkPreview->image_url);
        } catch (\Throwable) {
            abort(404);
        }

        abort_unless($response->successful(), 404);

        $contentType = $response->header('Content-Type', 'application/octet-stream');
        abort_unless(str_starts_with($contentType, 'image/'), 404);

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
