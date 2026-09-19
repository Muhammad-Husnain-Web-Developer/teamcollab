<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\GifProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GifController extends Controller
{
    public function __construct(private readonly GifProviderService $gifs)
    {
    }

    public function search(Request $request): JsonResponse
    {
        if (! $this->gifs->isConfigured()) {
            return response()->json(['configured' => false, 'gifs' => []]);
        }

        $query = trim((string) $request->query('q', ''));

        return response()->json([
            'configured' => true,
            'gifs'       => $query === '' ? [] : $this->gifs->search($query),
        ]);
    }

    public function trending(): JsonResponse
    {
        if (! $this->gifs->isConfigured()) {
            return response()->json(['configured' => false, 'gifs' => []]);
        }

        return response()->json(['configured' => true, 'gifs' => $this->gifs->trending()]);
    }
}
