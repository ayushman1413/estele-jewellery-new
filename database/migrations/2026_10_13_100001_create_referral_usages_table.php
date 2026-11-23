<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('referrer_credit', 10, 2);
            $table->decimal('referee_credit', 10, 2);
            $table->timestamps();

            $table->unique('referee_id'); // one conversion per referred user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_usages');
    }
};
