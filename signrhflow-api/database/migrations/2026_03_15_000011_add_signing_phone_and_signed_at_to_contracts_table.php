<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('signer_phone')->nullable()->after('signer_cpf');
            $table->string('signer_phone_country', 5)->nullable()->after('signer_phone');
            $table->timestamp('signed_at')->nullable()->after('pdf_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['signer_phone', 'signer_phone_country', 'signed_at']);
        });
    }
};
