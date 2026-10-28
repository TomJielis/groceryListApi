<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes login history, failed login attempts, the email log and support conversations once they are older than the retention period
 * (config app.personal_data_retention_months, 12 by default). They hold IP addresses and email addresses, so the
 * privacy policy (article 8) promises they are not kept longer than that. Runs daily (routes/console.php).
 */
class PrunePersonalData extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Remove login history, failed login attempts, sent-email logs and support tickets older than the retention period';

    public function handle(): int
    {
        $months = (int) config('app.personal_data_retention_months');
        $cutoff = now()->subMonths($months);

        $logins = DB::table('successful_logins')->where('logged_in_at', '<', $cutoff)->delete();
        $attempts = DB::table('invalid_login_attempts')->where('created_at', '<', $cutoff)->delete();
        $emails = DB::table('sent_emails')->where('created_at', '<', $cutoff)->delete();
        // A support conversation goes once nothing was written in it for the whole period (messages go with it)
        $tickets = DB::table('support_tickets')->where('last_message_at', '<', $cutoff)->delete();

        $this->info("Removed {$logins} logins, {$attempts} failed login attempts, {$emails} email logs and {$tickets} support tickets older than {$months} months.");
        return self::SUCCESS;
    }
}
