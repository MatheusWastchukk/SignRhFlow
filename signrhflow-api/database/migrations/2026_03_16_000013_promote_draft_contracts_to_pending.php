<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Contract::query()
            ->where('status', Contract::STATUS_DRAFT)
            ->update(['status' => Contract::STATUS_PENDING]);
    }

    public function down(): void
    {
        // Sem rollback seguro para normalizacao de status.
    }
};
