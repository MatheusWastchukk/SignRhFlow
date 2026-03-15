<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->timestamp('pdf_generated_at')->nullable()->after('file_path');
        });

        DB::table('contracts')
            ->whereNotNull('file_path')
            ->update([
                'pdf_generated_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('pdf_generated_at');
        });
    }
};
