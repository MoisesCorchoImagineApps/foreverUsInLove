<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\RegistrationService;
use App\Domain\Auth\Services\FaceIdVerificationService;
use App\Infrastructure\External\OnfidoService; // ⭐ NUEVO
use App\Infrastructure\External\TwilioService; // ⭐ NUEVO
use App\Infrastructure\External\SendGridService; // ⭐ NUEVO
use App\Infrastructure\External\CloudinaryService; // ⭐ NUEVO (si subes fotos)
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly FaceIdVerificationService $faceIdService,
        private readonly TwilioService $twilioService, // ⭐ NUEVO
        private readonly SendGridService $sendGridService, // ⭐ NUEVO
        private readonly OnfidoService $onfidoService, // ⭐ NUEVO
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $registrationData = $request->getRegistrationData();

            // 1. Registrar usuario
            $result = $this->registrationService->register(
                $registrationData['user_data'],
                $registrationData['device_info']
            );

            $user = $result['user'];

            // 2. Enviar verificación de teléfono vía Twilio
            if ($user->phone) {
                $smsResult = $this->twilioService->sendVerificationCode($user->phone);
                
                if (!$smsResult['success']) {
                    Log::warning('SMS verification failed during registration', [
                        'user_id' => $user->id,
                        'error' => $smsResult['error'] ?? 'Unknown error',
                    ]);
                }
            }

            // 3. Enviar verificación de email vía SendGrid
            $emailResult = $this->sendGridService->sendEmailVerification($user);
            
            if (!$emailResult['success']) {
                Log::warning('Email verification failed during registration', [
                    'user_id' => $user->id,
                    'error' => $emailResult['error'] ?? 'Unknown error',
                ]);
            }

            // 4. Enviar email de bienvenida
            $this->sendGridService->sendWelcomeEmail($user);

            // 5. Registrar Face ID si está presente
            if ($request->hasFaceIdRegistration()) {
                $faceIdResult = $this->registerFaceIdTemplate($request, $user);
                
                if (!$faceIdResult['success']) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Registration failed: ' . $faceIdResult['message'],
                        422
                    );
                }
                
                $result['face_id'] = $faceIdResult;
            }

            DB::commit();

            Log::info('New user registered successfully', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'has_phone' => !empty($user->phone),
                'has_face_id' => $request->hasFaceIdRegistration(),
            ]);

            return $this->createdResponse(
                $result,
                'Registration successful! Please verify your email and phone.',
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Registration failed. Please try again later.',
                500
            );
        }
    }

    /**
     * Verificar código de email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        try {
            $user = $request->user();
            
            // ⭐ Usar SendGridService para verificar
            $result = $this->sendGridService->verifyEmailCode($user, $request->get('code'));

            if ($result['success']) {
                return $this->successResponse(
                    $result,
                    'Email verified successfully!'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Email verification failed',
                400,
                ['error_code' => $result['error_code'] ?? 'VERIFICATION_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Email verification failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Email verification failed', 500);
        }
    }

    /**
     * Verificar código de teléfono
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        try {
            $user = $request->user();
            
            // ⭐ Usar TwilioService para verificar
            $result = $this->twilioService->verifyCode($user->phone, $request->get('code'));

            if ($result['success']) {
                // Actualizar estado del usuario
                $user->update([
                    'phone_verified_at' => now(),
                    'status' => $user->status === 'pending_verification' && $user->email_verified_at
                        ? 'active'
                        : $user->status,
                ]);

                return $this->successResponse(
                    $result,
                    'Phone verified successfully!'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Phone verification failed',
                400,
                [
                    'error_code' => $result['error_code'] ?? 'VERIFICATION_FAILED',
                    'attempts_remaining' => $result['attempts_remaining'] ?? null,
                ]
            );

        } catch (\Throwable $e) {
            Log::error('Phone verification failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Phone verification failed', 500);
        }
    }

    /**
     * Reenviar código de verificación
     */
    public function resendCode(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:email,phone',
        ]);

        try {
            $user = $request->user();
            $type = $request->get('type');

            if ($type === 'email') {
                // ⭐ Reenviar vía SendGrid
                $result = $this->sendGridService->sendEmailVerification($user);
            } else {
                // ⭐ Reenviar vía Twilio
                $result = $this->twilioService->sendVerificationCode($user->phone);
            }

            if ($result['success']) {
                return $this->successResponse(
                    ['sent' => true, 'type' => $type],
                    "Verification code sent to your {$type}"
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to resend verification code',
                400,
                ['error_code' => $result['error_code'] ?? 'RESEND_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Resend verification code failed', [
                'user_id' => $request->user()->id ?? null,
                'type' => $request->get('type'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to resend verification code', 500);
        }
    }

    /**
     * Verificar disponibilidad de username
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|min:3|max:30|alpha_dash',
        ]);

        try {
            $username = $request->get('username');
            $available = $this->registrationService->isUsernameAvailable($username);

            return $this->successResponse([
                'username' => $username,
                'available' => $available,
            ], $available ? 'Username is available' : 'Username is already taken');

        } catch (\Throwable $e) {
            Log::error('Check username failed', [
                'username' => $request->get('username'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to check username availability', 500);
        }
    }

    /**
     * Sugerir usernames disponibles
     */
    public function suggestUsernames(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2|max:50',
            'count' => 'sometimes|integer|min:1|max:10',
        ]);

        try {
            $name = $request->get('name');
            $count = $request->get('count', 5);

            $suggestions = $this->registrationService->generateUsernameSuggestions($name, $count);

            return $this->successResponse(
                ['suggestions' => $suggestions],
                'Username suggestions generated successfully'
            );

        } catch (\Throwable $e) {
            Log::error('Generate username suggestions failed', [
                'name' => $request->get('name'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to generate username suggestions', 500);
        }
    }

    /**
     * Registrar plantilla de Face ID
     */
    private function registerFaceIdTemplate(RegisterRequest $request, $user): array
    {
        try {
            $faceTemplate = [
                'biometric_data' => $request->get('face_id_data'),
                'quality_score' => $request->get('quality_score', 0.9),
                'feature_points' => $request->get('feature_points', []),
            ];

            $metadata = [
                'resolution' => $request->get('metadata.resolution'),
                'lighting_conditions' => $request->get('metadata.lighting_conditions'),
                'capture_angle' => $request->get('metadata.capture_angle'),
            ];

            return $this->faceIdService->registerFaceTemplate(
                $user,
                $faceTemplate,
                $request->get('device_info.device_id'),
                $metadata
            );

        } catch (\Throwable $e) {
            Log::error('Face ID registration failed during user registration', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Face ID registration failed: ' . $e->getMessage(),
            ];
        }
    }
}