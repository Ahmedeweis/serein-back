<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class OrdersSeeder extends Seeder
{
    /**
     * Seed six demo orders with accurate shipping costs tied to shipping zones.
     *
     * Zone shipping costs:
     *  - القاهرة الكبرى (Cairo, Giza, Qaliubiya) → 97 EGP
     *  - الإسكندرية (Alexandria)                  → 102 EGP
     *  - الدلتا والقناة (Dakahlia, …)              → 110 EGP
     *  - الصعيد (Upper Egypt + frontiers)          → 140 EGP
     */
    public function run(): void
    {
        // Helper: resolve zone price by governorate English name
        $zonePrice = function (string $govNameEn): float {
            $gov = Governorate::with('shippingZone')
                ->where('name_en', $govNameEn)
                ->first();
            return $gov && $gov->shippingZone ? (float) $gov->shippingZone->price : 97.00;
        };

        $orders = [
            // ── ORD-2026-1001: Cairo – COD – Pending ──────────────────────────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1001',
                    'customer_name'   => 'Ahmed Hassan',
                    'customer_email'  => 'ahmed.hassan@example.com',
                    'customer_phone'  => '+20 100 123 4567',
                    'customer_address'=> '15 El-Nasr St, New Cairo',
                    'customer_city'   => 'Cairo',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Cairo'),   // 97 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Cash on Delivery',
                    'payment_status'  => 'pending',
                    'status'          => 'Pending',
                    'notes'           => 'Please call before arrival.',
                    'created_at'      => '2026-09-10 11:30:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Raw Mountain Sidr Honey',
                        'product_name_ar' => 'عسل سدر جبلي خام',
                        'product_image'   => 'products/sidr-honey.webp',
                        'variant_label'   => '500g',
                        'price'           => 299,
                        'quantity'        => 2,
                    ],
                    [
                        'product_name_en' => 'Extra Virgin Cold-Pressed Olive Oil',
                        'product_name_ar' => 'زيت زيتون بكر ممتاز معصور على البارد',
                        'product_image'   => 'products/olive-oil.webp',
                        'variant_label'   => '500ml',
                        'price'           => 199,
                        'quantity'        => 1,
                    ],
                ],
            ],

            // ── ORD-2026-1002: Alexandria – Card – Delivered ──────────────────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1002',
                    'customer_name'   => 'Mariam Farouk',
                    'customer_email'  => 'mariam.f@example.com',
                    'customer_phone'  => '+20 112 987 6543',
                    'customer_address'=> '42 Fouad Street, Ramleh',
                    'customer_city'   => 'Alexandria',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Alexandria'), // 102 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Credit Card',
                    'payment_status'  => 'paid',
                    'status'          => 'Delivered',
                    'notes'           => 'Leave package with security if unavailable.',
                    'created_at'      => '2026-09-09 16:15:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Premium Royal Jelly & Ginseng Honey',
                        'product_name_ar' => 'عسل غذاء ملكات النحل مع الجنسنج',
                        'product_image'   => 'products/royal-jelly.webp',
                        'variant_label'   => '450g',
                        'price'           => 349,
                        'quantity'        => 1,
                    ],
                ],
            ],

            // ── ORD-2026-1003: Giza – Vodafone Cash – In Preparation ─────────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1003',
                    'customer_name'   => 'Omar Sherif',
                    'customer_email'  => 'omar.sherif@example.com',
                    'customer_phone'  => '+20 120 445 6789',
                    'customer_address'=> '77 Al-Galaa St',
                    'customer_city'   => 'Giza',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Giza'), // 97 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Vodafone Cash',
                    'payment_status'  => 'paid',
                    'status'          => 'In Preparation',
                    'notes'           => null,
                    'created_at'      => '2026-09-08 09:45:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Organic Black Seed Infusion Oil',
                        'product_name_ar' => 'زيت حبة البركة العضوي المركز',
                        'product_image'   => 'products/black-seed.webp',
                        'variant_label'   => '250ml',
                        'price'           => 220,
                        'quantity'        => 2,
                    ],
                    [
                        'product_name_en' => 'Pure Clover & Wildflower Honey',
                        'product_name_ar' => 'عسل زهور البرسيم والزهور البرية',
                        'product_image'   => 'products/clover-honey.webp',
                        'variant_label'   => '500g',
                        'price'           => 149,
                        'quantity'        => 1,
                    ],
                ],
            ],

            // ── ORD-2026-1004: Dakahlia – COD – Out for Delivery ─────────────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1004',
                    'customer_name'   => 'Nour El-Din',
                    'customer_email'  => 'nour.eldin@example.com',
                    'customer_phone'  => '+20 101 234 5678',
                    'customer_address'=> '12 Cornish El-Nile',
                    'customer_city'   => 'Mansoura',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Dakahlia'), // 110 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Cash on Delivery',
                    'payment_status'  => 'pending',
                    'status'          => 'Out for Delivery',
                    'notes'           => null,
                    'created_at'      => '2026-09-07 14:20:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Raw Mountain Sidr Honey',
                        'product_name_ar' => 'عسل سدر جبلي خام',
                        'product_image'   => 'products/sidr-honey.webp',
                        'variant_label'   => '500g',
                        'price'           => 299,
                        'quantity'        => 1,
                    ],
                ],
            ],

            // ── ORD-2026-1005: Cairo – Card – Confirmed (946+97=1043) ─────────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1005',
                    'customer_name'   => 'Salma Mahmoud',
                    'customer_email'  => 'salma.m@example.com',
                    'customer_phone'  => '+20 155 876 5432',
                    'customer_address'=> '28 El-Bahr St, Heliopolis',
                    'customer_city'   => 'Cairo',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Cairo'), // 97 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Credit Card',
                    'payment_status'  => 'paid',
                    'status'          => 'Confirmed',
                    'notes'           => null,
                    'created_at'      => '2026-09-05 10:10:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Extra Virgin Cold-Pressed Olive Oil',
                        'product_name_ar' => 'زيت زيتون بكر ممتاز معصور على البارد',
                        'product_image'   => 'products/olive-oil.webp',
                        'variant_label'   => '500ml',
                        'price'           => 199,
                        'quantity'        => 3,
                    ],
                    [
                        'product_name_en' => 'Premium Royal Jelly & Ginseng Honey',
                        'product_name_ar' => 'عسل غذاء ملكات النحل مع الجنسنج',
                        'product_image'   => 'products/royal-jelly.webp',
                        'variant_label'   => '450g',
                        'price'           => 349,
                        'quantity'        => 1,
                    ],
                ],
            ],

            // ── ORD-2026-1006: Alexandria – COD – Cancelled (149+102=251) ─────
            [
                'order' => [
                    'order_number'    => 'ORD-2026-1006',
                    'customer_name'   => 'Tarek Zaki',
                    'customer_email'  => 'tarek.z@example.com',
                    'customer_phone'  => '+20 109 876 1234',
                    'customer_address'=> '9 Horreya Ave',
                    'customer_city'   => 'Alexandria',
                    'customer_country'=> 'Egypt',
                    'shipping_cost'   => $zonePrice('Alexandria'), // 102 EGP
                    'discount_amount' => 0,
                    'payment_method'  => 'Cash on Delivery',
                    'payment_status'  => 'failed',
                    'status'          => 'Cancelled',
                    'notes'           => 'Customer cancelled before dispatch.',
                    'created_at'      => '2026-09-04 12:00:00',
                ],
                'items' => [
                    [
                        'product_name_en' => 'Pure Clover & Wildflower Honey',
                        'product_name_ar' => 'عسل زهور البرسيم والزهور البرية',
                        'product_image'   => 'products/clover-honey.webp',
                        'variant_label'   => '500g',
                        'price'           => 149,
                        'quantity'        => 1,
                    ],
                ],
            ],
        ];

        foreach ($orders as $entry) {
            $orderData = $entry['order'];
            $items     = $entry['items'];

            // Compute subtotal & total from items
            $subtotal = collect($items)->sum(fn ($i) => $i['price'] * $i['quantity']);
            $orderData['subtotal'] = $subtotal;
            $orderData['total']    = round($subtotal + $orderData['shipping_cost'] - $orderData['discount_amount'], 2);

            // Resolve shipping_zone_id from customer city
            $gov = Governorate::where('name_en', $orderData['customer_city'])->first();
            $orderData['shipping_zone_id'] = $gov?->shipping_zone_id ?? null;

            $order = Order::updateOrCreate(
                ['order_number' => $orderData['order_number']],
                $orderData
            );

            // Clear existing items then re-seed
            $order->items()->delete();

            foreach ($items as $item) {
                $order->items()->create($item);
            }
        }
    }
}
