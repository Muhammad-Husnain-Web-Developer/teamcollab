<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $searchService)
    {
    }

    public function __invoke(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'q'    => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['messages', 'channels', 'files', 'members'])],
        ]);

        $results = $this->searchService->search(
            $validated['q'],
            auth()->user(),
            ['type' => $validated['type'] ?? 'all'],
        );

        if ($request->wantsJson()) {
            return response()->json($results);
        }

        return Inertia::render('Search/Results', [
            'query'   => $validated['q'],
            'results' => $results,
        ]);
    }
}
