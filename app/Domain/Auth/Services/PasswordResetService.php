<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    private UserRepositoryInterface $userRepository;

    private const MAX_RESET_ATTEMPTS = 3;
    private const RATE_LIMIT_MINUTES = 60;
    private const RESET_CODE_EXPIRY = 15;
    private const RESET_TOKEN_EXPIRY = 60;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Send password reset code via email
     */
    public function sendResetCodeViaEmail(string $email): bool
    {
        $this->checkRateLimit($email, 'email');

        try {
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                Log::info('Password reset requested for non-existent email', [
                    'email' => $email,
                ]);
                return true; // Don't reveal if email exists
            }

            if ($user->status !== 'active') {
                throw ValidationException::withMessages([
                    'email' => 'This account is not active. Please contact support.'
                ]);
            }

            $resetCode = $this->generateResetCode();
            
            $user->update([
                'password_reset_code' => $resetCode,
                'password_reset_code_expires_at' => now()->addMinutes(self::RESET_CODE_EXPIRY),
                'password_reset_sent_at' => now(),
            ]);

            RateLimiter::hit($this->getRateLimitKey($email, 'email'), self::RATE_LIMIT_MINUTES * 60);

            Log::info('Password reset code sent via email', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to send password reset code via email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send password reset code via SMS
     */
    public function sendResetCodeViaSMS(string $phone): bool
    {
        $this->checkRateLimit($phone, 'sms');

        try {
            $user = $this->userRepository->findByPhone($phone);

            if (!$user) {
                Log::info('Password reset requested for non-existent phone', [
                    'phone' => $phone,
                ]);
                return true; // Don't reveal if phone exists
            }

            if ($user->status !== 'active') {
                throw ValidationException::withMessages([
                    'phone' => 'This account is not active. Please contact support.'
                ]);
            }

            if (!$user->phone_verified_at) {
                throw ValidationException::withMessages([
                    'phone' => 'This phone number is not verified. Please use email reset instead.'
                ]);
            }

            $resetCode = $this->generateResetCode();
            
            $user->update([
                'password_reset_code' => $resetCode,
                'password_reset_code_expires_at' => now()->addMinutes(self::RESET_CODE_EXPIRY),
                'password_reset_sent_at' => now(),
            ]);

            RateLimiter::hit($this->getRateLimitKey($phone, 'sms'), self::RATE_LIMIT_MINUTES * 60);

            Log::info('Password reset code sent via SMS', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to send password reset code via SMS', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify password reset code
     */
    public function verifyResetCode(string $identifier, string $code): string
    {
        try {
            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                throw ValidationException::withMessages([
                    'identifier' => 'Invalid identifier.'
                ]);
            }

            if (!$this->isValidResetCode($user, $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Invalid or expired reset code.'
                ]);
            }

            $resetToken = Str::random(64);
            
            $user->update([
                'password_reset_token' => Hash::make($resetToken),
                'password_reset_token_expires_at' => now()->addMinutes(self::RESET_TOKEN_EXPIRY),
                'password_reset_code' => null,
                'password_reset_code_expires_at' => null,
            ]);

            Log::info('Password reset code verified', [
                'user_id' => $user->id,
                'identifier' => $identifier,
            ]);

            return $resetToken;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to verify reset code', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Failed to verify reset code. Please try again.'
            ]);
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $identifier, string $token, string $newPassword): bool
    {
        try {
            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                throw ValidationException::withMessages([
                    'identifier' => 'Invalid identifier.'
                ]);
            }

            if (!$this->isValidResetToken($user, $token)) {
                throw ValidationException::withMessages([
                    'token' => 'Invalid or expired reset token.'
                ]);
            }

            if (Hash::check($newPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'New password must be different from current password.'
                ]);
            }

            $user->update([
                'password' => Hash::make($newPassword),
                'password_reset_token' => null,
                'password_reset_token_expires_at' => null,
                'password_reset_code' => null,
                'password_reset_code_expires_at' => null,
                'password_changed_at' => now(),
            ]);

            $user->tokens()->delete();
            $this->clearRateLimit($identifier);

            Log::info('Password reset completed', [
                'user_id' => $user->id,
                'identifier' => $identifier,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to reset password', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        try {
            if (!Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Current password is incorrect.'
                ]);
            }

            if (Hash::check($newPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'new_password' => 'New password must be different from current password.'
                ]);
            }

            $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now(),
            ]);

            Log::info('Password changed by user', [
                'user_id' => $user->id,
            ]);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to change password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if user has pending reset request
     */
    public function hasPendingResetRequest(User $user): bool
    {
        return $user->password_reset_code && 
               $user->password_reset_code_expires_at && 
               Carbon::parse($user->password_reset_code_expires_at)->isFuture();
    }

    /**
     * Cancel pending reset request
     */
    public function cancelResetRequest(User $user): bool
    {
        try {
            $user->update([
                'password_reset_code' => null,
                'password_reset_code_expires_at' => null,
                'password_reset_token' => null,
                'password_reset_token_expires_at' => null,
            ]);

            Log::info('Password reset request cancelled', [
                'user_id' => $user->id,
            ]);

            return true;

        } catch (Throwable $e) {
            Log::error('Failed to cancel reset request', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->userRepository->findByEmail($identifier);
        }
        
        return $this->userRepository->findByPhone($identifier);
    }

    private function isValidResetCode(User $user, string $code): bool
    {
        if (!$user->password_reset_code || !$user->password_reset_code_expires_at) {
            return false;
        }

        if (Carbon::parse($user->password_reset_code_expires_at)->isPast()) {
            return false;
        }

        return hash_equals($user->password_reset_code, $code);
    }

    private function isValidResetToken(User $user, string $token): bool
    {
        if (!$user->password_reset_token || !$user->password_reset_token_expires_at) {
            return false;
        }

        if (Carbon::parse($user->password_reset_token_expires_at)->isPast()) {
            return false;
        }

        return Hash::check($token, $user->password_reset_token);
    }

    private function generateResetCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function checkRateLimit(string $identifier, string $method): void
    {
        $key = $this->getRateLimitKey($identifier, $method);
        
        if (RateLimiter::tooManyAttempts($key, self::MAX_RESET_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);
            
            throw ValidationException::withMessages([
                'identifier' => "Too many reset attempts. Please try again in {$minutes} minutes."
            ]);
        }
    }

    private function getRateLimitKey(string $identifier, string $method): string
    {
        return "password_reset.{$method}." . md5($identifier);
    }

    private function clearRateLimit(string $identifier): void
    {
        RateLimiter::clear($this->getRateLimitKey($identifier, 'email'));
        RateLimiter::clear($this->getRateLimitKey($identifier, 'sms'));
    }
}