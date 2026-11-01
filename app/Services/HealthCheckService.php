<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthCheckService
{
    /**
     * Checks this service's health for external monitoring (the Yado landing page).
     * Deliberately only a light query (`SELECT 1`) - enough to confirm the DB connection is alive,
     * without making this endpoint slow/heavy.
     *
     * @return array{status: string, service: string, timestamp?: string, error?: string}
     */
    public function check(): array
    {
        $service = Str::slug(config('app.name', 'sso-engine'));

        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'service' => $service,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'service' => $service,
                'error' => $e->getMessage(),
            ];
        }
    }
}
