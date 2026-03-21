<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Contract::query()
            ->whereNotNull('signed_at')
            ->where('status', '!=', Contract::STATUS_SIGNED)
            ->update(['status' => Contract::STATUS_SIGNED]);
    }

    public function down(): void
    {
    }
};
