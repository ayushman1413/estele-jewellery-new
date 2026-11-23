<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('device_token_id')->nullable();
            $table->string('segment', 30)->index();
            $table->string('notification_type', 60)->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'notification_type'], 'notif_sched_user_type_unique');
            $table->unique(['device_token_id', 'notification_type'], 'notif_sched_device_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
