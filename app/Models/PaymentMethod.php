<?php

namespace App\Models;

use App\Models\Concerns\HasUploadedLogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, HasUploadedLogo;

    protected $table = 'payment_methods';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'badge',
        'instructions_en',
        'instructions_ar',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
