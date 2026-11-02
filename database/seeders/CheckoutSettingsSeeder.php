<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class CheckoutSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shippingMethods = [
            [
                'id' => 'dhl',
                'name_en' => 'DHL Express',
                'name_ar' => 'دي إتش إل إكسبريس',
                'estimate_en' => 'Estimated Delivery Time: 2 - 3 Days',
                'estimate_ar' => 'وقت التوصيل المتوقع: ٢ - ٣ أيام',
                'cost' => 0.00,
                'logo_type' => 'dhl',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'fedex',
                'name_en' => 'Aramex / FedEx',
                'name_ar' => 'أرامكس / فيديكس',
                'estimate_en' => 'Estimated Delivery Time: 24 - 48 Hours',
                'estimate_ar' => 'توصيل سريع: خلال ٢٤ - ٤٨ ساعة',
                'cost' => 45.00,
                'logo_type' => 'fedex',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 'serein',
                'name_en' => 'Serein Private Courier',
                'name_ar' => 'مندوب سيرين الخاص',
                'estimate_en' => 'Estimated Delivery Time: 1 - 2 Days',
                'estimate_ar' => 'توصيل مباشر: خلال ١ - ٢ يوم',
                'cost' => 30.00,
                'logo_type' => 'serein',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($shippingMethods as $method) {
            ShippingMethod::updateOrCreate(
                ['id' => $method['id']],
                $method
            );
        }

        $paymentMethods = [
            [
                'id' => 'card',
                'title_en' => 'Credit Card',
                'title_ar' => 'بطاقة ائتمان / فيزا - ماستركارد - ميزة',
                'description_en' => 'Safe money transfer using your bank account. Safe payment method. Visa, MasterCard, Meeza.',
                'description_ar' => 'دفع آمن ومباشر عبر بطاقتك البنكية. فيزا، ماستركارد، ميزة.',
                'badge' => 'cards',
                'instructions_en' => 'Pay securely using your Credit or Debit card (Visa, MasterCard, Meeza).',
                'instructions_ar' => 'الدفع الآمن والمباشر عبر بطاقة الخصم أو الائتمان (فيزا، ماستركارد، ميزة).',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'instapay',
                'title_en' => 'InstaPay / Mobile Wallets',
                'title_ar' => 'إنستاباي / محافظ إلكترونية (فودافون كاش)',
                'description_en' => 'Safe payment online via InstaPay or any mobile wallet. Fast and zero extra fees.',
                'description_ar' => 'تحويل فوري وآمن عبر تطبيق إنستاباي أو أي محفظة إلكترونية بدون رسوم إضافية.',
                'badge' => 'instapay',
                'instructions_en' => 'Send total order amount to InstaPay handle: serein.store@instapay or Vodafone Cash wallet: 01012345678, then click Checkout to confirm.',
                'instructions_ar' => 'يرجى إرسال قيمة الطلب لحساب إنستاباي: serein.store@instapay أو لمحفظة فودافون كاش على رقم 01012345678، ثم إتمام الطلب وسنقوم بالتأكيد فوراً.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 'cod',
                'title_en' => 'Cash on Delivery (COD)',
                'title_ar' => 'الدفع عند الاستلام',
                'description_en' => 'Pay with cash upon order arrival right at your doorstep. Check your products before paying.',
                'description_ar' => 'ادفع نقداً عند استلام طلبك لباب منزلك مع إمكانية معاينة المنتجات قبل الدفع.',
                'badge' => 'cod',
                'instructions_en' => 'You can inspect the items upon arrival before paying the total cash amount to our courier.',
                'instructions_ar' => 'يمكنك فحص المنتج والتأكد من سلامته مع المندوب قبل دفع المبلغ نقداً.',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['id' => $method['id']],
                $method
            );
        }
    }
}
