<?php

namespace Database\Seeders;

use App\Models\Governorate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZonesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = [
            [
                'name_ar'      => 'القاهرة الكبرى',
                'name_en'      => 'Greater Cairo',
                'price'        => 97.00,
                'min_days'     => 1,
                'max_days'     => 2,
                'is_active'    => true,
                'is_protected' => true,
                'governorates' => [
                    'Cairo',
                    'Giza',
                    'Qaliubiya',
                ],
            ],
            [
                'name_ar'      => 'الإسكندرية',
                'name_en'      => 'Alexandria',
                'price'        => 102.00,
                'min_days'     => 2,
                'max_days'     => 3,
                'is_active'    => true,
                'is_protected' => false,
                'governorates' => [
                    'Alexandria',
                ],
            ],
            [
                'name_ar'      => 'الدلتا والقناة',
                'name_en'      => 'Delta & Canal',
                'price'        => 110.00,
                'min_days'     => 2,
                'max_days'     => 4,
                'is_active'    => true,
                'is_protected' => false,
                'governorates' => [
                    'Dakahlia',
                    'Gharbia',
                    'Sharkia',
                    'Menofia',
                    'Kafr Al sheikh',
                    'Damietta',
                    'Port Said',
                    'Ismailia',
                    'Suez',
                    'Beheira',
                ],
            ],
            [
                'name_ar'      => 'الصعيد والبحر الأحمر والمحافظات الحدودية',
                'name_en'      => 'Upper Egypt, Red Sea & Frontiers',
                'price'        => 140.00,
                'min_days'     => 3,
                'max_days'     => 5,
                'is_active'    => true,
                'is_protected' => false,
                'governorates' => [
                    'Fayoum',
                    'Beni Suef',
                    'Minya',
                    'Assiut',
                    'Sohag',
                    'Qena',
                    'Luxor',
                    'Aswan',
                    'Red Sea',
                    'South Sinai',
                    'North Sinai',
                    'New Valley',
                    'Matrouh',
                ],
            ],
        ];

        foreach ($zones as $zoneData) {
            $governorateNames = $zoneData['governorates'];
            unset($zoneData['governorates']);

            $zone = ShippingZone::updateOrCreate(
                ['name_en' => $zoneData['name_en']],
                $zoneData
            );

            // Assign governorates to this zone by English name or Arabic name match
            Governorate::where(function ($query) use ($governorateNames) {
                $query->whereIn('name_en', $governorateNames);
            })->update(['shipping_zone_id' => $zone->id]);
        }
    }
}
