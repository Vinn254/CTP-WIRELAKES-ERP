<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * Creates a user and returns a Sanctum personal access token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed by User casts
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * POST /api/auth/login
     * Returns a Sanctum personal access token on success.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * GET /api/me
     * Returns the authenticated user, and for a member account the role that drives the app's tabs.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = \App\Support\MemberPortal::resolve($user);

        return response()->json([
            'user' => $user,
            'member_type' => $member ? ($member instanceof \App\Models\FundiRegistration ? 'fundi' : 'ctp') : null,
            'member_name' => $member?->full_name,
            'staff' => in_array($user->role, ['admin', 'superadmin'], true),
        ]);
    }

    /**
     * POST /api/auth/logout
     * Revokes the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
