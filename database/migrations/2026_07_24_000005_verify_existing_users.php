<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is now required going forward - users who existed before
     * this feature (and therefore were never "verified") have been trusted since
     * the start (created via register/invite/the old seeder), so they are backfilled as verified
     * so they do not get locked out by the new `verified` middleware.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // There is no safe way to tell which users were genuinely verified vs
        // backfilled by this migration - deliberately nothing is reverted.
    }
};
