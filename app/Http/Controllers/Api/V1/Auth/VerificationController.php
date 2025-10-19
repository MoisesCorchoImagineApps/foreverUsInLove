<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Infrastructure\External\TwilioService;
use App\Infrastructure\External\SendGridService;
use App\Infrastructure\External\OnfidoService;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * VerificationController - Maneja todas las verificaciones de usuarios
 * 
 * Endpoints:
 * - POST /api/v1/auth/verification/email/send - Enviar código email
 * - POST /api/v1/auth/verification/email/verify - Verificar código email
 * - POST /api/v1/auth/verification/phone/send - Enviar código SMS
 * - POST /api/v1/auth/verification/phone/verify - Verificar código SMS
 * - POST /api/v1/auth/verification/identity/start - Iniciar verificación Onfido
 * - POST /api/v1/auth/verification/identity/upload-document - Subir documento
 * - POST /api/v1/auth/verification/identity/upload-selfie - Subir selfie
 * - GET /api/v1/auth/verification/identity/status - Estado verificación Onfido
 * - POST /api/v1/auth/verification/resend - Reenviar cualquier código
 * 
 * @package App\Http\Controllers\Api\V1\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class VerificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TwilioService $twilioService,
        private readonly SendGridService $sendGridService,
        private readonly OnfidoService $onfidoService,
    ) {
        // Todos los endpoints requieren autenticación
        $this->middleware('auth:sanctum');
    }

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Enviar código de verificación por email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendEmailVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar si ya está verificado
            if ($user->is_verified) {
                return $this->errorResponse(
                    'Email is already verified',
                    400,
                    ['error_code' => 'ALREADY_VERIFIED']
                );
            }

            $result = $this->sendGridService->sendEmailVerification($user);

            if ($result['success']) {
                return $this->successResponse(
                    ['sent' => true, 'email' => $user->email],
                    'Verification code sent to your email'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to send verification email',
                400,
                ['error_code' => $result['error_code'] ?? 'SEND_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Send email verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to send verification email', 500);
        }
    }

    /**
     * Verificar código de email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        try {
            $user = $request->user();
            $code = $request->get('code');

            $result = $this->sendGridService->verifyEmailCode($user, $code);

            if ($result['success']) {
                Log::info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return $this->successResponse(
                    $result,
                    'Email verified successfully! 🎉'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Invalid verification code',
                400,
                [
                    'error_code' => $result['error_code'] ?? 'INVALID_CODE',
                    'attempts_remaining' => $result['attempts_remaining'] ?? null,
                ]
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Email verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Email verification failed', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Phone Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Enviar código de verificación por SMS
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendPhoneVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar si tiene teléfono
            if (!$user->phone) {
                return $this->errorResponse(
                    'No phone number associated with this account',
                    400,
                    ['error_code' => 'NO_PHONE_NUMBER']
                );
            }

            // Verificar si ya está verificado
            if ($user->is_phone_verified) {
                return $this->errorResponse(
                    'Phone number is already verified',
                    400,
                    ['error_code' => 'ALREADY_VERIFIED']
                );
            }

            // Usar Twilio Verify API (recomendado) o método manual
            $useVerifyAPI = config('twilio.use_verify_api', true);

            if ($useVerifyAPI && config('twilio.verify_service_sid')) {
                $result = $this->twilioService->sendVerifyAPI($user->phone);
            } else {
                $result = $this->twilioService->sendVerificationCode($user->phone);
            }

            if ($result['success']) {
                return $this->successResponse(
                    ['sent' => true, 'phone' => $user->phone],
                    'Verification code sent to your phone'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to send verification SMS',
                400,
                ['error_code' => $result['error_code'] ?? 'SEND_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Send phone verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to send verification SMS', 500);
        }
    }

    /**
     * Verificar código de teléfono
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        try {
            $user = $request->user();
            $code = $request->get('code');

            // Usar Twilio Verify API o método manual
            $useVerifyAPI = config('twilio.use_verify_api', true);

            if ($useVerifyAPI && config('twilio.verify_service_sid')) {
                $result = $this->twilioService->checkVerifyAPI($user->phone, $code);
            } else {
                $result = $this->twilioService->verifyCode($user->phone, $code);
            }

            if ($result['success']) {
                // Actualizar usuario
                $user->update([
                    'phone_verified_at' => now(),
                    'status' => $user->status === 'pending_verification' && $user->email_verified_at
                        ? 'active'
                        : $user->status,
                ]);

                Log::info('Phone verified successfully', [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                ]);

                return $this->successResponse(
                    ['verified' => true, 'phone' => $user->phone],
                    'Phone number verified successfully! 🎉'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Invalid verification code',
                400,
                [
                    'error_code' => $result['error_code'] ?? 'INVALID_CODE',
                    'attempts_remaining' => $result['attempts_remaining'] ?? null,
                ]
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Phone verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Phone verification failed', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Identity Verification (Onfido)
    |--------------------------------------------------------------------------
    */

    /**
     * Iniciar verificación de identidad con Onfido
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function startIdentityVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Verificar si ya tiene verificación pendiente o aprobada
            if ($user->onfido_check_id) {
                $checkResult = $this->onfidoService->getCheckResult($user->onfido_check_id);
                
                if ($checkResult['success'] && $checkResult['status'] === 'in_progress') {
                    return $this->errorResponse(
                        'Verification already in progress',
                        400,
                        ['error_code' => 'VERIFICATION_IN_PROGRESS']
                    );
                }
            }

            // Crear applicant en Onfido
            $result = $this->onfidoService->createApplicant($user);

            if ($result['success']) {
                return $this->successResponse(
                    $result,
                    'Identity verification started. Please upload your documents.'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to start identity verification',
                400,
                ['error_code' => $result['error_code'] ?? 'START_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Start identity verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to start identity verification', 500);
        }
    }

    /**
     * Subir documento de identidad
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadIdentityDocument(Request $request): JsonResponse
    {
        $request->validate([
            'document' => 'required|string', // Base64 o ruta temporal
            'document_type' => 'required|string|in:passport,driving_licence,national_identity_card',
            'document_side' => 'sometimes|string|in:front,back',
        ]);

        try {
            $user = $request->user();

            // Verificar que tenga applicant_id
            if (!$user->onfido_applicant_id) {
                return $this->errorResponse(
                    'Identity verification not started. Please start verification first.',
                    400,
                    ['error_code' => 'VERIFICATION_NOT_STARTED']
                );
            }

            $result = $this->onfidoService->uploadDocument(
                $user->onfido_applicant_id,
                $request->get('document'),
                $request->get('document_type'),
                $request->get('document_side', 'front')
            );

            if ($result['success']) {
                return $this->successResponse(
                    $result,
                    'Document uploaded successfully'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to upload document',
                400,
                ['error_code' => $result['error_code'] ?? 'UPLOAD_FAILED']
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Upload identity document failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to upload document', 500);
        }
    }

    /**
     * Subir selfie para verificación facial
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadIdentitySelfie(Request $request): JsonResponse
    {
        $request->validate([
            'selfie' => 'required|string', // Base64 o ruta temporal
        ]);

        try {
            $user = $request->user();

            if (!$user->onfido_applicant_id) {
                return $this->errorResponse(
                    'Identity verification not started',
                    400,
                    ['error_code' => 'VERIFICATION_NOT_STARTED']
                );
            }

            $result = $this->onfidoService->uploadLiveSelfie(
                $user->onfido_applicant_id,
                $request->get('selfie')
            );

            if ($result['success']) {
                // Crear el check automáticamente después de subir selfie
                $checkResult = $this->onfidoService->createCheck($user->onfido_applicant_id);

                if ($checkResult['success']) {
                    $user->update([
                        'onfido_check_id' => $checkResult['check_id'],
                        'verification_status' => 'pending',
                    ]);

                    return $this->successResponse(
                        array_merge($result, $checkResult),
                        'Selfie uploaded successfully. Verification in progress.'
                    );
                }
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to upload selfie',
                400,
                ['error_code' => $result['error_code'] ?? 'UPLOAD_FAILED']
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Upload identity selfie failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to upload selfie', 500);
        }
    }

    /**
     * Obtener estado de verificación de identidad
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getIdentityVerificationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->onfido_check_id) {
                return $this->errorResponse(
                    'No identity verification found',
                    404,
                    ['error_code' => 'NO_VERIFICATION_FOUND']
                );
            }

            $result = $this->onfidoService->getCheckResult($user->onfido_check_id);

            if ($result['success']) {
                // Actualizar estado del usuario si el check está completo
                if ($result['status'] === 'complete') {
                    $verificationStatus = $result['result'] === 'clear' ? 'verified' : 'failed';
                    
                    $user->update([
                        'verification_status' => $verificationStatus,
                        'verified_at' => $result['result'] === 'clear' ? now() : null,
                    ]);
                }

                return $this->successResponse(
                    $result,
                    'Verification status retrieved successfully'
                );
            }

            return $this->errorResponse(
                $result['error'] ?? 'Failed to get verification status',
                400,
                ['error_code' => $result['error_code'] ?? 'STATUS_FAILED']
            );

        } catch (\Throwable $e) {
            Log::error('Get identity verification status failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to get verification status', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | General Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Reenviar código de verificación (email o phone)
     *
     * @param Request $request
     * @return JsonResponse
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
                return $this->sendEmailVerification($request);
            } else {
                return $this->sendPhoneVerification($request);
            }

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Resend verification code failed', [
                'user_id' => $request->user()->id,
                'type' => $request->get('type'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to resend verification code', 500);
        }
    }
}
