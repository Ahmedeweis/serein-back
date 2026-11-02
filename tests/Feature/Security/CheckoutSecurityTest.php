<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected User $admin;
    protected Product $product;
    protected ProductVariant $variant;
    protected ShippingZone $zone;
    protected Governorate $gov;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Seed users ────────────────────────────────────────────────────────
        $this->customerA = User::create([
            'first_name' => 'Customer',
            'last_name'  => 'Alpha',
            'email'      => 'alpha@serein.test',
            'phone'      => '01011111111',
            'password'   => bcrypt('password'),
            'role'       => 'user',
        ]);

        $this->customerB = User::create([
            'first_name' => 'Customer',
            'last_name'  => 'Beta',
            'email'      => 'beta@serein.test',
            'phone'      => '01022222222',
            'password'   => bcrypt('password'),
            'role'       => 'user',
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'Root',
            'email'      => 'admin@serein.test',
            'phone'      => '01099999999',
            'password'   => bcrypt('password'),
            'role'       => 'admin',
        ]);

        // ── Seed catalog ──────────────────────────────────────────────────────
        $category = Category::create([
            'name_en' => 'Organic Honey',
            'name_ar' => 'عسل عضوي',
            'slug'    => 'organic-honey',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en'     => 'Royal Mountain Sidr',
            'name_ar'     => 'سدر جبلي ملكي',
            'slug'        => 'royal-mountain-sidr',
            'is_active'   => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '1kg Luxury Jar',
            'name_ar'    => 'برطمان فاخر 1 كجم',
            'unit'       => 'kg',
            'value'      => 1,
            'price'      => 300.00, // True database price
            'stock'      => 10,     // Available stock
            'is_default' => true,
        ]);

        // ── Seed shipping ─────────────────────────────────────────────────────
        $country = Country::create([
            'name_en'   => 'Egypt',
            'name_ar'   => 'مصر',
            'code'      => 'EG',
            'is_active' => true,
        ]);

        $this->zone = ShippingZone::create([
            'name_en'   => 'Cairo Standard Zone',
            'name_ar'   => 'منطقة القاهرة الكبرى',
            'price'     => 50.00, // True shipping price
            'min_days'  => 1,
            'max_days'  => 3,
            'is_active' => true,
        ]);

        $this->gov = Governorate::create([
            'country_id'       => $country->id,
            'shipping_zone_id' => $this->zone->id,
            'name_en'          => 'Cairo',
            'name_ar'          => 'القاهرة',
            'is_active'        => true,
        ]);
    }

    /**
     * Scenario 1: Zero-Trust Price Tampering Prevention.
     * The order ignores client-sent tampered prices, subtotals, shipping costs,
     * and totals, and calculates the true amounts strictly from the Database.
     */
    public function test_order_ignores_client_tampered_prices_and_calculates_real_price_from_database(): void
    {
        $tamperedPayload = [
            'first_name'       => 'Attacker',
            'last_name'        => 'Hacker',
            'email'            => 'hacker@blackhat.test',
            'phone'            => '01000000000',
            'address'          => 'Darknet Ave 404',
            'city'             => 'Cairo',
            'country'          => 'Egypt',
            'shipping_zone_id' => $this->zone->id,

            // Malicious fields attempting price manipulation:
            'shipping_cost'    => 0.00,   // Real is 50.00
            'discount_amount'  => 500.00, // Fake discount without coupon
            'subtotal'         => 2.00,   // Real is 600.00 (300 * 2)
            'total'            => 2.00,   // Tampered total

            'items' => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Royal Mountain Sidr',
                    'price'           => 1.00, // Tampered unit price (Real is 300.00)
                    'quantity'        => 2,
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $tamperedPayload);

        $response->assertStatus(201);

        // Expected True Total: (300.00 * 2) + 50.00 shipping - 0.00 discount = 650.00
        $expectedSubtotal = 600.00;
        $expectedShipping = 50.00;
        $expectedTotal    = 650.00;

        $this->assertEquals($expectedTotal, $response->json('total'));

        // Verify database records contain genuine prices
        $this->assertDatabaseHas('orders', [
            'order_number'    => $response->json('order_number'),
            'customer_email'  => 'hacker@blackhat.test',
            'subtotal'        => $expectedSubtotal,
            'shipping_cost'   => $expectedShipping,
            'discount_amount' => 0.00,
            'total'           => $expectedTotal,
        ]);

        $this->assertDatabaseHas('order_items', [
            'variant_id' => $this->variant->id,
            'price'      => 300.00, // Real unit price, not 1.00
            'quantity'   => 2,
        ]);

        // Verify stock decremented safely from 10 to 8
        $this->assertEquals(8, $this->variant->fresh()->stock);
    }

    /**
     * Scenario 2: Validation rejection on negative quantity (quantity: -1).
     */
    public function test_order_fails_with_422_when_quantity_is_negative(): void
    {
        $payload = [
            'first_name'       => 'Test',
            'last_name'        => 'User',
            'email'            => 'test@example.com',
            'phone'            => '01012345678',
            'address'          => '123 Test St',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id,
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'quantity'   => -1, // Negative quantity
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        // Stock must remain intact
        $this->assertEquals(10, $this->variant->fresh()->stock);
    }

    /**
     * Scenario 2 (Cont.): Validation rejection on zero quantity (quantity: 0).
     */
    public function test_order_fails_with_422_when_quantity_is_zero(): void
    {
        $payload = [
            'first_name'       => 'Test',
            'last_name'        => 'User',
            'email'            => 'test@example.com',
            'phone'            => '01012345678',
            'address'          => '123 Test St',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id,
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'quantity'   => 0, // Zero quantity
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        // Stock must remain intact
        $this->assertEquals(10, $this->variant->fresh()->stock);
    }

    /**
     * Scenario 3: Stock overflow failure and atomic rollback.
     * When requested quantity exceeds available inventory:
     * - Order fails with 422.
     * - Stock is NOT modified.
     * - No Order or OrderItem is recorded in DB.
     */
    public function test_order_and_stock_modification_fails_when_quantity_exceeds_available_stock(): void
    {
        // Available stock is 10, attempt to order 15
        $payload = [
            'first_name'       => 'Greedy',
            'last_name'        => 'Buyer',
            'email'            => 'greedy@buyer.test',
            'phone'            => '01012345678',
            'address'          => '123 Market St',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id,
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'quantity'   => 15, // Exceeds available stock (10)
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Stock must remain unmodified at 10
        $this->assertEquals(10, $this->variant->fresh()->stock);

        // No order should have been saved
        $this->assertDatabaseMissing('orders', [
            'customer_email' => 'greedy@buyer.test',
        ]);
    }

    /**
     * Scenario 4: Authorization & Policies (OrderPolicy).
     * Prevents a regular customer from viewing another customer's order, returning 403 Forbidden.
     */
    public function test_authenticated_user_cannot_view_another_users_order_and_receives_403_forbidden(): void
    {
        // Order belongs strictly to Customer A
        $orderA = Order::create([
            'order_number'     => 'ORD-2026-0001',
            'user_id'          => $this->customerA->id,
            'customer_name'    => 'Customer Alpha',
            'customer_email'   => 'alpha@serein.test',
            'customer_phone'   => '01011111111',
            'customer_address' => 'Alpha Residence',
            'customer_city'    => 'Cairo',
            'customer_country' => 'Egypt',
            'subtotal'         => 300.00,
            'shipping_cost'    => 50.00,
            'discount_amount'  => 0.00,
            'total'            => 350.00,
            'status'           => 'Pending',
        ]);

        // Customer B authenticates
        Sanctum::actingAs($this->customerB);

        // Customer B attempts to inspect Customer A's order details
        $response = $this->getJson("/api/orders/{$orderA->id}");

        // Must be rejected with 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * Customer A can view their own order successfully (200 OK).
     */
    public function test_authenticated_user_can_view_their_own_order(): void
    {
        $orderA = Order::create([
            'order_number'     => 'ORD-2026-0002',
            'user_id'          => $this->customerA->id,
            'customer_name'    => 'Customer Alpha',
            'customer_email'   => 'alpha@serein.test',
            'customer_phone'   => '01011111111',
            'customer_address' => 'Alpha Residence',
            'customer_city'    => 'Cairo',
            'customer_country' => 'Egypt',
            'subtotal'         => 300.00,
            'shipping_cost'    => 50.00,
            'discount_amount'  => 0.00,
            'total'            => 350.00,
            'status'           => 'Pending',
        ]);

        Sanctum::actingAs($this->customerA);

        $response = $this->getJson("/api/orders/{$orderA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $orderA->id)
            ->assertJsonPath('data.order_number', 'ORD-2026-0002');
    }

    /**
     * Admin can view any customer's order (200 OK).
     */
    public function test_admin_can_view_any_order(): void
    {
        $orderA = Order::create([
            'order_number'     => 'ORD-2026-0003',
            'user_id'          => $this->customerA->id,
            'customer_name'    => 'Customer Alpha',
            'customer_email'   => 'alpha@serein.test',
            'customer_phone'   => '01011111111',
            'customer_address' => 'Alpha Residence',
            'customer_city'    => 'Cairo',
            'customer_country' => 'Egypt',
            'subtotal'         => 300.00,
            'shipping_cost'    => 50.00,
            'discount_amount'  => 0.00,
            'total'            => 350.00,
            'status'           => 'Pending',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/orders/{$orderA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $orderA->id);
    }

    /**
     * Server verifies coupon codes against the database and computes
     * true percentage discounts, ignoring client-submitted discount amounts.
     */
    public function test_coupon_discount_is_calculated_strictly_from_database(): void
    {
        Coupon::create([
            'code'       => 'SEREIN20',
            'percentage' => 20,
            'is_active'  => true,
        ]);

        $payload = [
            'first_name'       => 'Nour',
            'last_name'        => 'Salem',
            'email'            => 'nour@example.com',
            'phone'            => '01033333333',
            'address'          => 'Corniche El Nil',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id,
            'coupon_code'      => 'serein20', // lowercase input trimmed & uppercase in DB
            'discount_amount'  => 999.00,     // Manipulated client discount ignored
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'quantity'   => 2, // Subtotal: 300 * 2 = 600.00
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        // Subtotal = 600.00
        // 20% discount = 120.00
        // Shipping = 50.00
        // Total = 600 - 120 + 50 = 530.00
        $this->assertDatabaseHas('orders', [
            'order_number'    => $response->json('order_number'),
            'subtotal'        => 600.00,
            'discount_amount' => 120.00,
            'shipping_cost'   => 50.00,
            'total'           => 530.00,
            'coupon_code'     => 'SEREIN20',
        ]);
    }

    /**
     * Form Request strictly enforces exists rules on foreign keys.
     */
    public function test_order_fails_with_422_when_identifiers_do_not_exist(): void
    {
        $payload = [
            'first_name'       => 'Invalid',
            'last_name'        => 'Data',
            'email'            => 'invalid@test.com',
            'phone'            => '01012345678',
            'address'          => 'Unknown',
            'city'             => 'Cairo',
            'shipping_zone_id' => 999999, // Non-existent zone
            'items'            => [
                [
                    'product_id' => 999999, // Non-existent product
                    'variant_id' => 999999, // Non-existent variant
                    'quantity'   => 1,
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_zone_id',
                'items.0.product_id',
                'items.0.variant_id',
            ]);
    }

    /**
     * Fix: Order number generation must be collision-resistant, unpredictable, and adhere to format.
     */
    public function test_order_number_is_unique_unpredictable_and_collision_resistant(): void
    {
        $year = now()->year;
        $orderNumbers = [];

        // Generate 50 consecutive order numbers
        for ($i = 0; $i < 50; $i++) {
            $number = Order::generateOrderNumber();

            // Must match format ORD-YYYY-XXXX-YYY (e.g. ORD-2026-4821-9XK)
            $this->assertMatchesRegularExpression(
                "/^ORD-{$year}-\\d{4}-[A-Z0-9]{3}$/",
                $number
            );

            $orderNumbers[] = $number;
        }

        // All 50 generated numbers must be strictly unique (no collisions)
        $this->assertCount(50, array_unique($orderNumbers));
    }

    /**
     * Fix: Orders with multiple different variants of the same product must compute
     * their distinct individual variant prices (e.g. 250g for 180, 500g for 450, 1kg for 1000),
     * NOT default all items to the first/default variant price.
     */
    public function test_orders_with_different_variants_of_the_same_product_compute_their_individual_variant_prices(): void
    {
        // Variant 1: 250g (180.00 EGP) - default
        $v250 = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '250g Jar',
            'name_ar'    => 'برطمان 250 جم',
            'unit'       => 'g',
            'value'      => 250,
            'price'      => 180.00,
            'stock'      => 50,
            'is_default' => true,
        ]);

        // Variant 2: 500g (450.00 EGP)
        $v500 = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '500g Jar',
            'name_ar'    => 'برطمان 500 جم',
            'unit'       => 'g',
            'value'      => 500,
            'price'      => 450.00,
            'stock'      => 50,
            'is_default' => false,
        ]);

        // Variant 3: 1kg (1000.00 EGP)
        $v1000 = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '1kg Jar',
            'name_ar'    => 'برطمان 1 كجم',
            'unit'       => 'kg',
            'value'      => 1,
            'price'      => 1000.00,
            'stock'      => 50,
            'is_default' => false,
        ]);

        $payload = [
            'first_name'       => 'Ahmed',
            'last_name'        => 'Eweis',
            'email'            => 'ahmed@serein.test',
            'phone'            => '01093057107',
            'address'          => 'B 14 - M2 - M Al-Abd',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id, // 50 EGP
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $v250->id,
                    'quantity'   => 1,
                ],
                [
                    'product_id' => $this->product->id,
                    'size_id'    => $v500->id, // Using size_id alias from frontend cart
                    'quantity'   => 1,
                ],
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $v1000->id,
                    'quantity'   => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        // Subtotal must be 180 + 450 + 1000 = 1630.00
        // Total with shipping: 1630 + 50 = 1680.00
        $this->assertDatabaseHas('orders', [
            'order_number'  => $response->json('order_number'),
            'subtotal'      => 1630.00,
            'shipping_cost' => 50.00,
            'total'         => 1680.00,
        ]);

        // Each individual item must have its exact distinct price recorded in order_items
        $this->assertDatabaseHas('order_items', [
            'variant_id' => $v250->id,
            'price'      => 180.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'variant_id' => $v500->id,
            'price'      => 450.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'variant_id' => $v1000->id,
            'price'      => 1000.00,
        ]);
    }
}
