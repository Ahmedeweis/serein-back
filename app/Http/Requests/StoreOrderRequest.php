<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends CheckoutRequest
{
    // Inherits all strict Zero-Trust rules, validation messages, and authorization from CheckoutRequest
}
