<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $egypt = Country::updateOrCreate(
            ['code' => 'EG'],
            [
                'name_ar'    => 'مصر',
                'name_en'    => 'Egypt',
                'phone_code' => '+20',
                'is_active'  => true,
            ]
        );

        $governorates = [
            ['name_ar' => 'القاهرة', 'name_en' => 'Cairo'],
            ['name_ar' => 'الجيزة', 'name_en' => 'Giza'],
            ['name_ar' => 'الإسكندرية', 'name_en' => 'Alexandria'],
            ['name_ar' => 'الدقهلية', 'name_en' => 'Dakahlia'],
            ['name_ar' => 'البحر الأحمر', 'name_en' => 'Red Sea'],
            ['name_ar' => 'البحيرة', 'name_en' => 'Beheira'],
            ['name_ar' => 'الفيوم', 'name_en' => 'Fayoum'],
            ['name_ar' => 'الغربية', 'name_en' => 'Gharbia'],
            ['name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia'],
            ['name_ar' => 'المنوفية', 'name_en' => 'Menofia'],
            ['name_ar' => 'المنيا', 'name_en' => 'Minya'],
            ['name_ar' => 'القليوبية', 'name_en' => 'Qaliubiya'],
            ['name_ar' => 'الوادي الجديد', 'name_en' => 'New Valley'],
            ['name_ar' => 'السويس', 'name_en' => 'Suez'],
            ['name_ar' => 'أسوان', 'name_en' => 'Aswan'],
            ['name_ar' => 'أسيوط', 'name_en' => 'Assiut'],
            ['name_ar' => 'بني سويف', 'name_en' => 'Beni Suef'],
            ['name_ar' => 'بورسعيد', 'name_en' => 'Port Said'],
            ['name_ar' => 'دمياط', 'name_en' => 'Damietta'],
            ['name_ar' => 'الشرقية', 'name_en' => 'Sharkia'],
            ['name_ar' => 'جنوب سيناء', 'name_en' => 'South Sinai'],
            ['name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr Al sheikh'],
            ['name_ar' => 'مطروح', 'name_en' => 'Matrouh'],
            ['name_ar' => 'الأقصر', 'name_en' => 'Luxor'],
            ['name_ar' => 'قنا', 'name_en' => 'Qena'],
            ['name_ar' => 'شمال سيناء', 'name_en' => 'North Sinai'],
            ['name_ar' => 'سوهاج', 'name_en' => 'Sohag'],
        ];

        foreach ($governorates as $gov) {
            Governorate::updateOrCreate(
                ['country_id' => $egypt->id, 'name_en' => $gov['name_en']],
                ['name_ar' => $gov['name_ar'], 'is_active' => true]
            );
        }
    }
}
