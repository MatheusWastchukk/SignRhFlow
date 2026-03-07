<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->string('event_hash', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->dropUnique('webhook_logs_event_hash_unique');
            $table->dropColumn('event_hash');
        });
    }
};
