<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PHARMACIST])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $user = User::query()->findOrFail($id);

        if ((int) $request->user()->getKey() === (int) $user->getKey()) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'User deactivated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        $token = $user->createToken('pharmacy-api')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'role', 'is_active']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_active && $user->isAdmin(), 403);
    }
}
