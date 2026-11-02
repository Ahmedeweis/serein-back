<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Product $product;
    protected ProductVariant $variant;
    protected ShippingZone $zone;
    protected Governorate $gov;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@serein.test',
            'phone'      => '01000000001',
            'password'   => bcrypt('secret'),
            'role'       => 'admin',
        ]);

        $category = Category::create([
            'name_en' => 'Honey',
            'name_ar' => 'عسل',
            'slug'    => 'honey',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en'     => 'Sidr Honey',
            'name_ar'     => 'عسل سدر',
            'slug'        => 'sidr-honey',
            'is_active'   => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '500g Jar',
            'name_ar'    => 'برطمان 500 جم',
            'unit'       => 'g',
            'value'      => 500,
            'price'      => 250.00,
            'stock'      => 20,
            'is_default' => true,
        ]);

        $country = Country::create([
            'name_en'   => 'Egypt',
            'name_ar'   => 'مصر',
            'code'      => 'EG',
            'is_active' => true,
        ]);

        $this->zone = ShippingZone::create([
            'name_en'   => 'Cairo & Giza',
            'name_ar'   => 'القاهرة والجيزة',
            'price'     => 60.00,
            'min_days'  => 1,
            'max_days'  => 2,
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

    public function test_admin_product_lookup_returns_active_products_and_variants(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/products/lookup');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name_en',
                        'name_ar',
                        'slug',
                        'price',
                        'stock',
                        'variants' => [
                            '*' => ['id', 'name_en', 'name_ar', 'price', 'stock', 'is_default']
                        ]
                    ]
                ]
            ]);

        $this->assertEquals(250.00, $response->json('data.0.price'));
        $this->assertEquals(20, $response->json('data.0.stock'));
    }

    public function test_storefront_order_creation_reduces_stock_and_creates_order(): void
    {
        $payload = [
            'first_name'       => 'Ahmed',
            'last_name'        => 'Eweis',
            'email'            => 'ahmed@example.com',
            'phone'            => '+201012345678',
            'address'          => '10 Tahrir Sq',
            'city'             => 'Cairo',
            'country'          => 'Egypt',
            'shipping_cost'    => 60.00,
            'shipping_zone_id' => $this->zone->id,
            'payment_method'   => 'Cash on Delivery',
            'discount_amount'  => 0,
            'items'            => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Sidr Honey',
                    'product_name_ar' => 'عسل سدر',
                    'price'           => 250.00,
                    'quantity'        => 3,
                    'variant_label'   => '500g Jar',
                ]
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'order_number',
                'order_id',
                'total',
            ]);

        $this->assertEquals(810.00, $response->json('total')); // 250 * 3 + 60 = 810

        // Verify variant stock decremented from 20 to 17
        $this->assertEquals(17, $this->variant->fresh()->stock);

        // Verify order in database
        $this->assertDatabaseHas('orders', [
            'order_number'   => $response->json('order_number'),
            'customer_email' => 'ahmed@example.com',
            'total'          => 810.00,
            'status'         => 'Pending',
        ]);
    }

    public function test_admin_manual_order_creation_with_stock_deduction(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'customer_name'    => 'Sara Mohamed',
            'customer_email'   => 'sara@example.com',
            'customer_phone'   => '+201099999999',
            'customer_address' => '22 Maadi St',
            'customer_city'    => 'Cairo',
            'customer_country' => 'Egypt',
            'governorate_id'   => $this->gov->id,
            'payment_method'   => 'Cash on Delivery',
            'payment_status'   => 'pending',
            'status'           => 'Confirmed',
            'items'            => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Sidr Honey',
                    'product_name_ar' => 'عسل سدر',
                    'variant_label'   => '500g Jar',
                    'price'           => 250.00,
                    'quantity'        => 2,
                ]
            ],
        ];

        $response = $this->postJson('/api/admin/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Sara Mohamed')
            ->assertJsonPath('data.status', 'Confirmed')
            ->assertJsonPath('data.shipping_cost', 60);

        // Stock decremented from 20 to 18
        $this->assertEquals(18, $this->variant->fresh()->stock);
    }

    public function test_admin_order_status_update_auto_pays_delivered_cod(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::create([
            'order_number'     => Order::generateOrderNumber(),
            'customer_name'    => 'Test Client',
            'customer_email'   => 'client@test.com',
            'subtotal'         => 100,
            'shipping_cost'    => 50,
            'total'            => 150,
            'payment_method'   => 'Cash on Delivery',
            'payment_status'   => 'pending',
            'status'           => 'Pending',
        ]);

        $response = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'Delivered',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Delivered')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_admin_can_update_existing_order_and_reconcile_stock(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create initial order with 2 units of variant (stock: 20 -> 18)
        $createPayload = [
            'customer_name'    => 'Initial Name',
            'customer_email'   => 'initial@example.com',
            'customer_phone'   => '01011111111',
            'customer_address' => 'Old Address',
            'customer_city'    => 'Cairo',
            'governorate_id'   => $this->gov->id,
            'payment_method'   => 'Cash on Delivery',
            'payment_status'   => 'pending',
            'status'           => 'Pending',
            'notes'            => 'Old note',
            'items'            => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Sidr Honey',
                    'variant_label'   => '500g Jar',
                    'price'           => 250.00,
                    'quantity'        => 2,
                ]
            ],
        ];

        $createRes = $this->postJson('/api/admin/orders', $createPayload);
        $createRes->assertStatus(201);
        $orderId = $createRes->json('data.id');
        $orderNumber = $createRes->json('data.order_number');
        $this->assertEquals(18, $this->variant->fresh()->stock);

        // 2. Update order with new customer info and 3 units (stock should restore 2 then deduct 3 => 17)
        $updatePayload = [
            'customer_name'    => 'Updated Name',
            'customer_email'   => 'updated@example.com',
            'customer_phone'   => '01022222222',
            'customer_address' => 'New Address 45',
            'customer_city'    => 'Cairo',
            'governorate_id'   => $this->gov->id,
            'payment_method'   => 'Credit Card',
            'payment_status'   => 'paid',
            'status'           => 'In Preparation',
            'notes'            => 'Updated note instructions',
            'shipping_cost'    => 60.00,
            'items'            => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Sidr Honey',
                    'variant_label'   => '500g Jar',
                    'price'           => 250.00,
                    'quantity'        => 3,
                ]
            ],
        ];

        $updateRes = $this->putJson("/api/admin/orders/{$orderId}", $updatePayload);

        $updateRes->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.order_number', $orderNumber) // order_number immutable
            ->assertJsonPath('data.customer_name', 'Updated Name')
            ->assertJsonPath('data.customer_email', 'updated@example.com')
            ->assertJsonPath('data.customer_phone', '01022222222')
            ->assertJsonPath('data.notes', 'Updated note instructions')
            ->assertJsonPath('data.status', 'In Preparation')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.subtotal', 750)
            ->assertJsonPath('data.total', 810);

        // Variant stock correctly reconciled to 17 (20 - 3)
        $this->assertEquals(17, $this->variant->fresh()->stock);

        // Verify items in database synced (1 row with qty 3)
        $this->assertDatabaseHas('orders', [
            'id'           => $orderId,
            'order_number' => $orderNumber,
            'subtotal'     => 750,
            'total'        => 810,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $orderId,
            'variant_id' => $this->variant->id,
            'quantity'   => 3,
        ]);
    }

    public function test_admin_order_update_fails_on_insufficient_stock(): void
    {
        Sanctum::actingAs($this->admin);

        // Initial order with 1 unit (stock: 20 -> 19)
        $order = Order::create([
            'order_number'     => Order::generateOrderNumber(),
            'customer_name'    => 'Customer',
            'customer_email'   => 'customer@example.com',
            'subtotal'         => 250,
            'shipping_cost'    => 60,
            'total'            => 310,
            'payment_method'   => 'Cash on Delivery',
            'payment_status'   => 'pending',
            'status'           => 'Pending',
        ]);
        $order->items()->create([
            'product_id'      => $this->product->id,
            'variant_id'      => $this->variant->id,
            'product_name_en' => 'Sidr Honey',
            'price'           => 250,
            'quantity'        => 1,
        ]);
        $this->variant->decrement('stock', 1);
        $this->assertEquals(19, $this->variant->fresh()->stock);

        // Attempt to update with 100 units (available is 20 after restoring 1)
        $updatePayload = [
            'customer_name'    => 'Customer',
            'customer_email'   => 'customer@example.com',
            'items'            => [
                [
                    'product_id'      => $this->product->id,
                    'variant_id'      => $this->variant->id,
                    'product_name_en' => 'Sidr Honey',
                    'price'           => 250.00,
                    'quantity'        => 100,
                ]
            ],
        ];

        $response = $this->putJson("/api/admin/orders/{$order->id}", $updatePayload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Stock restored from failed transaction rollback
        $this->assertEquals(19, $this->variant->fresh()->stock);
    }
}
