<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code'       => 'SEREIN10',
                'percentage' => 10,
                'is_active'  => true,
            ],
            [
                'code'       => 'WELCOME20',
                'percentage' => 20,
                'is_active'  => true,
            ],
            [
                'code'       => 'MEGA50',
                'percentage' => 50,
                'is_active'  => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
