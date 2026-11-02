<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ─── 1. Ensure Default Categories Exist ──────────────────────────────
        $honeyCategory = Category::firstOrCreate(
            ['slug' => 'honey'],
            [
                'name_en'   => 'Honey',
                'name_ar'   => 'عسل',
                'image'     => 'categories/honey.webp',
                'is_active' => true,
            ]
        );

        $oilsCategory = Category::firstOrCreate(
            ['slug' => 'oils'],
            [
                'name_en'   => 'Oils',
                'name_ar'   => 'زيوت',
                'image'     => 'categories/oils.webp',
                'is_active' => true,
            ]
        );

        // ─── 2. Catalog Dataset Definition ────────────────────────────────────
        $productsData = [
            [
                'category_id'    => $honeyCategory->id,
                'name_en'        => 'Raw Mountain Sidr Honey',
                'name_ar'        => 'عسل سدر جبلي خام',
                'description_en' => 'Premium royal Sidr honey known for its rich flavor and medicinal properties.',
                'description_ar' => 'عسل سدر جبلي ملكي فاخر يتميز بنكهته الغنية وخصائصه العلاجية الفريدة.',
                'image'          => 'products/sidr-honey.webp',
                'unit_type'      => 'weight',
                'rating'         => 4.9,
                'reviews_count'  => 234000,
                'is_best_seller' => true,
                'is_featured'    => true,
                'is_trending'    => true,
                'is_new_arrival' => false,
                'variants'       => [
                    [
                        'name_en'          => '250g',
                        'name_ar'          => '250 جرام',
                        'unit'             => 'g',
                        'value'            => 250,
                        'price'            => 180,
                        'compare_at_price' => 299,
                        'is_default'       => true,
                    ],
                    [
                        'name_en'          => '500g',
                        'name_ar'          => '500 جرام',
                        'unit'             => 'g',
                        'value'            => 500,
                        'price'            => 299,
                        'compare_at_price' => 399,
                        'is_default'       => false,
                    ],
                ],
            ],
            [
                'category_id'    => $oilsCategory->id,
                'name_en'        => 'Extra Virgin Cold-Pressed Olive Oil',
                'name_ar'        => 'زيت زيتون بكر ممتاز معصور على البارد',
                'description_en' => 'Single-estate extra virgin olive oil cold-extracted from fresh organic olives.',
                'description_ar' => 'زيت زيتون بكر ممتاز معصور على البارد من حقول منتقاة من حبات الزيتون العضوي الطازج.',
                'image'          => 'products/olive-oil.webp',
                'unit_type'      => 'volume',
                'rating'         => 4.8,
                'reviews_count'  => 189000,
                'is_best_seller' => true,
                'is_featured'    => true,
                'is_trending'    => false,
                'is_new_arrival' => false,
                'variants'       => [
                    [
                        'name_en'          => '500ml',
                        'name_ar'          => '500 مل',
                        'unit'             => 'ml',
                        'value'            => 500,
                        'price'            => 199,
                        'compare_at_price' => 249,
                        'is_default'       => true,
                    ],
                    [
                        'name_en'          => '1L',
                        'name_ar'          => '1 لتر',
                        'unit'             => 'L',
                        'value'            => 1,
                        'price'            => 350,
                        'compare_at_price' => 420,
                        'is_default'       => false,
                    ],
                ],
            ],
            [
                'category_id'    => $honeyCategory->id,
                'name_en'        => 'Pure Clover & Wildflower Honey',
                'name_ar'        => 'عسل زهور برية وبرسيم نقي',
                'description_en' => 'Light, sweet, and golden unfiltered wildflower honey for everyday vitality.',
                'description_ar' => 'عسل زهور برية وبرسيم نقي، خفيف ومحلى طبيعياً يمنحك الطاقة والحيوية اليومية.',
                'image'          => 'products/wildflower-honey.webp',
                'unit_type'      => 'weight',
                'rating'         => 4.7,
                'reviews_count'  => 312000,
                'is_best_seller' => false,
                'is_featured'    => false,
                'is_trending'    => true,
                'is_new_arrival' => true,
                'variants'       => [
                    [
                        'name_en'          => '500g',
                        'name_ar'          => '500 جرام',
                        'unit'             => 'g',
                        'value'            => 500,
                        'price'            => 149,
                        'compare_at_price' => 199,
                        'is_default'       => true,
                    ],
                ],
            ],
            [
                'category_id'    => $oilsCategory->id,
                'name_en'        => 'Organic Black Seed Infusion Oil',
                'name_ar'        => 'زيت حبة البركة العضوي المنقوع',
                'description_en' => '100% pure cold-pressed Nigella Sativa oil rich in antioxidants and thymoquinone.',
                'description_ar' => 'زيت حبة البركة العضوي النقي 100% المعصور على البارد، غني بمضادات الأكسدة والثيموكينون.',
                'image'          => 'products/black-seed-oil.webp',
                'unit_type'      => 'volume',
                'rating'         => 5.0,
                'reviews_count'  => 456000,
                'is_best_seller' => true,
                'is_featured'    => true,
                'is_trending'    => true,
                'is_new_arrival' => false,
                'variants'       => [
                    [
                        'name_en'          => '250ml',
                        'name_ar'          => '250 مل',
                        'unit'             => 'ml',
                        'value'            => 250,
                        'price'            => 220,
                        'compare_at_price' => 280,
                        'is_default'       => true,
                    ],
                ],
            ],
            [
                'category_id'    => $honeyCategory->id,
                'name_en'        => 'Premium Royal Jelly & Ginseng Honey',
                'name_ar'        => 'عسل ملكي فاخر مع غذاء الملكات والجنسنج',
                'description_en' => 'Energizing blend of raw honey, active royal jelly, and red Korean ginseng.',
                'description_ar' => 'مزيج حيوي ومقوٍ من العسل الطبيعي الخام وغذاء ملكات النحل مع الجنسنج الكوري الأحمر.',
                'image'          => 'products/royal-jelly-honey.webp',
                'unit_type'      => 'weight',
                'rating'         => 4.9,
                'reviews_count'  => 175000,
                'is_best_seller' => false,
                'is_featured'    => true,
                'is_trending'    => false,
                'is_new_arrival' => true,
                'variants'       => [
                    [
                        'name_en'          => '450g',
                        'name_ar'          => '450 جرام',
                        'unit'             => 'g',
                        'value'            => 450,
                        'price'            => 349,
                        'compare_at_price' => 420,
                        'is_default'       => true,
                    ],
                ],
            ],
        ];

        // ─── 3. Seed Products & Variants ──────────────────────────────────────
        foreach ($productsData as $data) {
            $slug = Str::slug($data['name_en']);
            $variants = $data['variants'];
            unset($data['variants']);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                array_merge($data, [
                    'slug'      => $slug,
                    'is_active' => true,
                ])
            );

            foreach ($variants as $v) {
                // Generate realistic random stock between 15 and 80
                $stock = rand(15, 80);

                // Generate unique SKU: PRD-{PRODUCT_ID}-{SIZE} (e.g. PRD-1-250G)
                $skuSize = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $v['name_en']));
                $sku = "PRD-{$product->id}-{$skuSize}";

                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'name_en'    => $v['name_en'],
                    ],
                    [
                        'name_ar'          => $v['name_ar'],
                        'unit'             => $v['unit'],
                        'value'            => $v['value'],
                        'price'            => $v['price'],
                        'compare_at_price' => $v['compare_at_price'],
                        'stock'            => $stock,
                        'sku'              => $sku,
                        'is_default'       => $v['is_default'],
                    ]
                );
            }
        }
    }
}
