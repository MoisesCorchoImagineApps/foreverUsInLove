<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationService
{
    private UserRepositoryInterface $userRepository;
    private AuthenticationService $authService;

    private const USERNAME_MIN_LENGTH = 3;
    private const USERNAME_MAX_LENGTH = 30;
    private const VERIFICATION_CODE_EXPIRY = 15;

    public function __construct(
        UserRepositoryInterface $userRepository,
        AuthenticationService $authService
    ) {
        $this->userRepository = $userRepository;
        $this->authService = $authService;
    }

    /**
     * Register a new user
     */
    public function register(array $userData, array $deviceInfo = []): array
    {
        try {
            $this->validateRegistrationData($userData);

            return DB::transaction(function () use ($userData, $deviceInfo) {
                $user = $this->createUser($userData);

                event(new UserRegistered($user, $deviceInfo));
                event(new Registered($user));

                $authData = $this->authService->authenticate([
                    'email' => $user->email,
                    'password' => $userData['password']
                ], $deviceInfo['device_name'] ?? null, $deviceInfo);

                Log::info('User registered successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return [
                    'user' => $user->fresh(['profile']),
                    'token' => $authData['token'],
                    'token_type' => 'Bearer',
                    'expires_at' => $authData['expires_at'],
                    'verification_required' => true,
                    'next_step' => 'verify_email_phone',
                ];
            });

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'user_data' => Arr::except($userData, ['password']),
            ]);

            throw ValidationException::withMessages([
                'registration' => 'Registration failed. Please try again.'
            ]);
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(User $user, string $code): bool
    {
        try {
            if ($user->email_verified_at) {
                return true;
            }

            if (!$this->verifyCode($user, 'email', $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Invalid or expired verification code.'
                ]);
            }

            $user->update([
                'email_verified_at' => now(),
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
            ]);

            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Email verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify phone number
     */
    public function verifyPhone(User $user, string $code): bool
    {
        try {
            if ($user->phone_verified_at) {
                return true;
            }

            if (!$this->verifyCode($user, 'phone', $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Invalid or expired verification code.'
                ]);
            }

            $user->update([
                'phone_verified_at' => now(),
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
            ]);

            Log::info('Phone verified successfully', [
                'user_id' => $user->id,
                'phone' => $user->phone,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Phone verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(User $user, string $type): bool
    {
        if (!in_array($type, ['email', 'phone'])) {
            throw ValidationException::withMessages([
                'type' => 'Invalid verification type.'
            ]);
        }

        if (($type === 'email' && $user->email_verified_at) || ($type === 'phone' && $user->phone_verified_at)) {
            throw ValidationException::withMessages([
                $type => ucfirst($type) . ' is already verified.'
            ]);
        }

        $lastSentField = $type . '_verification_sent_at';
        $lastSent = $user->$lastSentField;
        
        if ($lastSent && $lastSent->diffInMinutes(now()) < 2) {
            $waitTime = 2 - $lastSent->diffInMinutes(now());
            throw ValidationException::withMessages([
                'code' => "Please wait {$waitTime} minute(s) before requesting another code."
            ]);
        }

        try {
            $code = $this->generateVerificationCode();
            
            $user->update([
                $type . '_verification_code' => $code,
                $type . '_verification_expires_at' => now()->addMinutes(self::VERIFICATION_CODE_EXPIRY),
                $type . '_verification_sent_at' => now(),
            ]);

            Log::info('Verification code resent', [
                'user_id' => $user->id,
                'type' => $type,
            ]);

            return true;

        } catch (Throwable $e) {
            Log::error('Failed to resend verification code', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if username is available
     */
    public function isUsernameAvailable(string $username, ?int $excludeUserId = null): bool
    {
        return $this->userRepository->isUsernameAvailable($username, $excludeUserId);
    }

    /**
     * Generate unique username suggestions
     */
    public function generateUsernameSuggestions(string $baseName, int $count = 5): array
    {
        $suggestions = [];
        $baseName = Str::slug($baseName, '');
        
        if (strlen($baseName) < self::USERNAME_MIN_LENGTH) {
            $baseName = str_pad($baseName, self::USERNAME_MIN_LENGTH, '0');
        }
        
        if (strlen($baseName) > self::USERNAME_MAX_LENGTH) {
            $baseName = substr($baseName, 0, self::USERNAME_MAX_LENGTH);
        }

        if ($this->isUsernameAvailable($baseName)) {
            $suggestions[] = $baseName;
        }

        while (count($suggestions) < $count) {
            $variation = $baseName . rand(10, 9999);
            
            if (strlen($variation) <= self::USERNAME_MAX_LENGTH && $this->isUsernameAvailable($variation)) {
                $suggestions[] = $variation;
            }
        }

        return array_slice($suggestions, 0, $count);
    }

    private function validateRegistrationData(array $userData): void
    {
        if ($this->userRepository->findByEmail($userData['email'])) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.'
            ]);
        }

        if (isset($userData['phone']) && $this->userRepository->findByPhone($userData['phone'])) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.'
            ]);
        }

        if (isset($userData['username']) && !$this->isUsernameAvailable($userData['username'])) {
            throw ValidationException::withMessages([
                'username' => 'This username is already taken.'
            ]);
        }

        if (isset($userData['date_of_birth'])) {
            $age = Carbon::parse($userData['date_of_birth'])->age;
            if ($age < 18) {
                throw ValidationException::withMessages([
                    'date_of_birth' => 'You must be at least 18 years old to register.'
                ]);
            }
        }
    }

    private function createUser(array $userData): User
    {
        $userData['password'] = Hash::make($userData['password']);
        $userData['user_type'] = 'user';
        $userData['status'] = 'active';
        
        $userData['email_verification_code'] = $this->generateVerificationCode();
        $userData['email_verification_expires_at'] = now()->addMinutes(self::VERIFICATION_CODE_EXPIRY);
        
        if (isset($userData['phone'])) {
            $userData['phone_verification_code'] = $this->generateVerificationCode();
            $userData['phone_verification_expires_at'] = now()->addMinutes(self::VERIFICATION_CODE_EXPIRY);
        }

        return $this->userRepository->create($userData);
    }

    private function verifyCode(User $user, string $type, string $code): bool
    {
        $codeField = $type . '_verification_code';
        $expiryField = $type . '_verification_expires_at';

        $storedCode = $user->$codeField;
        $expiryTime = $user->$expiryField;

        if (!$storedCode || !$expiryTime) {
            return false;
        }

        if (Carbon::parse($expiryTime)->isPast()) {
            return false;
        }

        return hash_equals($storedCode, $code);
    }

    private function generateVerificationCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}