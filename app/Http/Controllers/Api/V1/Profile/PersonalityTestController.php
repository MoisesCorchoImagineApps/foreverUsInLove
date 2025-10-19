<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\PersonalityTestRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Domain\Profile\Services\PersonalityTestService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PersonalityTestController
 * 
 * Controlador responsable de la gestión de tests de personalidad.
 * 
 * Endpoints:
 * - POST   /personality-tests/start       - Iniciar un test de personalidad
 * - POST   /personality-tests/submit      - Enviar respuestas del test
 * - POST   /personality-tests/complete    - Completar test y obtener resultados
 * - GET    /personality-tests/results     - Obtener resultados de tests completados
 * - GET    /personality-tests/compatibility/{userId} - Calcular compatibilidad con otro usuario
 * - GET    /personality-tests/stats       - Obtener estadísticas de tests
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class PersonalityTestController extends Controller
{
    use ApiResponseTrait;

    /**
     * Servicio de gestión de tests de personalidad
     */
    private PersonalityTestService $testService;

    /**
     * Constructor del controlador
     *
     * @param PersonalityTestService $testService
     */
    public function __construct(PersonalityTestService $testService)
    {
        $this->testService = $testService;
    }

    /**
     * Iniciar un test de personalidad
     *
     * @param PersonalityTestRequest $request
     * @return JsonResponse
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Test de personalidad iniciado exitosamente",
     *   "data": {
     *     "test_session": {...},
     *     "test_info": {...},
     *     "questions": [...],
     *     "progress": {...}
     *   }
     * }
     */
    public function start(PersonalityTestRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de realizar tests de personalidad',
                    403
                );
            }

            // Validar datos del test
            $validated = $request->validated();
            
            // Iniciar test
            $result = $this->testService->startPersonalityTest(
                $user,
                $validated['test_type'],
                $validated['context'] ?? []
            );

            Log::info('Personality test started', [
                'user_id' => $user->id,
                'test_type' => $validated['test_type'],
                'test_session_id' => $result['test_session']->id ?? null
            ]);

            return $this->successResponse(
                $result,
                $result['message'],
                201
            );

        } catch (Exception $e) {
            Log::error('Personality test start failed', [
                'user_id' => $request->user()->id ?? null,
                'test_type' => $request->input('test_type'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al iniciar el test: ' . $e->getMessage(),
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Enviar respuestas del test
     *
     * @param PersonalityTestRequest $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Respuestas procesadas exitosamente",
     *   "data": {
     *     "progress": {...},
     *     "next_questions": [...],
     *     "is_complete": false
     *   }
     * }
     */
    public function submit(PersonalityTestRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Validar datos
            $validated = $request->validated();
            
            // Enviar respuestas
            $result = $this->testService->submitTestAnswers(
                $validated['test_session_id'],
                $validated['answers'],
                $user
            );

            Log::info('Personality test answers submitted', [
                'user_id' => $user->id,
                'test_session_id' => $validated['test_session_id'],
                'answers_count' => count($validated['answers']),
                'progress_percentage' => $result['progress']['percentage'] ?? 0
            ]);

            return $this->successResponse(
                $result,
                $result['message']
            );

        } catch (Exception $e) {
            Log::error('Personality test answers submission failed', [
                'user_id' => $request->user()->id ?? null,
                'test_session_id' => $request->input('test_session_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al procesar respuestas: ' . $e->getMessage(),
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Completar test y obtener resultados
     *
     * @param PersonalityTestRequest $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Test completado exitosamente",
     *   "data": {
     *     "test_result": {...},
     *     "personality_profile": {...},
     *     "dimension_scores": {...},
     *     "insights": {...}
     *   }
     * }
     */
    public function complete(PersonalityTestRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Validar datos
            $validated = $request->validated();
            
            // Obtener sesión del test
            $testSession = $user->personalityTests()->find($validated['test_session_id']);
            
            if (!$testSession) {
                return $this->errorResponse(
                    'Sesión de test no encontrada',
                    404
                );
            }

            // Completar test
            $result = $this->testService->completeTest(
                $testSession,
                $user
            );

            Log::info('Personality test completed', [
                'user_id' => $user->id,
                'test_session_id' => $testSession->id,
                'test_type' => $testSession->test_type,
                'result_id' => $result['test_result']->id ?? null
            ]);

            return $this->successResponse(
                $result,
                $result['message']
            );

        } catch (Exception $e) {
            Log::error('Personality test completion failed', [
                'user_id' => $request->user()->id ?? null,
                'test_session_id' => $request->input('test_session_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al completar el test: ' . $e->getMessage(),
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Obtener resultados de tests completados
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "completed_tests": [...],
     *     "personality_summary": {...},
     *     "insights": {...}
     *   }
     * }
     */
    public function results(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Validar parámetros opcionales
            $validated = $request->validate([
                'test_type' => 'nullable|string|in:big_five,mbti,love_language,attachment_style,dating_persona'
            ]);

            // Obtener resultados
            $testType = $validated['test_type'] ?? null;
            
            if ($testType) {
                // Resultados de un tipo específico
                $results = $user->personalityTests()
                    ->where('test_type', $testType)
                    ->where('status', 'completed')
                    ->with('result')
                    ->latest()
                    ->get();
            } else {
                // Todos los resultados
                $results = $user->personalityTests()
                    ->where('status', 'completed')
                    ->with('result')
                    ->latest()
                    ->get();
            }

            Log::info('Personality test results retrieved', [
                'user_id' => $user->id,
                'test_type' => $testType,
                'results_count' => $results->count()
            ]);

            return $this->successResponse([
                'completed_tests' => $results,
                'total_tests' => $results->count(),
                'test_types' => $results->pluck('test_type')->unique()->values()
            ], 'Resultados obtenidos exitosamente');

        } catch (Exception $e) {
            Log::error('Personality test results retrieval failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al obtener resultados',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Calcular compatibilidad con otro usuario
     *
     * @param Request $request
     * @param int $targetUserId
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "overall_compatibility": 85.5,
     *     "test_scores": {...},
     *     "insights": {...},
     *     "strengths": [...],
     *     "challenges": [...]
     *   }
     * }
     */
    public function compatibility(Request $request, int $targetUserId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que no sea el mismo usuario
            if ($user->id === $targetUserId) {
                return $this->errorResponse(
                    'No puedes calcular compatibilidad contigo mismo',
                    400
                );
            }

            // Obtener usuario objetivo
            $targetUser = User::find($targetUserId);
            
            if (!$targetUser) {
                return $this->errorResponse(
                    'Usuario no encontrado',
                    404
                );
            }

            // Calcular compatibilidad
            $result = $this->testService->calculatePersonalityCompatibility(
                $user,
                $targetUser
            );

            Log::info('Personality compatibility calculated', [
                'user_id' => $user->id,
                'target_user_id' => $targetUserId,
                'overall_compatibility' => $result['overall_compatibility'] ?? 0
            ]);

            return $this->successResponse(
                $result,
                'Compatibilidad calculada exitosamente'
            );

        } catch (Exception $e) {
            Log::error('Personality compatibility calculation failed', [
                'user_id' => $request->user()->id ?? null,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al calcular compatibilidad',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Obtener estadísticas de tests
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "completed_tests": {...},
     *     "available_tests": {...},
     *     "personality_summary": {...},
     *     "engagement_metrics": {...}
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Obtener estadísticas
            $stats = $this->testService->getPersonalityStatistics($user->id);

            Log::info('Personality test statistics retrieved', [
                'user_id' => $user->id,
                'completed_tests_count' => $stats['completed_tests']['count'] ?? 0
            ]);

            return $this->successResponse(
                $stats,
                'Estadísticas obtenidas exitosamente'
            );

        } catch (Exception $e) {
            Log::error('Personality test statistics retrieval failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al obtener estadísticas',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }
}