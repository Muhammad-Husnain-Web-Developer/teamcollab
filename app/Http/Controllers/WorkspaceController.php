<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\CreateWorkspaceRequest;
use App\Http\Requests\Workspace\InviteMemberRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService)
    {
    }

    public function index(): Response
    {
        $user = auth()->user();

        $tenantIds = \DB::connection('mysql')
            ->table('tenant_users')
            ->where('user_id', $user->id)
            ->pluck('tenant_id');

        $tenants = Tenant::whereIn('id', $tenantIds)->with('domains')->get();

        return Inertia::render('Workspace/Index', [
            'tenants' => $tenants->map(fn(Tenant $tenant) => [
                'id'       => $tenant->id,
                'name'     => $tenant->name,
                'slug'     => $tenant->slug,
                'logo_url' => $tenant->logo_url,
                'domain'   => $tenant->domains->first()?->domain,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workspace/Create');
    }

    public function store(CreateWorkspaceRequest $request): RedirectResponse
    {
        $this->workspaceService->create($request->validated(), auth()->user());

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace created successfully! Add the workspace domain to your hosts file to access it.');
    }

    public function show(Tenant $tenant): RedirectResponse
    {
        $this->authorize('view', $tenant);

        $domain = $tenant->domains->first();

        if ($domain) {
            $scheme = request()->secure() ? 'https' : 'http';
            return redirect("{$scheme}://{$domain->domain}");
        }

        return redirect()->route('workspaces.index')
            ->with('error', 'Workspace domain not found.');
    }

    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        return Inertia::render('Workspace/Settings', [
            'tenant' => [
                'id'           => $tenant->id,
                'name'         => $tenant->name,
                'slug'         => $tenant->slug,
                'logo_url'     => $tenant->logo_url,
                'timezone'     => $tenant->timezone,
                'company_size' => $tenant->company_size,
                'settings'     => $tenant->settings ?? [],
            ],
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->workspaceService->update($tenant, $request->validated());

        return back()->with('success', 'Workspace updated successfully.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $this->workspaceService->delete($tenant);

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    public function inviteMember(InviteMemberRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->workspaceService->inviteMember($tenant, $request->validated());

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function removeMember(Tenant $tenant, User $member): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->workspaceService->removeMember($tenant, $member);

        return back()->with('success', 'Member removed from workspace.');
    }

    /**
     * Consume an invite token from WorkspaceInviteNotification and join the
     * authenticated user to the workspace. No `authorize()` call here by
     * design — access is proven by the token itself, not existing membership.
     */
    public function acceptInvite(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $this->workspaceService->acceptInvite($tenant, $request->string('token')->toString(), auth()->user());

        return redirect()->route('workspaces.show', $tenant)
            ->with('success', "You've joined {$tenant->name}.");
    }
}
