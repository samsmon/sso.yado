<?php

namespace App\Services\Dashboard;

use App\Models\OAuth\Client;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Laravel\Passport\ClientRepository;

class ApplicationService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly AuditLogService $auditLog,
    ) {}

    public function list(): Collection
    {
        return Client::where('revoked', false)->orderByDesc('created_at')->get();
    }

    public function create(array $data): Client
    {
        $client = $this->clients->createAuthorizationCodeGrantClient(
            name: $data['name'],
            redirectUris: [$data['redirect_uri']],
            confidential: true,
        );

        $client->forceFill(['secret_encrypted' => $client->plainSecret])->save();

        $this->auditLog->record('dashboard.application_created', "Application \"{$data['name']}\" created");

        return $client;
    }

    public function update(Client $client, array $data): void
    {
        $this->clients->update($client, $data['name'], [$data['redirect_uri']]);

        $this->auditLog->record('dashboard.application_updated', "Application \"{$data['name']}\" updated");
    }

    public function delete(Client $client): void
    {
        foreach ($client->tokens as $token) {
            $token->revoke();
            $token->refreshToken?->update(['revoked' => true]);
        }

        $name = $client->name;

        // Passport's ClientRepository::delete() only flips the `revoked` flag,
        // it does not remove the row - delete it directly so it stops showing up.
        $client->delete();

        $this->auditLog->record('dashboard.application_deleted', "Application \"{$name}\" deleted");
    }
}
