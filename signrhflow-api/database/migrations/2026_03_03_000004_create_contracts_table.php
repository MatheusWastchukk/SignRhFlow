<?php

use App\Models\Contract;
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
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->string('autentique_document_id')->nullable()->index();
            $table->enum('status', [
                Contract::STATUS_DRAFT,
                Contract::STATUS_PENDING,
                Contract::STATUS_SIGNED,
                Contract::STATUS_REJECTED,
            ])->default(Contract::STATUS_DRAFT);
            $table->enum('delivery_method', [
                Contract::DELIVERY_EMAIL,
                Contract::DELIVERY_WHATSAPP,
            ]);
            $table->string('file_path');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
