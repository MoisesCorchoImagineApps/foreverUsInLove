<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Events\AccountDeleted;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthenticationService
{
    private UserRepositoryInterface $userRepository;

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const RATE_LIMIT_MINUTES = 15;
    private const TOKEN_EXPIRY_MINUTES = 60 * 24 * 7; // 7 days

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Authenticate user with credentials
     */
    public function authenticate(array $credentials, ?string $deviceName = null, array $deviceInfo = []): array
    {
        $identifier = $credentials['email'] ?? $credentials['phone'] ?? null;

        if (!$identifier) {
            throw ValidationException::withMessages([
                'identifier' => 'Email or phone number is required'
            ]);
        }

        $this->checkRateLimit($identifier);

        try {
            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                $this->handleFailedLogin($identifier, 'User not found');
                throw new AuthenticationException('Invalid credentials');
            }

            if (!Hash::check($credentials['password'], $user->password)) {
                $this->handleFailedLogin($identifier, 'Invalid password', $user);
                throw new AuthenticationException('Invalid credentials');
            }

            $this->validateUserStatus($user);
            $this->clearRateLimit($identifier);
            $this->updateUserLoginInfo($user, $deviceInfo);

            $token = $this->createAuthToken($user, $deviceName, $deviceInfo);

            event(new UserLoggedIn($user, 'password', uniqid(), null, $deviceInfo));

            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
            ]);

            return [
                'user' => $user->fresh(['profile', 'activeSubscription']),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes(self::TOKEN_EXPIRY_MINUTES),
            ];

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Authentication error', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException('Authentication failed');
        }
    }

    /**
     * Logout user from current device
     */
    public function logout(User $user, ?string $tokenId = null): bool
    {
        try {
            if ($tokenId) {
                $token = $user->tokens()->where('id', $tokenId)->first();
                if ($token) {
                    $token->delete();
                }
            } else {
                $currentToken = $user->currentAccessToken();
                if ($currentToken instanceof PersonalAccessToken) {
                    $currentToken->delete();
                }
            }

            $user->update(['last_logout_at' => now()]);

            Log::info('User logged out', ['user_id' => $user->id]);
            return true;

        } catch (Throwable $e) {
            Log::error('Logout error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Logout user from all devices
     */
    public function logoutFromAllDevices(User $user): bool
    {
        try {
            $user->tokens()->delete();
            $user->update(['last_logout_at' => now()]);

            Log::info('User logged out from all devices', ['user_id' => $user->id]);
            return true;

        } catch (Throwable $e) {
            Log::error('Logout from all devices error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(User $user, string $reason = 'user_request', bool $hardDelete = false): bool
    {
        try {
            $this->logoutFromAllDevices($user);

            if ($hardDelete) {
                $deleted = $this->userRepository->forceDelete($user->id);
            } else {
                $deleted = $this->userRepository->delete($user->id);
            }

            if ($deleted) {
                event(new AccountDeleted($user->toArray(), $hardDelete ? 'hard_delete' : 'soft_delete', $reason));

                Log::info('Account deleted', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reason' => $reason,
                    'hard_delete' => $hardDelete,
                ]);

                return true;
            }

            return false;

        } catch (Throwable $e) {
            Log::error('Account deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'account_deletion' => 'Failed to delete account. Please try again.'
            ]);
        }
    }

    /**
     * Get user's active sessions/tokens
     */
    public function getUserSessions(User $user): array
    {
        $tokens = $user->tokens()
            ->select(['id', 'name', 'last_used_at', 'created_at'])
            ->orderBy('last_used_at', 'desc')
            ->get();

        return $tokens->map(function (PersonalAccessToken $token) {
            return [
                'id' => $token->id,
                'device_name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === request()->user()?->currentAccessToken()?->id,
            ];
        })->toArray();
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->userRepository->findByEmail($identifier);
        }
        
        return $this->userRepository->findByPhone($identifier);
    }

    private function validateUserStatus(User $user): void
    {
        if ($user->status === 'inactive') {
            throw new AuthenticationException('Account is inactive. Please contact support.');
        }

        if ($user->status === 'suspended') {
            throw new AuthenticationException('Account is suspended. Please contact support.');
        }

        if ($user->status === 'banned') {
            throw new AuthenticationException('Account is banned.');
        }

        if ($user->deleted_at) {
            throw new AuthenticationException('Account has been deleted.');
        }
    }

    private function createAuthToken(User $user, ?string $deviceName = null, array $deviceInfo = []): string
    {
        $deviceName = $deviceName ?? $this->generateDeviceName($deviceInfo);
        $token = $user->createToken($deviceName, ['*'], now()->addMinutes(self::TOKEN_EXPIRY_MINUTES));
        
        return $token->plainTextToken;
    }

    private function generateDeviceName(array $deviceInfo): string
    {
        $platform = $deviceInfo['platform'] ?? 'Unknown';
        $browser = $deviceInfo['browser'] ?? 'Unknown';
        $timestamp = now()->format('M j, Y H:i');
        
        return "{$platform} - {$browser} - {$timestamp}";
    }

    private function updateUserLoginInfo(User $user, array $deviceInfo): void
    {
        $updateData = [
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ];

        if (isset($deviceInfo['ip_address'])) {
            $updateData['last_login_ip'] = $deviceInfo['ip_address'];
        }

        $user->update($updateData);
    }

    private function handleFailedLogin(string $identifier, string $reason, ?User $user = null): void
    {
        $key = 'login.' . $identifier;
        RateLimiter::hit($key, self::RATE_LIMIT_MINUTES * 60);

        Log::warning('Login attempt failed', [
            'identifier' => $identifier,
            'reason' => $reason,
            'user_id' => $user?->id,
            'attempts' => RateLimiter::attempts($key),
        ]);
    }

    private function checkRateLimit(string $identifier): void
    {
        $key = 'login.' . $identifier;
        
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);
            
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$minutes} minutes."
            ]);
        }
    }

    private function clearRateLimit(string $identifier): void
    {
        RateLimiter::clear('login.' . $identifier);
    }
}