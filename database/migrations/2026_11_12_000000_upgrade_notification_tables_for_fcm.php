<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                if (! Schema::hasColumn('device_tokens', 'device_token')) {
                    $table->string('device_token')->nullable()->after('device_id')->index();
                }
                if (! Schema::hasColumn('device_tokens', 'token')) {
                    $table->string('token')->nullable()->after('device_token');
                }
                if (! Schema::hasColumn('device_tokens', 'push_token')) {
                    $table->string('push_token')->nullable()->after('token');
                }
                if (! Schema::hasColumn('device_tokens', 'expo_push_token')) {
                    $table->string('expo_push_token')->nullable()->after('push_token');
                }
                if (! Schema::hasColumn('device_tokens', 'provider')) {
                    $table->string('provider', 20)->default('fcm')->after('platform');
                }
                if (! Schema::hasColumn('device_tokens', 'app_version')) {
                    $table->string('app_version', 50)->nullable();
                }
                if (! Schema::hasColumn('device_tokens', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable();
                }
            });

            // Backfill device_token and push_token if either was populated
            if (Schema::hasColumn('device_tokens', 'token')) {
                DB::table('device_tokens')
                    ->whereNull('device_token')
                    ->whereNotNull('token')
                    ->update(['device_token' => DB::raw('token')]);
            }
            if (Schema::hasColumn('device_tokens', 'push_token')) {
                DB::table('device_tokens')
                    ->whereNull('device_token')
                    ->whereNotNull('push_token')
                    ->update(['device_token' => DB::raw('push_token')]);
                DB::table('device_tokens')
                    ->whereNull('push_token')
                    ->whereNotNull('device_token')
                    ->update(['push_token' => DB::raw('device_token')]);
            }
        }

        if (Schema::hasTable('notification_schedules')) {
            Schema::table('notification_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('notification_schedules', 'device_id')) {
                    $table->string('device_id')->nullable()->after('user_id')->index();
                }
                if (! Schema::hasColumn('notification_schedules', 'device_token_id')) {
                    $table->string('device_token_id')->nullable()->after('device_id')->index();
                }
                if (! Schema::hasColumn('notification_schedules', 'notification_type')) {
                    $table->string('notification_type', 60)->default('lifecycle_promo')->after('segment')->index();
                }
                if (! Schema::hasColumn('notification_schedules', 'last_sent_at')) {
                    $table->timestamp('last_sent_at')->nullable()->after('notification_type');
                }
                if (! Schema::hasColumn('notification_schedules', 'next_send_at')) {
                    $table->timestamp('next_send_at')->nullable()->after('last_sent_at')->index();
                }
                if (! Schema::hasColumn('notification_schedules', 'last_notified_at')) {
                    $table->timestamp('last_notified_at')->nullable()->after('next_send_at');
                }
                if (! Schema::hasColumn('notification_schedules', 'next_eligible_at')) {
                    $table->timestamp('next_eligible_at')->nullable()->after('last_notified_at')->index();
                }
                if (! Schema::hasColumn('notification_schedules', 'cycle_count')) {
                    $table->unsignedInteger('cycle_count')->default(0)->after('next_eligible_at');
                }
                if (! Schema::hasColumn('notification_schedules', 'metadata')) {
                    $table->json('metadata')->nullable()->after('cycle_count');
                }
            });

            if (Schema::hasColumn('notification_schedules', 'next_eligible_at')) {
                DB::table('notification_schedules')
                    ->whereNull('next_send_at')
                    ->whereNotNull('next_eligible_at')
                    ->update(['next_send_at' => DB::raw('next_eligible_at')]);
            }

            if (Schema::hasColumn('notification_schedules', 'last_notified_at')) {
                DB::table('notification_schedules')
                    ->whereNull('last_sent_at')
                    ->whereNotNull('last_notified_at')
                    ->update(['last_sent_at' => DB::raw('last_notified_at')]);
            }
        }
    }

    public function down(): void
    {
        // Safe backward-compatible no-op on down
    }
};
