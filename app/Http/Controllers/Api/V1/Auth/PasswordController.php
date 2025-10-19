<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\PasswordResetService;
use App\Infrastructure\External\TwilioService; // ⭐ NUEVO
use App\Infrastructure\External\SendGridService; // ⭐ NUEVO
use App\Infrastructure\External\FirebaseService; // ⭐ NUEVO
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PasswordController - Maneja el restablecimiento y cambio de contraseñas
 * 
 * Endpoints:
 * - POST /api/v1/auth/password/forgot - Solicitar reset (detecta email/phone automáticamente)
 * - POST /api/v1/auth/password/reset/email - Enviar código por email
 * - POST /api/v1/auth/password/reset/sms - Enviar código por SMS
 * - POST /api/v1/auth/password/verify-code - Verificar código de reset
 * - POST /api/v1/auth/password/reset - Restablecer contraseña
 * - PUT /api/v1/auth/password/change - Cambiar contraseña (autenticado)
 * - GET /api/v1/auth/password/strength - Verificar fuerza de contraseña
 * 
 * @package App\Http\Controllers\Api\V1\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class PasswordController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly TwilioService $twilioService, // ⭐ NUEVO
        private readonly SendGridService $sendGridService, // ⭐ NUEVO
        private readonly FirebaseService $firebaseService, // ⭐ NUEVO
    ) {}

    /**
     * ⭐ NUEVO - Solicitar reset de contraseña (auto-detecta email/phone)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        try {
            $identifier = $request->get('identifier');

            // Buscar usuario por email, phone o username
            $user = User::where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->orWhere('username', $identifier)
                ->first();

            if (!$user) {
                // Por seguridad, retornar éxito aunque no exista
                return $this->successResponse(
                    ['sent' => true],
                    'If an account exists, you will receive a reset code shortly.'
                );
            }

            // Detectar si es email o phone
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) || $identifier === $user->email;

            if ($isEmail) {
                // ⭐ Enviar por email
                $result = $this->sendGridService->sendPasswordReset(
                    $user,
                    $this->passwordResetService->generateResetToken($user)
                );
            } else {
                // ⭐ Enviar por SMS
                $code = $this->passwordResetService->generateResetCode($user);
                $result = $this->twilioService->sendSMS(
                    $user->phone,
                    "Your ForeverUsInLove password reset code is: {$code}. Valid for 15 minutes."
                );
            }

            Log::info('Password reset requested', [
                'user_id' => $user->id,
                'method' => $isEmail ? 'email' : 'sms',
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(
                ['sent' => true, 'method' => $isEmail ? 'email' : 'sms'],
                'If an account exists, you will receive a reset code shortly.'
            );

        } catch (\Throwable $e) {
            Log::error('Forgot password failed', [
                'identifier' => $request->get('identifier'),
                'error' => $e->getMessage(),
            ]);

            // Por seguridad, siempre retornar éxito
            return $this->successResponse(
                ['sent' => true],
                'If an account exists, you will receive a reset code shortly.'
            );
        }
    }

    /**
     * Send password reset code via email
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/password/reset/email",
     *     tags={"Password Reset"},
     *     summary="Send reset code via email",
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Reset code sent successfully")
     * )
     */
    public function sendResetCodeEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $email = $request->get('email');
            
            // Buscar usuario
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Por seguridad, retornar éxito aunque no exista
                return $this->successResponse([
                    'sent' => true,
                    'method' => 'email',
                    'expires_in_minutes' => 15,
                ], 'If an account exists, reset code sent to your email');
            }

            // ⭐ Generar token y enviar por SendGrid
            $resetToken = $this->passwordResetService->generateResetToken($user);
            $result = $this->sendGridService->sendPasswordReset($user, $resetToken);

            if ($result['success']) {
                Log::info('Password reset code sent via email', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse([
                    'sent' => true,
                    'method' => 'email',
                    'expires_in_minutes' => 15,
                ], 'Password reset code sent to your email');
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to send reset code',
                500,
                ['error_code' => $result['error_code'] ?? 'SEND_FAILED']
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Send password reset email failed', [
                'email' => $request->get('email'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to send reset code', 500);
        }
    }

    /**
     * Send password reset code via SMS
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/password/reset/sms",
     *     tags={"Password Reset"},
     *     summary="Send reset code via SMS",
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Reset code sent successfully")
     * )
     */
    public function sendResetCodeSMS(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        try {
            $phone = $request->get('phone');
            
            // Buscar usuario
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                // Por seguridad, retornar éxito aunque no exista
                return $this->successResponse([
                    'sent' => true,
                    'method' => 'sms',
                    'expires_in_minutes' => 15,
                ], 'If an account exists, reset code sent to your phone');
            }

            // ⭐ Generar código y enviar por Twilio
            $code = $this->passwordResetService->generateResetCode($user);
            $result = $this->twilioService->sendSMS(
                $user->phone,
                "Your ForeverUsInLove password reset code is: {$code}. Valid for 15 minutes. Do not share this code."
            );

            if ($result['success']) {
                Log::info('Password reset code sent via SMS', [
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse([
                    'sent' => true,
                    'method' => 'sms',
                    'expires_in_minutes' => 15,
                ], 'Password reset code sent to your phone');
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to send reset code',
                500,
                ['error_code' => $result['error_code'] ?? 'SEND_FAILED']
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Send password reset SMS failed', [
                'phone' => $request->get('phone'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to send reset code', 500);
        }
    }

    /**
     * Verify password reset code
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/password/verify-code",
     *     tags={"Password Reset"},
     *     summary="Verify reset code",
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Code verified successfully")
     * )
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        try {
            $identifier = $request->get('identifier');
            $code = $request->get('code');

            $result = $this->passwordResetService->verifyResetCode($identifier, $code);

            if ($result['success']) {
                Log::info('Password reset code verified', [
                    'identifier' => $identifier,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse([
                    'verified' => true,
                    'reset_token' => $result['reset_token'],
                    'expires_in_minutes' => 60,
                ], 'Reset code verified successfully. You can now reset your password.');
            }

            return $this->errorResponse(
                $result['error'] ?? 'Invalid or expired reset code',
                400,
                ['error_code' => $result['error_code'] ?? 'INVALID_CODE']
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Verify reset code failed', [
                'identifier' => $request->get('identifier'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to verify reset code', 500);
        }
    }

    /**
     * Reset password using token
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/password/reset",
     *     tags={"Password Reset"},
     *     summary="Reset password",
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Password reset successfully")
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'reset_token' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
        ]);

        try {
            DB::beginTransaction();

            $identifier = $request->get('identifier');
            $token = $request->get('reset_token');
            $password = $request->get('password');

            $result = $this->passwordResetService->resetPassword($identifier, $token, $password);

            if ($result['success']) {
                $user = $result['user'];

                // ⭐ Enviar confirmación por email
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Password Changed',
                    'Your password has been successfully reset.',
                    ['ip_address' => $request->ip()]
                );

                // ⭐ SMS de confirmación (opcional)
                if ($user->phone && config('password.send_sms_confirmation', true)) {
                    $this->twilioService->sendSecurityAlert(
                        $user,
                        "Your ForeverUsInLove password has been reset. If this wasn't you, contact support immediately."
                    );
                }

                // ⭐ Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Password Changed',
                        'Your password has been successfully reset',
                        ['type' => 'security_alert', 'action' => 'password_reset']
                    );
                }

                // Cerrar todas las sesiones activas por seguridad
                $user->tokens()->delete();

                DB::commit();

                Log::info('Password reset successfully', [
                    'user_id' => $user->id,
                    'identifier' => $identifier,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse(
                    ['reset' => true],
                    'Password reset successfully! You can now login with your new password.'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['error'] ?? 'Failed to reset password',
                400,
                ['error_code' => $result['error_code'] ?? 'RESET_FAILED']
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Reset password failed', [
                'identifier' => $request->get('identifier'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to reset password', 500);
        }
    }

    /**
     * Change password for authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Put(
     *     path="/api/v1/auth/password/change",
     *     tags={"Password Management"},
     *     summary="Change password",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Password changed successfully")
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
        ]);

        try {
            DB::beginTransaction();

            $user = $request->user();
            $currentPassword = $request->get('current_password');
            $newPassword = $request->get('new_password');

            $result = $this->passwordResetService->changePassword($user, $currentPassword, $newPassword);

            if ($result['success']) {
                // ⭐ Enviar confirmación por email
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Password Changed',
                    'Your password has been successfully changed.',
                    ['ip_address' => $request->ip()]
                );

                // ⭐ SMS de confirmación
                if ($user->phone) {
                    $this->twilioService->sendSecurityAlert(
                        $user,
                        "Your ForeverUsInLove password has been changed. If this wasn't you, contact support immediately."
                    );
                }

                // ⭐ Push notification a otros dispositivos
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Password Changed',
                        'Your password has been changed successfully',
                        ['type' => 'security_alert', 'action' => 'password_changed']
                    );
                }

                // Opcional: cerrar otras sesiones por seguridad
                if ($request->get('logout_other_devices', false)) {
                    $currentTokenId = $request->user()->currentAccessToken()?->id;
                    $user->tokens()->where('id', '!=', $currentTokenId)->delete();
                }

                DB::commit();

                Log::info('Password changed by user', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse(
                    ['changed' => true],
                    'Password changed successfully!'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['error'] ?? 'Failed to change password',
                400,
                ['error_code' => $result['error_code'] ?? 'CHANGE_FAILED']
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Change password failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to change password', 500);
        }
    }

    /**
     * ⭐ NUEVO - Verificar fuerza de contraseña
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkStrength(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        try {
            $password = $request->get('password');
            $strength = $this->passwordResetService->checkPasswordStrength($password);

            return $this->successResponse(
                $strength,
                'Password strength evaluated'
            );

        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to check password strength', 500);
        }
    }
}
