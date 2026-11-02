<?php

namespace App\Models;

use App\Models\Concerns\HasUploadedLogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory, HasUploadedLogo;

    protected $table = 'shipping_methods';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name_en',
        'name_ar',
        'estimate_en',
        'estimate_ar',
        'cost',
        'logo_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cost' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
