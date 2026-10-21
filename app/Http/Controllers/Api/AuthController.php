<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\OtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\City;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cityId = $data['city_id'] ?? $this->defaultCityId();

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'area' => $data['area'] ?? null,
            'city_id' => $cityId,
            'status' => UserStatus::Pending->value,
        ]);

        $user->assignRole(Role::Customer->value);

        if ($data['role'] === 'seller') {
            $user->assignRole(Role::Seller->value);

            SellerProfile::create([
                'user_id' => $user->id,
                'city_id' => $cityId,
                'shop_name' => $data['shop_name'],
                'slug' => $this->uniqueSellerSlug($data['shop_name']),
                'area' => $data['area'] ?? null,
            ]);
        }

        return $this->issueOtp($user, 'register', 201, [
            'message' => 'Account created. Enter the code we sent to verify your number.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $purpose = $data['purpose'] ?? 'register';
        $user = User::where('phone', $data['phone'])->firstOrFail();

        try {
            $this->otp->verify($user->phone, $purpose, $data['code']);
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'status' => UserStatus::Active->value,
            'last_login_at' => now(),
        ])->save();

        return response()->json([
            'token' => $user->createToken('bss')->plainTextToken,
            'user' => new UserResource($user->load('sellerProfile')),
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('phone', $data['phone'])->firstOrFail();

        return $this->issueOtp($user, $data['purpose'] ?? 'register', 200, [
            'message' => 'A new code has been sent.',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! Auth::validate(['phone' => $data['phone'], 'password' => $data['password']])) {
            throw ValidationException::withMessages(['phone' => 'Those details don\'t match an account.']);
        }

        if ($user->phone_verified_at === null) {
            return $this->issueOtp($user, 'login', 403, [
                'message' => 'Verify your number to continue.',
                'code' => 'phone_not_verified',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('bss')->plainTextToken,
            'user' => new UserResource($user->load('sellerProfile')),
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function issueOtp(User $user, string $purpose, int $status, array $extra): JsonResponse
    {
        $code = $this->otp->generate($user->phone, $purpose);

        return response()->json([
            ...$extra,
            'phone' => $user->phone,
            ...(config('bss.otp.expose_debug_code') && ! app()->isProduction() ? ['debug_code' => $code] : []),
        ], $status);
    }

    /** The launch city (config `bss.default_city`), used when a signup doesn't pick one. */
    private function defaultCityId(): ?int
    {
        return City::where('slug', config('bss.default_city'))->value('id');
    }

    private function uniqueSellerSlug(string $shopName): string
    {
        $base = Str::slug($shopName) ?: 'shop';
        $slug = $base;
        $i = 1;

        while (SellerProfile::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
