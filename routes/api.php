<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPaymentMethodController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminShippingMethodController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminShippingZoneController;
use App\Http\Controllers\Api\Admin\AdminPostController;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminCouponController;
use App\Http\Controllers\Api\Admin\AdminTrackingPixelController;
use App\Http\Controllers\Api\Admin\AdminSiteSettingsController;
use App\Http\Controllers\Api\Admin\AdminHeroSlideController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\ShippingZoneController;
use App\Http\Controllers\Api\TrackingPixelController;
use App\Http\Controllers\Api\SiteSettingsController;
use App\Http\Controllers\Api\HeroSlideController;
use Illuminate\Support\Facades\Route;

// ─── Public Catalog ───────────────────────────────────────────────────────────

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/shipping-methods', [ShippingMethodController::class, 'index']);
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::get('/governorates', [ShippingZoneController::class, 'getGovernorates']);
Route::post('/checkout/calculate-shipping', [ShippingZoneController::class, 'calculateShipping']);
Route::post('/orders', [OrderController::class, 'store']); // Storefront checkout
Route::middleware('auth:sanctum')->get('/orders/{order}', [OrderController::class, 'show']); // Customer order details (protected by OrderPolicy)
Route::post('/coupons/validate', [CouponController::class, 'validateCoupon'])
    ->middleware('throttle:10,1'); // Fix: max 10 attempts per minute per IP — prevents coupon enumeration brute-force
Route::get('/analytics/monthly-sales', [AdminDashboardController::class, 'monthlySales']);
Route::get('/tracking-pixels', [TrackingPixelController::class, 'index']); // Storefront: enabled pixel IDs
Route::get('/site-settings', [SiteSettingsController::class, 'index']); // Storefront: announcement bar & favicon
Route::get('/hero-slides', [HeroSlideController::class, 'index']); // Storefront: home page slider

Route::prefix('products')->group(function () {
    Route::get('/', [ProductCatalogController::class, 'index']);
    Route::get('/{slug}', [ProductCatalogController::class, 'show']);
    Route::get('/{productId}/reviews', [ReviewController::class, 'index']);
    Route::post('/{productId}/reviews', [ReviewController::class, 'store']);
});

// ─── Public Blog / Articles Routes ───────────────────────────────────────────
Route::prefix('posts')->group(function () {
    Route::get('/', [PostController::class, 'index']);
    Route::get('/featured', [PostController::class, 'featured']);
    Route::get('/categories', [PostController::class, 'categories']);
    Route::get('/{slug}', [PostController::class, 'show']);
});
Route::prefix('blogs')->group(function () {
    Route::get('/', [PostController::class, 'index']);
    Route::get('/featured', [PostController::class, 'featured']);
    Route::get('/categories', [PostController::class, 'categories']);
    Route::get('/{slug}', [PostController::class, 'show']);
});

// Public locations routes
Route::prefix('locations')->middleware('throttle:60,1')->group(function () {
    Route::get('/countries', [LocationController::class, 'getCountries']);
    Route::get('/governorates', [LocationController::class, 'getGovernorates']);
});

