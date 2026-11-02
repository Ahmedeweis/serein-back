<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');


        if (! empty($adminPassword)) {
            User::updateOrCreate(
                ['email' => env('ADMIN_EMAIL', 'serin@gmail.com')],
                [
                    'first_name'  => env('ADMIN_FIRST_NAME', 'Mahmoud'),
                    'last_name'   => env('ADMIN_LAST_NAME', 'Belal'),
                    'bio'         => env('ADMIN_BIO', 'Team Manager'),
                    'phone'       => env('ADMIN_PHONE', '+201011005068'),
                    'password'    => Hash::make($adminPassword),
                    'role'        => 'admin',
                    'country'     => env('ADMIN_COUNTRY', 'Egypt'),
                    'governorate' => env('ADMIN_GOVERNORATE', 'Alexandria'),
                    'address'     => env('ADMIN_ADDRESS', 'Alex, Egypt'),
                    'postal_code' => env('ADMIN_POSTAL_CODE', '2489'),
                    'tax_id'      => env('ADMIN_TAX_ID', 'AS4568384'),
                ]
            );
        }

        $this->call([
            ProductCatalogSeeder::class,
            CheckoutSettingsSeeder::class,
            ShippingZonesSeeder::class,
            OrdersSeeder::class,
            ReviewsSeeder::class,
            CouponSeeder::class,
        ]);
    }
}
