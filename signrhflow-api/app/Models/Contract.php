<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'status',
        'delivery_method',
        'file_path',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