// ─── Authentication & Customer Account ─────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::get('/orders', [AuthController::class, 'orders']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Top-level aliases for customer auth
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::get('/customer/orders', [AuthController::class, 'orders']);
    Route::get('/profile/orders', [AuthController::class, 'orders']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ─── Admin Protected Routes ───────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/monthly-sales', [AdminDashboardController::class, 'monthlySales']);
    Route::get('/dashboard/monthly-sales', [AdminDashboardController::class, 'monthlySales']);

    // Admin Hero Slider (home page)
    Route::prefix('hero-slides')->group(function () {
        Route::get('/', [AdminHeroSlideController::class, 'index']);
        Route::post('/', [AdminHeroSlideController::class, 'store']);
        Route::put('/order', [AdminHeroSlideController::class, 'reorder']);
        Route::post('/{id}', [AdminHeroSlideController::class, 'update']);
        Route::delete('/{id}', [AdminHeroSlideController::class, 'destroy']);
        Route::patch('/{id}/toggle-active', [AdminHeroSlideController::class, 'toggleActive']);
    });

    // Admin Site Settings: announcement bar & favicon
    Route::prefix('site-settings')->group(function () {
        Route::get('/', [AdminSiteSettingsController::class, 'show']);
        Route::put('/announcement-bar', [AdminSiteSettingsController::class, 'updateAnnouncement']);
        Route::post('/announcement-bar/logo', [AdminSiteSettingsController::class, 'uploadAnnouncementLogo']);
        Route::delete('/announcement-bar/logo', [AdminSiteSettingsController::class, 'removeAnnouncementLogo']);
        Route::put('/features', [AdminSiteSettingsController::class, 'updateFeatures']);
        Route::post('/features/{index}/icon', [AdminSiteSettingsController::class, 'uploadFeatureIcon'])->whereNumber('index');
        Route::delete('/features/{index}/icon', [AdminSiteSettingsController::class, 'removeFeatureIcon'])->whereNumber('index');
        Route::post('/favicon', [AdminSiteSettingsController::class, 'uploadFavicon']);
        Route::delete('/favicon', [AdminSiteSettingsController::class, 'removeFavicon']);
    });

    // Admin Tracking Pixels (Meta, Google, TikTok, Snapchat)
    Route::get('/tracking-pixels', [AdminTrackingPixelController::class, 'show']);
    Route::put('/tracking-pixels', [AdminTrackingPixelController::class, 'update']);

    // Admin Categories CRUD
    Route::prefix('categories')->group(function () {
        Route::get('/', [AdminCategoryController::class, 'index']);
        Route::post('/', [AdminCategoryController::class, 'store']);
        Route::get('/{id}', [AdminCategoryController::class, 'show']);
        Route::post('/{id}', [AdminCategoryController::class, 'update']);
        Route::put('/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/{id}', [AdminCategoryController::class, 'destroy']);
    });

    // Admin Products CRUD
    Route::prefix('products')->group(function () {
        Route::get('/', [AdminProductController::class, 'index']);
        Route::post('/', [AdminProductController::class, 'store']);
        Route::get('/lookup', [AdminProductController::class, 'lookup']); // ← lightweight dropdown
        Route::get('/export', [AdminProductController::class, 'export']);
        Route::post('/import', [AdminProductController::class, 'import']);
        // Manual order of showcase sections: best_seller | featured (bundles) | new_arrival | trending
        Route::get('/showcase/{section}', [AdminProductController::class, 'showcase']);
        Route::put('/showcase/{section}/order', [AdminProductController::class, 'reorderShowcase']);
        Route::get('/{id}', [AdminProductController::class, 'show']);
        Route::post('/{id}', [AdminProductController::class, 'update']);
        Route::put('/{id}', [AdminProductController::class, 'update']);
        Route::delete('/{id}', [AdminProductController::class, 'destroy']);
    });

    // Admin Reviews Moderation & Management
    Route::prefix('reviews')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index']);
        Route::get('/{id}', [AdminReviewController::class, 'show']);
        Route::put('/{id}', [AdminReviewController::class, 'update']);
        Route::patch('/{id}/status', [AdminReviewController::class, 'updateStatus']);
        Route::delete('/{id}', [AdminReviewController::class, 'destroy']);
    });

    // Admin Shipping Methods CRUD & Toggle
    Route::prefix('shipping-methods')->group(function () {
        Route::get('/', [AdminShippingMethodController::class, 'index']);
        Route::post('/', [AdminShippingMethodController::class, 'store']);
        Route::get('/{id}', [AdminShippingMethodController::class, 'show']);
        Route::put('/{id}', [AdminShippingMethodController::class, 'update']);
        Route::delete('/{id}', [AdminShippingMethodController::class, 'destroy']);
        Route::patch('/{id}/toggle-active', [AdminShippingMethodController::class, 'toggleActive']);
        Route::post('/{id}/logo', [AdminShippingMethodController::class, 'uploadLogo']);
        Route::delete('/{id}/logo', [AdminShippingMethodController::class, 'removeLogo']);
    });

    // Admin Payment Methods CRUD & Toggle
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [AdminPaymentMethodController::class, 'index']);
        Route::post('/', [AdminPaymentMethodController::class, 'store']);
        Route::get('/{id}', [AdminPaymentMethodController::class, 'show']);
        Route::put('/{id}', [AdminPaymentMethodController::class, 'update']);
        Route::delete('/{id}', [AdminPaymentMethodController::class, 'destroy']);
        Route::patch('/{id}/toggle-active', [AdminPaymentMethodController::class, 'toggleActive']);
        Route::post('/{id}/logo', [AdminPaymentMethodController::class, 'uploadLogo']);
        Route::delete('/{id}/logo', [AdminPaymentMethodController::class, 'removeLogo']);
    });

    // Admin Orders CRUD & Status Management
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::post('/', [AdminOrderController::class, 'store']);
        Route::get('/stats', [AdminOrderController::class, 'stats']);
        Route::get('/export', [AdminOrderController::class, 'export']);
        Route::post('/bulk-delete', [AdminOrderController::class, 'bulkDestroy']);
        Route::get('/{id}', [AdminOrderController::class, 'show']);
        Route::put('/{id}', [AdminOrderController::class, 'update']);
        Route::patch('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::delete('/{id}', [AdminOrderController::class, 'destroy']);
    });

    // Admin Shipping Zones CRUD & Available Governorates
    Route::prefix('shipping-zones')->group(function () {
        Route::get('/', [AdminShippingZoneController::class, 'index']);
        Route::get('/available-governorates', [AdminShippingZoneController::class, 'availableGovernorates']);
        Route::post('/', [AdminShippingZoneController::class, 'store']);
        Route::get('/{id}', [AdminShippingZoneController::class, 'show']);
        Route::put('/{id}', [AdminShippingZoneController::class, 'update']);
        Route::delete('/{id}', [AdminShippingZoneController::class, 'destroy']);
        Route::patch('/{id}/toggle-active', [AdminShippingZoneController::class, 'toggleActive']);
    });

    // Admin Blog Posts CRUD & Status Toggle
    Route::prefix('posts')->group(function () {
        Route::get('/', [AdminPostController::class, 'index']);
        Route::post('/', [AdminPostController::class, 'store']);
        Route::get('/{id}', [AdminPostController::class, 'show']);
        Route::post('/{id}', [AdminPostController::class, 'update']);
        Route::put('/{id}', [AdminPostController::class, 'update']);
        Route::patch('/{id}/status', [AdminPostController::class, 'toggleStatus']);
        Route::delete('/{id}', [AdminPostController::class, 'destroy']);
    });
    Route::prefix('blogs')->group(function () {
        Route::get('/', [AdminPostController::class, 'index']);
        Route::post('/', [AdminPostController::class, 'store']);
        Route::get('/{id}', [AdminPostController::class, 'show']);
        Route::post('/{id}', [AdminPostController::class, 'update']);
        Route::put('/{id}', [AdminPostController::class, 'update']);
        Route::patch('/{id}/status', [AdminPostController::class, 'toggleStatus']);
        Route::delete('/{id}', [AdminPostController::class, 'destroy']);
    });

    // Admin Customers CRUD
    Route::prefix('customers')->group(function () {
        Route::get('/', [AdminCustomerController::class, 'index']);
        Route::post('/', [AdminCustomerController::class, 'store']);
        Route::get('/{id}', [AdminCustomerController::class, 'show']);
        Route::put('/{id}', [AdminCustomerController::class, 'update']);
        Route::delete('/{id}', [AdminCustomerController::class, 'destroy']);
    });

    // Admin Coupons CRUD & Status Toggle
    Route::prefix('coupons')->group(function () {
        Route::get('/', [AdminCouponController::class, 'index']);
        Route::post('/', [AdminCouponController::class, 'store']);
        Route::get('/{id}', [AdminCouponController::class, 'show']);
        Route::put('/{id}', [AdminCouponController::class, 'update']);
        Route::delete('/{id}', [AdminCouponController::class, 'destroy']);
        Route::patch('/{id}/toggle-active', [AdminCouponController::class, 'toggleActive']);
    });
});