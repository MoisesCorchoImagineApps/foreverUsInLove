<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\AuthenticationService;
use App\Domain\Auth\Services\FaceIdVerificationService;
use App\Infrastructure\External\TwilioService; // ⭐ NUEVO
use App\Infrastructure\External\SendGridService; // ⭐ NUEVO
use App\Infrastructure\External\FirebaseService; // ⭐ NUEVO (para push notifications)
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * LoginController - Maneja la autenticación de usuarios
 * 
 * Endpoints:
 * - POST /api/v1/auth/login - Iniciar sesión
 * - POST /api/v1/auth/logout - Cerrar sesión actual
 * - POST /api/v1/auth/logout-all - Cerrar todas las sesiones
 * - POST /api/v1/auth/refresh-token - Renovar token de acceso
 * - GET /api/v1/auth/sessions - Listar sesiones activas
 * - DELETE /api/v1/auth/sessions/{tokenId} - Cerrar sesión específica
 * 
 * @package App\Http\Controllers\Api\V1\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class LoginController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly FaceIdVerificationService $faceIdService,
        private readonly TwilioService $twilioService, // ⭐ NUEVO
        private readonly SendGridService $sendGridService, // ⭐ NUEVO
        private readonly FirebaseService $firebaseService, // ⭐ NUEVO
    ) {}

    /**
     * Authenticate user and return access token
     *
     * @param LoginRequest $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=423, description="Account locked"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->getLoginCredentials();
            $loginValue = $credentials['credentials']['login'] ?? null;

            // Buscar usuario para validaciones previas
            $user = $this->findUserByLogin($loginValue);

            // Validaciones de seguridad previas
            if ($user) {
                // Verificar si la cuenta está bloqueada
                if ($user->is_locked) {
                    $remainingTime = $user->locked_until->diffForHumans();
                    
                    Log::warning('Login attempt on locked account', [
                        'user_id' => $user->id,
                        'locked_until' => $user->locked_until,
                        'ip' => $request->ip(),
                    ]);

                    return $this->errorResponse(
                        "Account is temporarily locked. Try again {$remainingTime}.",
                        423,
                        ['locked_until' => $user->locked_until]
                    );
                }

                // Verificar si la cuenta está suspendida o baneada
                if (in_array($user->status, ['suspended', 'banned'])) {
                    Log::warning('Login attempt on suspended/banned account', [
                        'user_id' => $user->id,
                        'status' => $user->status,
                        'ip' => $request->ip(),
                    ]);

                    return $this->errorResponse(
                        'Your account has been ' . $user->status . '. Please contact support.',
                        403,
                        ['status' => $user->status]
                    );
                }

                // Verificar rate limiting
                if ($user->isRateLimited()) {
                    Log::warning('Rate limit exceeded on login', [
                        'user_id' => $user->id,
                        'ip' => $request->ip(),
                    ]);

                    return $this->errorResponse(
                        'Too many requests. Please try again later.',
                        429,
                        ['rate_limit_reset_at' => $user->rate_limit_reset_at]
                    );
                }
            }

            // Verificar si es login con Face ID
            if ($request->isFaceIdLogin()) {
                return $this->handleFaceIdLogin($request, $user);
            }

            // Login tradicional con email/phone/username y password
            $result = $this->authService->authenticate(
                $credentials['credentials'],
                $request->get('device_name', 'Unknown Device'),
                $credentials['device_info'] ?? []
            );

            $authenticatedUser = $result['user'];

            // Actualizar información de login
            $authenticatedUser->markAsLoggedIn($request->ip());
            $authenticatedUser->incrementRateLimit();

            // Registrar device token para push notifications
            if ($deviceToken = $request->get('device_token')) {
                $authenticatedUser->update(['device_token' => $deviceToken]);
            }

            // Enviar alerta de seguridad por email si es un nuevo dispositivo
            if ($this->isNewDevice($authenticatedUser, $credentials['device_info'] ?? [])) {
                $this->sendGridService->sendSecurityAlert(
                    $authenticatedUser,
                    'New device login',
                    'A new device was used to login to your account.',
                    [
                        'ip_address' => $request->ip(),
                        'device' => $request->get('device_name', 'Unknown'),
                        'location' => $this->getLocationFromIP($request->ip()),
                    ]
                );
            }

            // Enviar notificación push de login exitoso (opcional)
            if ($authenticatedUser->device_token && config('firebase.send_login_notification')) {
                $this->firebaseService->sendPushNotification(
                    $authenticatedUser,
                    'Login successful',
                    'You have successfully logged in to ForeverUsInLove',
                    ['type' => 'login_success']
                );
            }

            Log::info('User logged in successfully', [
                'user_id' => $authenticatedUser->id,
                'username' => $authenticatedUser->username,
                'login_type' => $request->getAuthenticationMethod(),
                'ip' => $request->ip(),
                'device' => $request->get('device_name'),
            ]);

            return $this->successResponse(
                $result,
                'Login successful. Welcome back!',
                200,
                [
                    'login_method' => $request->getAuthenticationMethod(),
                    'device_info' => $credentials['device_info'] ?? [],
                    'requires_verification' => !$authenticatedUser->is_verified,
                ]
            );

        } catch (AuthenticationException $e) {
            // Incrementar intentos fallidos si existe el usuario
            if ($user ?? null) {
                $user->incrementLoginAttempts();

                Log::warning('Login failed - Invalid credentials', [
                    'user_id' => $user->id,
                    'attempts' => $user->login_attempts,
                    'ip' => $request->ip(),
                ]);

                // Si se alcanzó el límite de intentos
                if ($user->is_locked) {
                    // Enviar alerta por SMS y Email
                    $this->twilioService->sendSecurityAlert(
                        $user,
                        "Your account has been temporarily locked due to multiple failed login attempts."
                    );

                    $this->sendGridService->sendSecurityAlert(
                        $user,
                        'Account Locked',
                        'Your account has been temporarily locked due to multiple failed login attempts.',
                        ['ip_address' => $request->ip()]
                    );
                }
            }

            return $this->unauthorizedResponse($e->getMessage());

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Login failed - Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse(
                'Login failed. Please try again later.',
                500
            );
        }
    }

    /**
     * Handle Face ID login
     *
     * @param LoginRequest $request
     * @param User|null $user
     * @return JsonResponse
     */
    private function handleFaceIdLogin(LoginRequest $request, ?User $user = null): JsonResponse
    {
        try {
            if (!$user) {
                $loginValue = $request->get('login');
                $user = $this->findUserByLogin($loginValue);

                if (!$user) {
                    return $this->unauthorizedResponse('Invalid credentials');
                }
            }

            // Verificar que el usuario tenga Face ID habilitado
            if (!$user->face_id_enabled) {
                Log::warning('Face ID login attempted but not enabled', [
                    'user_id' => $user->id,
                ]);

                return $this->errorResponse(
                    'Face ID is not enabled for this account. Please use password login.',
                    400,
                    ['error_code' => 'FACE_ID_NOT_ENABLED']
                );
            }

            // Verificar Face ID
            $faceIdData = $request->get('face_id_data');
            $deviceId = $request->get('device_info.device_id', 'unknown');

            $faceIdResult = $this->faceIdService->verifyFaceId(
                $user,
                ['biometric_data' => $faceIdData],
                $deviceId,
                'login',
                $request->getLoginContext()
            );

            if (!$faceIdResult['success']) {
                // Incrementar intentos fallidos
                $user->incrementLoginAttempts();

                Log::warning('Face ID verification failed during login', [
                    'user_id' => $user->id,
                    'confidence_score' => $faceIdResult['confidence_score'] ?? null,
                    'error_code' => $faceIdResult['error_code'] ?? null,
                ]);

                return $this->unauthorizedResponse(
                    $faceIdResult['message'] ?? 'Face ID verification failed',
                    ['error_code' => $faceIdResult['error_code'] ?? 'FACE_ID_FAILED']
                );
            }

            // Crear token de autenticación
            $token = $user->createToken(
                $request->get('device_name', 'Face ID Device'),
                ['*'],
                now()->addDays(7)
            );

            // Actualizar información de login
            $user->markAsLoggedIn($request->ip());
            $user->resetLoginAttempts();

            // Registrar device token
            if ($deviceToken = $request->get('device_token')) {
                $user->update(['device_token' => $deviceToken]);
            }

            Log::info('User logged in successfully with Face ID', [
                'user_id' => $user->id,
                'confidence_score' => $faceIdResult['confidence_score'],
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'user' => $user->load('profile', 'settings'),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7),
            ], 'Login successful with Face ID', 200, [
                'login_method' => 'face_id',
                'confidence_score' => $faceIdResult['confidence_score'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Face ID login failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Face ID login failed. Please use password login.',
                500
            );
        }
    }

    /**
     * Logout user from current device
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout from current device",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Logout successful")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokenId = $request->user()->currentAccessToken()?->id;

            $success = $this->authService->logout($user, $tokenId);

            if ($success) {
                Log::info('User logged out', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'token_id' => $tokenId,
                ]);

                return $this->successResponse(
                    ['logged_out' => true],
                    'Logout successful. See you soon!'
                );
            }

            return $this->errorResponse('Logout failed', 500);

        } catch (\Throwable $e) {
            Log::error('Logout failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Logout failed', 500);
        }
    }

    /**
     * Logout user from all devices
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     tags={"Authentication"},
     *     summary="Logout from all devices",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Logout from all devices successful")
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $success = $this->authService->logoutFromAllDevices($user);

            if ($success) {
                // Enviar notificación de seguridad
                $this->sendGridService->sendSecurityAlert(
                    $user,
                    'All sessions terminated',
                    'All active sessions for your account have been terminated.',
                    ['ip_address' => $request->ip()]
                );

                // Push notification
                if ($user->device_token) {
                    $this->firebaseService->sendPushNotification(
                        $user,
                        'Security Alert',
                        'All sessions have been logged out',
                        ['type' => 'logout_all']
                    );
                }

                Log::info('User logged out from all devices', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                return $this->successResponse(
                    ['logged_out_all' => true],
                    'Logged out from all devices successfully'
                );
            }

            return $this->errorResponse('Logout from all devices failed', 500);

        } catch (\Throwable $e) {
            Log::error('Logout from all devices failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Logout from all devices failed', 500);
        }
    }

    /**
     * Refresh authentication token
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Post(
     *     path="/api/v1/auth/refresh-token",
     *     tags={"Authentication"},
     *     summary="Refresh access token",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Token refreshed successfully")
     * )
     */
    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();
            
            // Eliminar token actual
            $currentToken->delete();
            
            // Crear nuevo token con la misma expiración
            $newToken = $user->createToken(
                $request->get('device_name', $currentToken->name ?? 'Unknown Device'),
                ['*'],
                now()->addDays(7)
            );

            Log::info('Token refreshed', [
                'user_id' => $user->id,
                'old_token_id' => $currentToken->id,
                'new_token_id' => $newToken->accessToken->id,
            ]);

            return $this->successResponse([
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7),
            ], 'Token refreshed successfully');

        } catch (\Throwable $e) {
            Log::error('Token refresh failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Token refresh failed', 500);
        }
    }

    /**
     * Get user's active sessions
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @OA\Get(
     *     path="/api/v1/auth/sessions",
     *     tags={"Authentication"},
     *     summary="List active sessions",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Sessions retrieved successfully")
     * )
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentTokenId = $request->user()->currentAccessToken()?->id;
            
            $sessions = $this->authService->getUserSessions($user);

            return $this->successResponse(
                [
                    'sessions' => $sessions,
                    'current_token_id' => $currentTokenId,
                ],
                'Active sessions retrieved successfully',
                200,
                ['total_sessions' => count($sessions)]
            );

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve sessions', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve sessions', 500);
        }
    }

    /**
     * Logout from specific session
     *
     * @param Request $request
     * @param int $tokenId
     * @return JsonResponse
     * 
     * @OA\Delete(
     *     path="/api/v1/auth/sessions/{tokenId}",
     *     tags={"Authentication"},
     *     summary="Logout from specific session",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="tokenId", in="path", required=true),
     *     @OA\Response(response=200, description="Session terminated successfully")
     * )
     */
    public function terminateSession(Request $request, int $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar que el token pertenece al usuario
            $token = $user->tokens()->find($tokenId);

            if (!$token) {
                return $this->errorResponse('Session not found', 404);
            }

            // No permitir cerrar la sesión actual
            $currentTokenId = $request->user()->currentAccessToken()?->id;
            if ($tokenId === $currentTokenId) {
                return $this->errorResponse(
                    'Cannot terminate current session. Use /logout instead.',
                    400
                );
            }

            // Eliminar token
            $token->delete();

            Log::info('Session terminated', [
                'user_id' => $user->id,
                'terminated_token_id' => $tokenId,
            ]);

            return $this->successResponse(
                ['terminated' => true, 'token_id' => $tokenId],
                'Session terminated successfully'
            );

        } catch (\Throwable $e) {
            Log::error('Session termination failed', [
                'user_id' => $request->user()->id ?? null,
                'token_id' => $tokenId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Session termination failed', 500);
        }
    }

    /**
     * Buscar usuario por email, phone o username
     *
     * @param string $loginValue
     * @return User|null
     */
    private function findUserByLogin(string $loginValue): ?User
    {
        return User::where('email', $loginValue)
            ->orWhere('phone', $loginValue)
            ->orWhere('username', $loginValue)
            ->first();
    }

    /**
     * Verificar si es un nuevo dispositivo
     *
     * @param User $user
     * @param array $deviceInfo
     * @return bool
     */
    private function isNewDevice(User $user, array $deviceInfo): bool
    {
        if (empty($deviceInfo['device_id'])) {
            return false;
        }

        // Verificar si existe un token con este device_id
        return !$user->tokens()
            ->where('name', 'LIKE', "%{$deviceInfo['device_id']}%")
            ->exists();
    }

    /**
     * Obtener ubicación aproximada desde IP
     *
     * @param string $ip
     * @return string
     */
    private function getLocationFromIP(string $ip): string
    {
        // Implementar con un servicio de geolocalización
        // Por ahora retornar placeholder
        return 'Unknown location';
    }
}
