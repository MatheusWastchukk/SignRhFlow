<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SIGNED = 'SIGNED';
    public const STATUS_REJECTED = 'REJECTED';

    public const DELIVERY_EMAIL = 'EMAIL';
    public const DELIVERY_WHATSAPP = 'WHATSAPP';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'autentique_document_id',
        'signing_token',
        'signing_token_expires_at',
        'signer_name',
        'signer_email',
        'signer_cpf',
        'signer_data_collected_at',
        'status',
        'delivery_method',
        'file_path',
        'pdf_generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signing_token_expires_at' => 'datetime',
            'signer_data_collected_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            if (! is_string($contract->signing_token) || $contract->signing_token === '') {
                $contract->signing_token = Str::random(64);
            }

            if ($contract->signing_token_expires_at === null) {
                $contract->signing_token_expires_at = now()->addDays(7);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
