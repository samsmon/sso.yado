<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToPgsql extends Command
{
    protected $signature = 'db:migrate-to-pgsql {--force : Lewati konfirmasi}';

    protected $description = 'Copy all data from the old MySQL connection (MYSQL_LEGACY_*) to the active Postgres connection (pgsql)';

    /**
     * Order must be FK-safe: parent before child.
     */
    private const TABLES = [
        'roles',
        'users',
        'oauth_clients',
        'oauth_access_tokens',
        'oauth_refresh_tokens',
        'oauth_auth_codes',
        'oauth_device_codes',
        'user_invitations',
        'settings',
        'audit_logs',
        'password_reset_tokens',
        'sessions',
    ];

    /**
     * Boolean columns per table - MySQL PDO returns "0"/"1" strings, and the column
     * boolean Postgres butuh true/false asli.
     */
    private const BOOLEAN_COLUMNS = [
        'users' => ['is_active'],
        'oauth_clients' => ['revoked'],
        'oauth_access_tokens' => ['revoked'],
        'oauth_refresh_tokens' => ['revoked'],
        'oauth_auth_codes' => ['revoked'],
        'oauth_device_codes' => ['revoked'],
    ];

    /**
     * Tables with an auto-increment integer PK whose sequence needs to be reset
     * after bulk insert (explicit ids are passed, not via nextval).
     */
    private const SERIAL_PK_TABLES = ['roles', 'users', 'user_invitations', 'settings', 'audit_logs'];

    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->error('The active DB_CONNECTION is not pgsql. Set DB_CONNECTION=pgsql in .env before migrating.');
            return self::FAILURE;
        }

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to the mysql (legacy) connection. Check MYSQL_LEGACY_* in .env: '.$e->getMessage());
            return self::FAILURE;
        }

        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = Schema::connection('mysql')->hasTable($table)
                ? DB::connection('mysql')->table($table)->count()
                : 0;
        }

        $this->table(['Table', 'Rows in old MySQL'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values());

        if (!$this->option('force') && !$this->confirm('Continue? This will DELETE THE CONTENTS of the tables above in the target Postgres database and replace them with data from MySQL.')) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        DB::connection('pgsql')->statement(
            'TRUNCATE TABLE '.implode(', ', self::TABLES).' RESTART IDENTITY CASCADE'
        );

        foreach (self::TABLES as $table) {
            if ($counts[$table] === 0) {
                $this->line("- {$table}: empty, skipped.");
                continue;
            }

            $migrated = 0;
            DB::connection('mysql')->table($table)->orderBy(
                Schema::connection('mysql')->getColumnListing($table)[0]
            )->chunk(500, function ($rows) use ($table, &$migrated) {
                $booleanColumns = self::BOOLEAN_COLUMNS[$table] ?? [];

                $prepared = $rows->map(function ($row) use ($booleanColumns) {
                    $row = (array) $row;
                    foreach ($booleanColumns as $column) {
                        if (array_key_exists($column, $row) && $row[$column] !== null) {
                            $row[$column] = (bool) $row[$column];
                        }
                    }
                    return $row;
                })->all();

                DB::connection('pgsql')->table($table)->insert($prepared);
                $migrated += count($prepared);
            });

            $this->line("- {$table}: {$migrated} rows migrated.");

            if (in_array($table, self::SERIAL_PK_TABLES, true)) {
                DB::connection('pgsql')->statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), (SELECT MAX(id) FROM {$table}) IS NOT NULL)"
                );
            }
        }

        $this->info('Migration complete. Verify the row counts and try logging in before deleting the old MySQL database.');

        return self::SUCCESS;
    }
}
