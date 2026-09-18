<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gap left between neighbouring cards, so a card can be dropped between two without renumbering.
     */
    private const int GAP = 1024;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->double('pipeline_position')->default(0)->after('status');

            $table->index(['status', 'pipeline_position']);
        });

        // Keep the board looking as it did: each stage starts in score order.
        foreach (DB::table('contacts')->distinct()->pluck('status') as $status) {
            $position = 0;

            DB::table('contacts')
                ->where('status', $status)
                ->orderByDesc('score')
                ->orderByDesc('id')
                ->select('id')
                ->lazy(500)
                ->each(function (object $contact) use (&$position): void {
                    $position += self::GAP;

                    DB::table('contacts')->where('id', $contact->id)->update(['pipeline_position' => $position]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['status', 'pipeline_position']);
            $table->dropColumn('pipeline_position');
        });
    }
};
