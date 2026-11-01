<?php

namespace App\Actions\Dashboard;

use App\Mail\InvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteUserAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function execute(User $inviter, string $email, int $roleId): UserInvitation
    {
        $invitation = UserInvitation::create([
            'email' => $email,
            'token' => Str::random(64),
            'role_id' => $roleId,
            'expires_at' => now()->addHours(24),
            'created_by' => $inviter->id,
        ]);

        Mail::to($email)->send(new InvitationMail($invitation));

        $this->auditLog->record('dashboard.user_invited', "Invitation sent to \"{$email}\"");

        return $invitation;
    }
}
