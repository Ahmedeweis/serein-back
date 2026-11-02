<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new customer account',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation', 'governorate', 'address'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Ahmed'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Eweis'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmed@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '01012345678'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret1234'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Secret1234'),
                    new OA\Property(property: 'country', type: 'string', example: 'Egypt'),
                    new OA\Property(property: 'governorate', type: 'string', example: 'Cairo'),
                    new OA\Property(property: 'address', type: 'string', example: '15 El-Tahrir Street')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'] ?? '',
            'email'       => $validated['email'],
            'phone'       => $validated['phone'],
            'password'    => Hash::make($validated['password']),
            'role'        => 'user',
            'country'     => $validated['country'] ?? 'Egypt',
            'governorate' => $validated['governorate'],
            'address'     => $validated['address'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user'    => new UserResource($user),
            'token'   => $token,
        ], 201);

    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Log in user to obtain access token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmed@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret1234')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful'),
            new OA\Response(response: 401, description: 'Invalid credentials')
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        // Invalidate previous tokens to prevent token bloat
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Get logged-in user profile',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User profile fetched'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Put(
        path: '/api/auth/profile',
        summary: 'Update customer personal info, address, or password',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Ahmed'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Eweis'),
                    new OA\Property(property: 'phone', type: 'string', example: '01012345678'),
                    new OA\Property(property: 'country', type: 'string', example: 'Egypt'),
                    new OA\Property(property: 'governorate', type: 'string', example: 'Cairo'),
                    new OA\Property(property: 'address', type: 'string', example: '15 El-Tahrir Street'),
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated successfully'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function updateProfile(\App\Http\Requests\Auth\UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // If user wants to change password, check current_password
        if (!empty($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'كلمة المرور الحالية غير صحيحة.',
                    'errors'  => [
                        'current_password' => ['كلمة المرور الحالية غير صحيحة.']
                    ]
                ], 422);
            }
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['first_name'])) $user->first_name = $validated['first_name'];
        if (isset($validated['last_name'])) $user->last_name = $validated['last_name'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['governorate'])) $user->governorate = $validated['governorate'];
        if (isset($validated['address'])) $user->address = $validated['address'];
        if (isset($validated['country'])) $user->country = $validated['country'];

        $user->save();

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'user'    => new UserResource($user),
        ]);
    }

    #[OA\Get(
        path: '/api/auth/orders',
        summary: 'Get customer order history',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Orders list fetched')
        ]
    )]
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $orders = \App\Models\Order::with(['items', 'shippingZone'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($orders);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout and revoke current token',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}