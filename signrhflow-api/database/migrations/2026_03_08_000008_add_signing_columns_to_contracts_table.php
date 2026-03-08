<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('signing_token', 80)->nullable()->unique()->after('autentique_document_id');
            $table->timestamp('signing_token_expires_at')->nullable()->after('signing_token');
        });

        DB::table('contracts')
            ->select('id')
            ->orderBy('created_at')
            ->get()
            ->each(function (object $contract): void {
                DB::table('contracts')
                    ->where('id', $contract->id)
                    ->update([
                        'signing_token' => Str::random(64),
                        'signing_token_expires_at' => now()->addDays(7),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropUnique('contracts_signing_token_unique');
            $table->dropColumn(['signing_token', 'signing_token_expires_at']);
        });
    }
};
