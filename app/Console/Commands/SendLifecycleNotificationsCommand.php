<?php

namespace App\Console\Commands;

use App\Jobs\SendLifecyclePushNotificationsJob;
use Illuminate\Console\Command;

class SendLifecycleNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:lifecycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger lifecycle push notifications for due schedules (Guest 7d, Registered 30d, Purchased 90d)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching SendLifecyclePushNotificationsJob...');
        SendLifecyclePushNotificationsJob::dispatchSync(app(\App\Services\PushNotificationService::class));
        $this->info('Lifecycle push notifications processed successfully.');

        return self::SUCCESS;
    }
}

