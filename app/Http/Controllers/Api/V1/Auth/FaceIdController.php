<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\FaceIdVerificationService;
use App\Infrastructure\External\TwilioService; // ⭐ NUEVO
use App\Infrastructure\External\SendGridService; // ⭐ NUEVO
use App\Infrastructure\External\FirebaseService; // ⭐ NUEVO
use App\Infrastructure\External\TensorFlowService; // ⭐ NUEVO (para Face Detection)
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FaceIdRequest;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * FaceIdController - Maneja operaciones de autenticación biométrica Face ID
 * 
 * Endpoints:
 * - POST /api/v1/auth/faceid/register - Registrar plantilla Face ID
 * - POST /api/v1/auth/faceid/verify - Verificar identidad con Face ID
 * - POST /api/v1/auth/faceid/fallback - Verificar con PIN de fallback
 * - PUT /api/v1/auth/faceid/update - Actualizar plantilla biométrica
 * - DELETE /api/v1/auth/faceid/remove - Eliminar plantillas Face ID
 * - GET /api/v1/auth/faceid/statistics - Estadísticas de uso Face ID
 * - POST /api/v1/auth/faceid/test-quality - Probar calidad de imagen facial
 * - GET /api/v1/auth/faceid/devices - Listar dispositivos con Face ID
 * - DELETE /api/v1/auth/faceid/devices/{deviceId} - Eliminar Face ID de dispositivo
 * 
 * @package App\Http\Controllers\Api\V1\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class FaceIdController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly FaceIdVerificationService $faceIdService,
        private readonly TwilioService $twilioService, // ⭐ NUEVO
        private readonly SendGridService $sendGridService, // ⭐ NUEVO
        private readonly FirebaseService $firebaseService, // ⭐ NUEVO
        private readonly TensorFlowService $tensorFlowService, // ⭐ NUEVO
    ) {
        // Todos los endpoints requieren autenticación
        $this->middleware('auth:sanctum');
    }

    /**
     * Register Face ID template
     *
     * @param FaceIdRequest $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/faceid/register",
     *     tags={"Face ID"},
     *     summary="Register Face ID template",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=201, description="Face ID registered successfully")
     * )
     */
    public function register(FaceIdRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->getFaceIdOperationData();
            $user = $request->user();

            // Verificar si ya tiene Face ID registrado
            if ($user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is already registered. Please update or remove existing Face ID first.',
                    400,
                    ['error_code' => 'FACE_ID_ALREADY_REGISTERED']
                );
            }

            // ⭐ Validar calidad de imagen facial con TensorFlow
            if (config('faceid.validate_quality', true)) {
                $qualityResult = $this->tensorFlowService->validateFaceQuality(
                    $data['face_template']['biometric_data'] ?? null
                );

                if (!$qualityResult['success'] || $qualityResult['quality_score'] < 0.7) {
                    DB::rollBack();
                    
                    Log::warning('Face ID registration failed - Poor image quality', [
                        'user_id' => $user->id,
                        'quality_score' => $qualityResult['quality_score'] ?? 0,
                    ]);

                    return $this->errorResponse(
                        'Face image quality is too low. Please try again with better lighting.',
                        400,
                        [
                            'error_code' => 'POOR_IMAGE_QUALITY',
                            'quality_score' => $qualityResult['quality_score'] ?? 0,
                            'recommendations' => $qualityResult['recommendations'] ?? [],
                        ]
                    );
                }
            }

            // Registrar plantilla biométrica
            $result = $this->faceIdService->registerFaceTemplate(
                $user,
                $data['face_template'],
                $data['device_id'],
                $data['metadata'] ?? []
            );

            if ($result['success']) {
                DB::commit();

                // ⭐ Enviar notificación de seguridad por Email
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Face ID Enabled',
                    'Face ID authentication has been enabled for your account.',
                    [
                        'device_id' => $data['device_id'],
                        'ip_address' => $request->ip(),
                        'timestamp' => now()->format('Y-m-d H:i:s'),
                    ]
                );

                // ⭐ Enviar alerta por SMS (opcional)
                if (config('faceid.send_sms_alert', true) && $user->phone) {
                    $this->twilioService->sendSecurityAlert(
                        $user,
                        "Face ID has been enabled for your ForeverUsInLove account. If this wasn't you, secure your account immediately."
                    );
                }

                // ⭐ Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Face ID Enabled',
                        'Face ID authentication is now active on your account',
                        ['type' => 'security_alert', 'action' => 'face_id_enabled']
                    );
                }

                Log::info('Face ID template registered successfully', [
                    'user_id' => $user->id,
                    'template_id' => $result['template_id'],
                    'device_id' => $data['device_id'],
                ]);

                return $this->createdResponse(
                    $result,
                    'Face ID registered successfully! 🎉'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['message'] ?? 'Failed to register Face ID',
                400,
                ['error_code' => $result['error_code'] ?? 'REGISTRATION_FAILED']
            );

        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            
            Log::warning('Face ID registration validation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 400);

        } catch (RuntimeException $e) {
            DB::rollBack();
            
            Log::error('Face ID registration runtime error', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 500);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Face ID registration failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Face ID registration failed', 500);
        }
    }

    /**
     * Verify Face ID
     *
     * @param FaceIdRequest $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/faceid/verify",
     *     tags={"Face ID"},
     *     summary="Verify Face ID",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Face ID verified successfully")
     * )
     */
    public function verify(FaceIdRequest $request): JsonResponse
    {
        try {
            $data = $request->getFaceIdOperationData();
            $user = $request->user();

            // Verificar que tenga Face ID habilitado
            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            // ⭐ Detectar rostro con TensorFlow antes de verificar
            $faceDetection = $this->tensorFlowService->detectFace(
                $data['captured_face']['biometric_data'] ?? null
            );

            if (!$faceDetection['success'] || !$faceDetection['face_detected']) {
                Log::warning('Face ID verification failed - No face detected', [
                    'user_id' => $user->id,
                    'device_id' => $data['device_id'],
                ]);

                return $this->errorResponse(
                    'No face detected in the image. Please try again.',
                    400,
                    ['error_code' => 'NO_FACE_DETECTED']
                );
            }

            // Verificar Face ID
            $result = $this->faceIdService->verifyFaceId(
                $user,
                $data['captured_face'],
                $data['device_id'],
                $data['verification_type'],
                $data['context'] ?? []
            );

            if ($result['success']) {
                Log::info('Face ID verified successfully', [
                    'user_id' => $user->id,
                    'device_id' => $data['device_id'],
                    'verification_type' => $data['verification_type'],
                    'confidence_score' => $result['confidence_score'],
                ]);

                return $this->successResponse(
                    $result,
                    'Face ID verified successfully! ✓'
                );
            }

            // Verificación fallida - registrar intento
            Log::warning('Face ID verification failed', [
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
                'error_code' => $result['error_code'] ?? null,
                'confidence_score' => $result['confidence_score'] ?? null,
            ]);

            // ⭐ Si hay múltiples intentos fallidos, enviar alerta
            if (($result['failed_attempts'] ?? 0) >= 3) {
                // Enviar alerta por email
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Multiple Face ID Verification Failures',
                    'Multiple failed Face ID verification attempts detected on your account.',
                    [
                        'failed_attempts' => $result['failed_attempts'],
                        'device_id' => $data['device_id'],
                        'ip_address' => $request->ip(),
                    ]
                );

                // Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Security Alert',
                        'Multiple failed Face ID attempts detected',
                        ['type' => 'security_alert', 'action' => 'face_id_failures']
                    );
                }
            }

            return $this->errorResponse(
                $result['message'] ?? 'Face ID verification failed',
                401,
                ['error_code' => $result['error_code'] ?? 'verification_failed']
            );

        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);

        } catch (RuntimeException $e) {
            Log::error('Face ID verification runtime error', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 500);

        } catch (\Throwable $e) {
            Log::error('Face ID verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Face ID verification failed', 500);
        }
    }

    /**
     * Verify using biometric fallback PIN
     *
     * @param FaceIdRequest $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/faceid/fallback",
     *     tags={"Face ID"},
     *     summary="Verify with biometric fallback PIN",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Fallback PIN verified successfully")
     * )
     */
    public function verifyFallback(FaceIdRequest $request): JsonResponse
    {
        try {
            $data = $request->getFaceIdOperationData();
            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            $result = $this->faceIdService->verifyBiometricFallback(
                $user,
                $data['fallback_pin'],
                $data['device_id']
            );

            if ($result['success']) {
                Log::info('Biometric fallback verified successfully', [
                    'user_id' => $user->id,
                    'device_id' => $data['device_id'],
                ]);

                return $this->successResponse(
                    $result,
                    'Biometric fallback verified successfully! ✓'
                );
            }

            return $this->errorResponse(
                $result['message'] ?? 'Fallback PIN verification failed',
                401,
                ['error_code' => $result['error_code'] ?? 'invalid_pin']
            );

        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 500);

        } catch (\Throwable $e) {
            Log::error('Biometric fallback verification failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Fallback verification failed', 500);
        }
    }

    /**
     * Update Face ID template
     *
     * @param FaceIdRequest $request
     * @return JsonResponse
     * 
     * @OA\Put(
     *     path="/api/v1/auth/faceid/update",
     *     tags={"Face ID"},
     *     summary="Update Face ID template",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Face ID updated successfully")
     * )
     */
    public function update(FaceIdRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->getFaceIdOperationData();
            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account. Please register first.',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            // ⭐ Validar calidad de nueva imagen
            if (config('faceid.validate_quality', true)) {
                $qualityResult = $this->tensorFlowService->validateFaceQuality(
                    $data['biometric_data'] ?? null
                );

                if (!$qualityResult['success'] || $qualityResult['quality_score'] < 0.7) {
                    DB::rollBack();
                    
                    return $this->errorResponse(
                        'Face image quality is too low. Please try again.',
                        400,
                        [
                            'error_code' => 'POOR_IMAGE_QUALITY',
                            'quality_score' => $qualityResult['quality_score'] ?? 0,
                        ]
                    );
                }
            }

            // Actualizar plantilla (re-registrar)
            $result = $this->faceIdService->registerFaceTemplate(
                $user,
                [
                    'biometric_data' => $data['biometric_data'] ?? null,
                    'quality_score' => $data['quality_score'] ?? 0.9,
                ],
                $data['device_id'],
                $data['metadata'] ?? []
            );

            if ($result['success']) {
                DB::commit();

                // ⭐ Enviar notificación de actualización
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Face ID Updated',
                    'Your Face ID template has been updated.',
                    [
                        'device_id' => $data['device_id'],
                        'ip_address' => $request->ip(),
                    ]
                );

                // Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Face ID Updated',
                        'Your Face ID template has been updated successfully',
                        ['type' => 'security_alert', 'action' => 'face_id_updated']
                    );
                }

                Log::info('Face ID template updated', [
                    'user_id' => $user->id,
                    'template_id' => $data['template_id'] ?? null,
                ]);

                return $this->successResponse(
                    $result,
                    'Face ID updated successfully! ✓'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['message'] ?? 'Failed to update Face ID',
                400
            );

        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400);

        } catch (RuntimeException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Face ID update failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Face ID update failed', 500);
        }
    }

    /**
     * Remove Face ID templates
     *
     * @param FaceIdRequest $request
     * @return JsonResponse
     * 
     * @OA\Delete(
     *     path="/api/v1/auth/faceid/remove",
     *     tags={"Face ID"},
     *     summary="Remove Face ID templates",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Face ID templates removed successfully")
     * )
     */
    public function remove(FaceIdRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->getFaceIdOperationData();
            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            $result = $this->faceIdService->removeFaceTemplates(
                $user,
                $data['template_id'] ?? null,
                $data['remove_device_only'] ? $data['device_id'] : null
            );

            if ($result['success']) {
                DB::commit();

                // ⭐ Enviar alertas de seguridad
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Face ID Disabled',
                    'Face ID authentication has been disabled for your account.',
                    [
                        'removed_count' => $result['removed_count'],
                        'ip_address' => $request->ip(),
                    ]
                );

                // SMS alert
                if ($user->phone) {
                    $this->twilioService->sendSecurityAlert(
                        $user,
                        "Face ID has been disabled for your ForeverUsInLove account. If this wasn't you, secure your account immediately."
                    );
                }

                // Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Face ID Disabled',
                        'Face ID has been removed from your account',
                        ['type' => 'security_alert', 'action' => 'face_id_removed']
                    );
                }

                Log::info('Face ID templates removed', [
                    'user_id' => $user->id,
                    'removed_count' => $result['removed_count'],
                ]);

                return $this->successResponse(
                    $result,
                    'Face ID templates removed successfully'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['message'] ?? 'Failed to remove Face ID templates',
                400
            );

        } catch (RuntimeException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Face ID removal failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Face ID removal failed', 500);
        }
    }

    /**
     * Get Face ID statistics
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Get(
     *     path="/api/v1/auth/faceid/statistics",
     *     tags={"Face ID"},
     *     summary="Get Face ID usage statistics",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Statistics retrieved successfully")
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            $statistics = $this->faceIdService->getFaceIdStatistics($user);

            return $this->successResponse(
                $statistics,
                'Face ID statistics retrieved successfully'
            );

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve Face ID statistics', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve statistics', 500);
        }
    }

    /**
     * ⭐ NUEVO - Test face image quality
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/faceid/test-quality",
     *     tags={"Face ID"},
     *     summary="Test quality of facial image",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Quality test completed")
     * )
     */
    public function testQuality(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string', // Base64 encoded
        ]);

        try {
            $user = $request->user();

            // ⭐ Validar calidad con TensorFlow
            $qualityResult = $this->tensorFlowService->validateFaceQuality(
                $request->get('image')
            );

            // ⭐ Detectar rostro
            $faceDetection = $this->tensorFlowService->detectFace(
                $request->get('image')
            );

            $result = [
                'quality_score' => $qualityResult['quality_score'] ?? 0,
                'face_detected' => $faceDetection['face_detected'] ?? false,
                'face_count' => $faceDetection['face_count'] ?? 0,
                'is_acceptable' => ($qualityResult['quality_score'] ?? 0) >= 0.7 && ($faceDetection['face_detected'] ?? false),
                'recommendations' => $qualityResult['recommendations'] ?? [],
                'details' => [
                    'lighting' => $qualityResult['lighting'] ?? 'unknown',
                    'sharpness' => $qualityResult['sharpness'] ?? 'unknown',
                    'angle' => $qualityResult['angle'] ?? 'unknown',
                    'face_size' => $faceDetection['face_size'] ?? 'unknown',
                ],
            ];

            Log::info('Face quality test performed', [
                'user_id' => $user->id,
                'quality_score' => $result['quality_score'],
                'face_detected' => $result['face_detected'],
            ]);

            return $this->successResponse(
                $result,
                $result['is_acceptable'] 
                    ? 'Image quality is acceptable for Face ID registration' 
                    : 'Image quality needs improvement'
            );

        } catch (\Throwable $e) {
            Log::error('Face quality test failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Quality test failed', 500);
        }
    }

    /**
     * ⭐ NUEVO - List devices with Face ID registered
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Get(
     *     path="/api/v1/auth/faceid/devices",
     *     tags={"Face ID"},
     *     summary="List devices with Face ID",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Devices listed successfully")
     * )
     */
    public function listDevices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            $devices = $this->faceIdService->getRegisteredDevices($user);

            return $this->successResponse(
                ['devices' => $devices],
                'Devices retrieved successfully',
                200,
                ['total_devices' => count($devices)]
            );

        } catch (\Throwable $e) {
            Log::error('Failed to list Face ID devices', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve devices', 500);
        }
    }

    /**
     * ⭐ NUEVO - Remove Face ID from specific device
     *
     * @param Request $request
     * @param string $deviceId
     * @return JsonResponse
     * 
     * @OA\Delete(
     *     path="/api/v1/auth/faceid/devices/{deviceId}",
     *     tags={"Face ID"},
     *     summary="Remove Face ID from device",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="deviceId", in="path", required=true),
     *     @OA\Response(response=200, description="Device removed successfully")
     * )
     */
    public function removeDevice(Request $request, string $deviceId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();

            if (!$user->face_id_enabled) {
                return $this->errorResponse(
                    'Face ID is not enabled for this account',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            $result = $this->faceIdService->removeFaceTemplates(
                $user,
                null,
                $deviceId
            );

            if ($result['success']) {
                DB::commit();

                // ⭐ Enviar notificación
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'Face ID Device Removed',
                    "Face ID has been removed from device: {$deviceId}",
                    [
                        'device_id' => $deviceId,
                        'ip_address' => $request->ip(),
                    ]
                );

                Log::info('Face ID removed from device', [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                ]);

                return $this->successResponse(
                    $result,
                    'Face ID removed from device successfully'
                );
            }

            DB::rollBack();
            return $this->errorResponse(
                $result['message'] ?? 'Failed to remove Face ID from device',
                400
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Failed to remove Face ID from device', [
                'user_id' => $request->user()->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to remove device', 500);
        }
    }
}
