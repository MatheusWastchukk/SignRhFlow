<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('signer_name')->nullable()->after('signing_token_expires_at');
            $table->string('signer_email')->nullable()->after('signer_name');
            $table->string('signer_cpf', 11)->nullable()->after('signer_email');
            $table->timestamp('signer_data_collected_at')->nullable()->after('signer_cpf');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'signer_name',
                'signer_email',
                'signer_cpf',
                'signer_data_collected_at',
            ]);
        });
    }
};
