<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes all personnel and resident data (and everything they created) so the
 * system starts clean, e.g. before a demonstration. Admin accounts and
 * stations (locations) are kept.
 */
class ResetDemoData extends Command
{
    protected $signature = 'qrt:reset-data {--force : Do not ask for confirmation}';

    protected $description = 'Delete all personnel, residents, reports, SOS alarms, chats, calls, attendance, schedules and audit logs (keeps admins and stations)';

    /** Tables emptied completely. */
    private const TABLES = [
        'calls',
        'messages',
        'conversations',
        'device_tokens',
        'alarms',
        'reports',
        'help_requests',
        'audit_logs',
        'attendances',
        'schedules',
    ];

    /** Uploaded files that belong to the deleted data. */
    private const UPLOAD_FOLDERS = ['chat', 'reports'];

    public function handle(): int
    {
        $users = DB::table('users')->where('role', '!=', 'admin');
        $admins = DB::table('users')->where('role', 'admin')->pluck('email');

        if ($admins->isEmpty()) {
            $this->error('No admin account found. Nothing was deleted, so you can still log in.');
            return self::FAILURE;
        }

        $this->info('This will permanently delete:');
        $this->line(sprintf('  %-16s %d', 'personnel', (clone $users)->where('role', 'personnel')->count()));
        $this->line(sprintf('  %-16s %d', 'residents', (clone $users)->where('role', 'resident')->count()));
        foreach ($this->existingTables() as $table) {
            $this->line(sprintf('  %-16s %d', $table, DB::table($table)->count()));
        }
        $this->line('  plus their photos (avatars, chat and report photos) and app logins.');
        $this->newLine();
        $this->info('Kept: admin account(s) ' . $admins->implode(', ') . ' and all stations (locations).');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Delete all of this? It cannot be undone', false)) {
            $this->warn('Cancelled. Nothing was deleted.');
            return self::SUCCESS;
        }

        $userIds = (clone $users)->pluck('id');
        $avatars = (clone $users)->whereNotNull('avatar')->pluck('avatar');

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($userIds) {
                foreach ($this->existingTables() as $table) {
                    DB::table($table)->delete();
                }

                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'like', '%User')
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'like', '%Personnel')
                    ->delete();

                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }

                DB::table('users')->whereIn('id', $userIds)->delete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $disk = Storage::disk('public');
        foreach ($avatars as $avatar) {
            $disk->delete($avatar);
        }
        foreach (self::UPLOAD_FOLDERS as $folder) {
            $disk->deleteDirectory($folder);
        }

        $this->info('Done. Only the admin account(s) and stations remain.');
        return self::SUCCESS;
    }

    private function existingTables(): array
    {
        return array_values(array_filter(self::TABLES, fn ($table) => Schema::hasTable($table)));
    }
}
