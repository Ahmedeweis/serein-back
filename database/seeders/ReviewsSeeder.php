<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all()->keyBy('slug');
        if ($products->isEmpty()) {
            return;
        }

        $customer = User::where('role', 'customer')->first();
        $customerId = $customer?->id;

        $reviews = [
            // ── Premium Royal Jelly & Ginseng Honey (Product 7) ────────────────
            [
                'product_slug'         => 'premium-royal-jelly-ginseng-honey',
                'user_id'              => $customerId,
                'reviewer_name'        => 'Ahmed Eweis',
                'reviewer_email'       => 'ahmed.eweiis@gmail.com',
                'rating'               => 5,
                'comment'              => 'Absolutely love this product! The quality is outstanding and the taste is incredible.',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'premium-royal-jelly-ginseng-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Mohamed Ali',
                'reviewer_email'       => 'm.ali@example.com',
                'rating'               => 4,
                'comment'              => "Best natural honey I've ever tried. Fast delivery and great packaging.",
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'premium-royal-jelly-ginseng-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Nour Hassan',
                'reviewer_email'       => 'nour.hassan@example.com',
                'rating'               => 5,
                'comment'              => 'Great product, pure and natural. Will definitely order again!',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'premium-royal-jelly-ginseng-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Omar Farouk',
                'reviewer_email'       => 'omar.f@example.com',
                'rating'               => 5,
                'comment'              => 'Rich texture and gives great natural energy throughout the workday morning.',
                'status'               => 'approved',
                'is_verified_purchase' => false,
            ],
            [
                'product_slug'         => 'premium-royal-jelly-ginseng-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Sara Gamal',
                'reviewer_email'       => 'sara.g@example.com',
                'rating'               => 5,
                'comment'              => 'First time trying Royal Jelly honey, truly premium taste and exceptional clarity.',
                'status'               => 'pending',
                'is_verified_purchase' => false,
            ],

            // ── Raw Mountain Sidr Honey ────────────────────────────────────────
            [
                'product_slug'         => 'raw-mountain-sidr-honey',
                'user_id'              => $customerId,
                'reviewer_name'        => 'Kareem Tarek',
                'reviewer_email'       => 'kareem@example.com',
                'rating'               => 5,
                'comment'              => 'Authentic Sidr honey with distinct herbal aroma. 100% genuine.',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'raw-mountain-sidr-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Youssef Mansour',
                'reviewer_email'       => 'ymansour@example.com',
                'rating'               => 4,
                'comment'              => 'High purity and very fast shipping to Alexandria.',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'raw-mountain-sidr-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Laila Adel',
                'reviewer_email'       => 'laila@example.com',
                'rating'               => 3,
                'comment'              => 'Good quality but shipping took 3 days to arrive.',
                'status'               => 'pending',
                'is_verified_purchase' => false,
            ],

            // ── Pure Clover & Wildflower Honey ─────────────────────────────────
            [
                'product_slug'         => 'pure-clover-wildflower-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Hassan Mahmoud',
                'reviewer_email'       => 'hmahmoud@example.com',
                'rating'               => 5,
                'comment'              => 'Light floral sweetness, great for breakfast and tea sweetening.',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
            [
                'product_slug'         => 'pure-clover-wildflower-honey',
                'user_id'              => null,
                'reviewer_name'        => 'Dina Fikry',
                'reviewer_email'       => 'dina@example.com',
                'rating'               => 5,
                'comment'              => 'Excellent packaging, jar was sealed tightly and arrived in perfect shape.',
                'status'               => 'pending',
                'is_verified_purchase' => true,
            ],

            // ── Extra Virgin Olive Oil ─────────────────────────────────────────
            [
                'product_slug'         => 'extra-virgin-cold-pressed-olive-oil',
                'user_id'              => null,
                'reviewer_name'        => 'Amr Khaled',
                'reviewer_email'       => 'amr@example.com',
                'rating'               => 5,
                'comment'              => 'Cold pressed with a slight peppery finish. Superb quality on salads.',
                'status'               => 'approved',
                'is_verified_purchase' => true,
            ],
        ];

        foreach ($reviews as $rev) {
            $product = $products->get($rev['product_slug']);
            if (! $product) {
                continue;
            }

            Review::updateOrCreate(
                [
                    'product_id'    => $product->id,
                    'reviewer_name' => $rev['reviewer_name'],
                ],
                [
                    'user_id'              => $rev['user_id'],
                    'reviewer_email'       => $rev['reviewer_email'],
                    'rating'               => $rev['rating'],
                    'comment'              => $rev['comment'],
                    'status'               => $rev['status'],
                    'is_verified_purchase' => $rev['is_verified_purchase'],
                ]
            );
        }

        // Recalculate metrics for all products
        foreach ($products as $p) {
            $p->recalculateRatingMetrics();
        }
    }
}
