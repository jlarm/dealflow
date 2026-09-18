<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->index()->constrained()->cascadeOnDelete();
            $table->string('message_id')->index();
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
