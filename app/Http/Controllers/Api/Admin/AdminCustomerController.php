<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreCustomerRequest;
use App\Http\Requests\Admin\AdminUpdateCustomerRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AdminCustomerController extends Controller
{
    #[OA\Get(
        path: '/api/admin/customers',
        summary: 'List all registered customers with search, filter, and order metrics',
        tags: ['Admin Customers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Customers list fetched successfully')
        ]
    )]

    public function index(Request $request): JsonResponse
    {
        $query = User::customers();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($gov = $request->query('governorate')) {
            $query->where('governorate', $gov);
        }

        $perPage = (int) $request->query('per_page', 15);
        $customers = $query->latest()->paginate($perPage);

        // Attach order metrics to each customer
        $customers->getCollection()->transform(function ($customer) {
            $stats = Order::where('user_id', $customer->id)
                ->orWhere('customer_email', $customer->email)
                ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total), 0) as total_spent')
                ->first();

            $customer->orders_count = $stats ? (int) $stats->total_orders : 0;
            $customer->total_spent = $stats ? round((float) $stats->total_spent, 2) : 0.0;

            return $customer;
        });

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/customers',
        summary: 'Create a new customer manually',
        tags: ['Admin Customers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Customer created successfully'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function store(AdminStoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $password = !empty($validated['password'])
            ? Hash::make($validated['password'])
            : Hash::make('Secret1234');

        $customer = User::create([
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'] ?? '',
            'email'       => $validated['email'],
            'phone'       => $validated['phone'],
            'password'    => $password,
            'role'        => 'user',
            'country'     => $validated['country'] ?? 'Egypt',
            'governorate' => $validated['governorate'] ?? null,
            'address'     => $validated['address'] ?? null,
        ]);

        $customer->orders_count = 0;
        $customer->total_spent = 0.0;

        return response()->json([
            'message' => 'Customer created successfully',
            'data'    => $customer,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/customers/{id}',
        summary: 'Get customer details and order history',
        tags: ['Admin Customers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Customer details fetched successfully'),
            new OA\Response(response: 404, description: 'Customer not found')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $customer = User::findOrFail($id);

        $orders = Order::with('items')
            ->where('user_id', $customer->id)
            ->orWhere('customer_email', $customer->email)
            ->latest()
            ->get();

        $customer->orders_count = $orders->count();
        $customer->total_spent = round((float) $orders->sum('total'), 2);

        return response()->json([
            'data' => [
                'customer' => $customer,
                'orders'   => $orders,
            ]
        ]);
    }

    #[OA\Put(
        path: '/api/admin/customers/{id}',
        summary: 'Update customer details',
        tags: ['Admin Customers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Customer updated successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 404, description: 'Customer not found')
        ]
    )]
    public function update(AdminUpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = User::findOrFail($id);
        $validated = $request->validated();

        if (!empty($validated['password'])) {
            $customer->password = Hash::make($validated['password']);
        }

        if (isset($validated['first_name'])) $customer->first_name = $validated['first_name'];
        if (isset($validated['last_name'])) $customer->last_name = $validated['last_name'];
        if (isset($validated['email'])) $customer->email = $validated['email'];
        if (isset($validated['phone'])) $customer->phone = $validated['phone'];
        if (isset($validated['country'])) $customer->country = $validated['country'];
        if (isset($validated['governorate'])) $customer->governorate = $validated['governorate'];
        if (isset($validated['address'])) $customer->address = $validated['address'];

        $customer->save();

        $stats = Order::where('user_id', $customer->id)
            ->orWhere('customer_email', $customer->email)
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total), 0) as total_spent')
            ->first();

        $customer->orders_count = $stats ? (int) $stats->total_orders : 0;
        $customer->total_spent = $stats ? round((float) $stats->total_spent, 2) : 0.0;

        return response()->json([
            'message' => 'Customer updated successfully',
            'data'    => $customer,
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/customers/{id}',
        summary: 'Delete a customer',
        tags: ['Admin Customers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Customer deleted successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Customer not found')
        ]
    )]
    public function destroy(int $id): JsonResponse

    {
        $customer = User::findOrFail($id);

        if ($customer->role === 'admin') {
            return response()->json([
                'message' => 'Cannot delete an administrator account.',
            ], 403);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }
}
