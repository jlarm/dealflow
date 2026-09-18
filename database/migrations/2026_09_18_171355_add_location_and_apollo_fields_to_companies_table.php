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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('city')->nullable()->after('size');
            $table->string('state')->nullable()->after('city');
            $table->string('phone', 50)->nullable()->after('state');
            $table->string('apollo_account_id')->nullable()->unique()->after('phone');

            $table->index(['name', 'state']);
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['name', 'state']);
            $table->dropIndex(['state']);
            $table->dropUnique(['apollo_account_id']);
            $table->dropColumn(['city', 'state', 'phone', 'apollo_account_id']);
        });
    }
};
