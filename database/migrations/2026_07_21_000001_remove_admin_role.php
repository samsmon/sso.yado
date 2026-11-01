<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The 'admin' role is unused - only 'user' and 'superadmin'
        // Users with the admin role are reassigned to 'user' before it is removed
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();

        if ($adminRole) {
            $userRole = DB::table('roles')->where('slug', 'user')->value('id');

            if ($userRole) {
                DB::table('users')
                    ->where('role_id', $adminRole->id)
                    ->update(['role_id' => $userRole]);
            }

            DB::table('roles')->where('slug', 'admin')->delete();
        }
    }

    public function down(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['name' => 'Admin', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
