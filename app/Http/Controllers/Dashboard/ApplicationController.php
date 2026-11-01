<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OAuth\Client;
use App\Rules\RedirectUriRule;
use App\Services\AuditLogService;
use App\Services\Dashboard\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $service,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard/Applications/Index', [
            'clients' => $this->service->list(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Dashboard/Applications/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:1000', new RedirectUriRule()],
        ]);

        if ($error = $this->duplicateError($validated)) {
            return back()->withErrors($error)->withInput();
        }

        $client = $this->service->create($validated);

        return redirect()->route('dashboard.applications.show', $client->id)
            ->with('new_secret', $client->plainSecret)
            ->with('success', 'Application created successfully.');
    }

    public function show(Client $application): InertiaResponse
    {
        return Inertia::render('Dashboard/Applications/Show', ['client' => $application]);
    }

    public function update(Request $request, Client $application): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:1000', new RedirectUriRule()],
        ]);

        if ($error = $this->duplicateError($validated, $application)) {
            return back()->withErrors($error)->withInput();
        }

        $this->service->update($application, $validated);

        return back()->with('success', 'Application updated.');
    }

    /**
     * @param  array{name: string, redirect_uri: string}  $data
     * @return array<string, string>|null
     */
    private function duplicateError(array $data, ?Client $ignoring = null): ?array
    {
        $others = Client::query()
            ->where('revoked', false)
            ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
            ->get();

        $nameTaken = $others->contains(fn (Client $client) => $client->name === $data['name']);

        if ($nameTaken) {
            return ['name' => 'Application name is already in use.'];
        }

        $redirectTaken = $others->contains(
            fn (Client $client) => in_array($data['redirect_uri'], $client->redirect_uris, true)
        );

        if ($redirectTaken) {
            return ['redirect_uri' => 'Redirect URI is already used by another application.'];
        }

        return null;
    }

    public function revealSecret(Client $application): JsonResponse
    {
        $this->auditLog->record(
            'dashboard.application_secret_revealed',
            "Client secret of application \"{$application->name}\" viewed",
        );

        return response()->json(['secret' => $application->secret_encrypted]);
    }

    public function destroy(Client $application): RedirectResponse
    {
        $this->service->delete($application);

        return redirect()->route('dashboard.applications.index')
            ->with('success', 'Application deleted and tokens revoked.');
    }
}
