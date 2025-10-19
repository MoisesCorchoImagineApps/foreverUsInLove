<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * PersonalityTestRequest - Validación para tests de personalidad
 * 
 * Este FormRequest maneja la validación de datos para la gestión de tests
 * de personalidad, incluyendo inicio de tests, envío de respuestas y
 * configuración de tests personalizados.
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class PersonalityTestRequest extends FormRequest
{
    /**
     * Tipos de tests de personalidad disponibles (sincronizado con PersonalityTestService)
     */
    private const PERSONALITY_TESTS = [
        'big_five' => [
            'name' => 'Big Five (OCEAN)',
            'questions' => 50,
            'duration_minutes' => 15,
            'dimensions' => ['openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism']
        ],
        'mbti' => [
            'name' => 'Myers-Briggs Type Indicator',
            'questions' => 60,
            'duration_minutes' => 20,
            'dimensions' => ['introversion_extraversion', 'sensing_intuition', 'thinking_feeling', 'judging_perceiving']
        ],
        'love_language' => [
            'name' => 'Lenguajes del Amor',
            'questions' => 30,
            'duration_minutes' => 10,
            'dimensions' => ['words_affirmation', 'quality_time', 'physical_touch', 'acts_service', 'receiving_gifts']
        ],
        'attachment_style' => [
            'name' => 'Estilo de Apego',
            'questions' => 36,
            'duration_minutes' => 12,
            'dimensions' => ['secure', 'anxious', 'avoidant', 'disorganized']
        ],
        'dating_persona' => [
            'name' => 'Persona de Citas',
            'questions' => 40,
            'duration_minutes' => 15,
            'dimensions' => ['romantic', 'adventurous', 'intellectual', 'social', 'traditional', 'spontaneous']
        ],
        'enneagram' => [
            'name' => 'Eneagrama',
            'questions' => 144,
            'duration_minutes' => 30,
            'dimensions' => ['type_1', 'type_2', 'type_3', 'type_4', 'type_5', 'type_6', 'type_7', 'type_8', 'type_9']
        ]
    ];

    /**
     * Configuración de scoring (sincronizado con PersonalityTestService)
     */
    private const SCORING_CONFIG = [
        'min_score' => 0,
        'max_score' => 100,
        'scale_points' => 7, // Escala Likert de 1-7
        'required_completion_rate' => 0.8, // 80% de respuestas mínimas
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        // El usuario debe estar autenticado
        if (!$user) {
            return false;
        }

        // Verificar límites de tests por usuario (máximo 3 tests activos simultáneos)
        $activeTestsCount = $user->personalityTests()
            ->where('status', 'in_progress')
            ->count();
        
        if ($activeTestsCount >= 3) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $action = $this->route('action') ?? $this->input('action', 'start');

        return match($action) {
            'start' => $this->getStartTestRules(),
            'submit' => $this->getSubmitAnswersRules(),
            'complete' => $this->getCompleteTestRules(),
            'retake' => $this->getRetakeTestRules(),
            'configure' => $this->getConfigureTestRules(),
            default => $this->getDefaultRules()
        };
    }

    /**
     * Get validation rules for starting a test.
     */
    private function getStartTestRules(): array
    {
        return [
            'test_type' => [
                'required',
                'string',
                Rule::in(array_keys(self::PERSONALITY_TESTS))
            ],
            'test_version' => ['nullable', 'string', 'max:20'],
            'context' => ['nullable', 'array'],
            'context.*' => ['string', 'max:255'],
            'preferences' => ['nullable', 'array'],
            'preferences.language' => ['nullable', 'string', 'in:es,en'],
            'preferences.difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'preferences.estimated_time' => ['nullable', 'integer', 'min:5', 'max:60'],
        ];
    }

    /**
     * Get validation rules for submitting answers.
     */
    private function getSubmitAnswersRules(): array
    {
        return [
            'test_session_id' => ['required', 'integer', 'exists:personality_tests,id'],
            'answers' => [
                'required',
                'array',
                'min:1',
                'max:50' // Máximo 50 respuestas por batch
            ],
            'answers.*.question_id' => [
                'required',
                'integer',
                'min:1'
            ],
            'answers.*.answer' => [
                'required',
                'integer',
                'min:1',
                'max:7' // Escala Likert 1-7
            ],
            'answers.*.response_time' => [
                'nullable',
                'integer',
                'min:1',
                'max:300' // Máximo 5 minutos por pregunta
            ],
            'answers.*.confidence' => [
                'nullable',
                'integer',
                'min:1',
                'max:5' // Escala de confianza 1-5
            ],
            'batch_info' => ['nullable', 'array'],
            'batch_info.batch_number' => ['nullable', 'integer', 'min:1'],
            'batch_info.total_batches' => ['nullable', 'integer', 'min:1'],
            'session_metadata' => ['nullable', 'array'],
            'session_metadata.time_spent' => ['nullable', 'integer', 'min:0'],
            'session_metadata.pauses_count' => ['nullable', 'integer', 'min:0'],
            'session_metadata.device_type' => ['nullable', 'string', 'in:mobile,tablet,desktop'],
        ];
    }

    /**
     * Get validation rules for completing a test.
     */
    private function getCompleteTestRules(): array
    {
        return [
            'test_session_id' => ['required', 'integer', 'exists:personality_tests,id'],
            'final_answers' => ['nullable', 'array'],
            'completion_metadata' => ['nullable', 'array'],
            'completion_metadata.total_time' => ['nullable', 'integer', 'min:1'],
            'completion_metadata.interruptions' => ['nullable', 'integer', 'min:0'],
            'completion_metadata.review_attempts' => ['nullable', 'integer', 'min:0'],
            'sharing_preferences' => ['nullable', 'array'],
            'sharing_preferences.is_public' => ['nullable', 'boolean'],
            'sharing_preferences.use_for_matching' => ['nullable', 'boolean'],
            'sharing_preferences.show_in_profile' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get validation rules for retaking a test.
     */
    private function getRetakeTestRules(): array
    {
        return [
            'original_test_id' => ['required', 'integer', 'exists:personality_tests,id'],
            'reason' => [
                'required',
                'string',
                Rule::in(['inaccurate_results', 'changed_personality', 'want_improvement', 'technical_issue'])
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get validation rules for configuring a test.
     */
    private function getConfigureTestRules(): array
    {
        return [
            'test_type' => [
                'required',
                'string',
                Rule::in(array_keys(self::PERSONALITY_TESTS))
            ],
            'custom_settings' => ['nullable', 'array'],
            'custom_settings.question_count' => ['nullable', 'integer', 'min:10', 'max:200'],
            'custom_settings.time_limit' => ['nullable', 'integer', 'min:5', 'max:120'],
            'custom_settings.difficulty' => ['nullable', 'string', 'in:easy,medium,hard,adaptive'],
            'custom_settings.language' => ['nullable', 'string', 'in:es,en'],
            'custom_settings.include_insights' => ['nullable', 'boolean'],
            'custom_settings.detailed_results' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get default validation rules.
     */
    private function getDefaultRules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['start', 'submit', 'complete', 'retake', 'configure'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Mensajes para inicio de test
            'test_type.required' => 'Debes seleccionar un tipo de test de personalidad.',
            'test_type.in' => 'El tipo de test seleccionado no es válido.',
            'test_version.max' => 'La versión del test no puede exceder los 20 caracteres.',
            
            // Mensajes para envío de respuestas
            'test_session_id.required' => 'El ID de sesión del test es requerido.',
            'test_session_id.exists' => 'La sesión del test no existe o no es válida.',
            'answers.required' => 'Debes proporcionar al menos una respuesta.',
            'answers.max' => 'No puedes enviar más de 50 respuestas a la vez.',
            'answers.*.question_id.required' => 'El ID de pregunta es requerido.',
            'answers.*.question_id.integer' => 'El ID de pregunta debe ser un número entero.',
            'answers.*.answer.required' => 'La respuesta es requerida.',
            'answers.*.answer.min' => 'La respuesta debe ser al menos 1.',
            'answers.*.answer.max' => 'La respuesta no puede ser mayor a 7.',
            'answers.*.response_time.max' => 'El tiempo de respuesta no puede exceder 5 minutos.',
            'answers.*.confidence.max' => 'El nivel de confianza no puede exceder 5.',
            
            // Mensajes para completar test
            'final_answers.array' => 'Las respuestas finales deben ser un array.',
            'completion_metadata.total_time.min' => 'El tiempo total debe ser al menos 1 segundo.',
            'completion_metadata.interruptions.min' => 'El número de interrupciones no puede ser negativo.',
            
            // Mensajes para retomar test
            'original_test_id.required' => 'El ID del test original es requerido.',
            'original_test_id.exists' => 'El test original no existe.',
            'reason.required' => 'Debes especificar la razón para repetir el test.',
            'reason.in' => 'La razón especificada no es válida.',
            'notes.max' => 'Las notas no pueden exceder los 500 caracteres.',
            
            // Mensajes para configuración
            'custom_settings.question_count.min' => 'El número mínimo de preguntas es 10.',
            'custom_settings.question_count.max' => 'El número máximo de preguntas es 200.',
            'custom_settings.time_limit.min' => 'El límite mínimo de tiempo es 5 minutos.',
            'custom_settings.time_limit.max' => 'El límite máximo de tiempo es 120 minutos.',
            
            // Mensajes generales
            'action.required' => 'La acción es requerida.',
            'action.in' => 'La acción especificada no es válida.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'test_type' => 'tipo de test',
            'test_version' => 'versión del test',
            'test_session_id' => 'ID de sesión',
            'answers' => 'respuestas',
            'question_id' => 'ID de pregunta',
            'answer' => 'respuesta',
            'response_time' => 'tiempo de respuesta',
            'confidence' => 'confianza',
            'batch_info' => 'información del lote',
            'session_metadata' => 'metadatos de sesión',
            'completion_metadata' => 'metadatos de finalización',
            'sharing_preferences' => 'preferencias de compartir',
            'custom_settings' => 'configuración personalizada',
            'original_test_id' => 'ID del test original',
            'reason' => 'razón',
            'notes' => 'notas',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $action = $this->route('action') ?? $this->input('action', 'start');

            if (!$user) {
                return;
            }

            // Validaciones específicas por acción
            match($action) {
                'start' => $this->validateStartTest($validator, $user),
                'submit' => $this->validateSubmitAnswers($validator, $user),
                'complete' => $this->validateCompleteTest($validator, $user),
                'retake' => $this->validateRetakeTest($validator, $user),
                'configure' => $this->validateConfigureTest($validator, $user),
                default => null
            };
        });
    }

    /**
     * Validar inicio de test.
     */
    private function validateStartTest($validator, $user): void
    {
        $testType = $this->input('test_type');
        
        if (!$testType) {
            return;
        }

        // Verificar si ya tiene un test activo de este tipo
        $activeTest = $user->personalityTests()
            ->where('test_type', $testType)
            ->where('status', 'in_progress')
            ->first();

        if ($activeTest) {
            $validator->errors()->add('test_type', 
                'Ya tienes un test de ' . self::PERSONALITY_TESTS[$testType]['name'] . ' en progreso.'
            );
        }

        // Verificar límite de tests completados recientemente
        $recentCompletedTests = $user->personalityTests()
            ->where('test_type', $testType)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        if ($recentCompletedTests > 0) {
            $validator->errors()->add('test_type', 
                'Ya completaste este test recientemente. Puedes repetirlo después de 7 días.'
            );
        }
    }

    /**
     * Validar envío de respuestas.
     */
    private function validateSubmitAnswers($validator, $user): void
    {
        $testSessionId = $this->input('test_session_id');
        $answers = $this->input('answers', []);

        if (!$testSessionId) {
            return;
        }

        // Verificar que la sesión pertenece al usuario
        $testSession = $user->personalityTests()->find($testSessionId);
        
        if (!$testSession) {
            $validator->errors()->add('test_session_id', 
                'La sesión del test no existe o no te pertenece.'
            );
            return;
        }

        // Verificar que el test no esté completado
        if ($testSession->status === 'completed') {
            $validator->errors()->add('test_session_id', 
                'Este test ya ha sido completado.'
            );
        }

        // Verificar que el test no haya expirado
        if ($testSession->expires_at && $testSession->expires_at->isPast()) {
            $validator->errors()->add('test_session_id', 
                'La sesión del test ha expirado.'
            );
        }

        // Validar respuestas específicas por tipo de test
        $this->validateAnswersByTestType($validator, $testSession->test_type, $answers);
    }

    /**
     * Validar completar test.
     */
    private function validateCompleteTest($validator, $user): void
    {
        $testSessionId = $this->input('test_session_id');

        if (!$testSessionId) {
            return;
        }

        $testSession = $user->personalityTests()->find($testSessionId);
        
        if (!$testSession) {
            $validator->errors()->add('test_session_id', 
                'La sesión del test no existe o no te pertenece.'
            );
            return;
        }

        // Verificar que el test esté en progreso
        if ($testSession->status !== 'in_progress') {
            $validator->errors()->add('test_session_id', 
                'Este test no está en progreso.'
            );
        }

        // Verificar tasa de finalización mínima
        $completionRate = $testSession->completion_percentage / 100;
        
        if ($completionRate < self::SCORING_CONFIG['required_completion_rate']) {
            $validator->errors()->add('test_session_id', 
                'Debes completar al menos el ' . (self::SCORING_CONFIG['required_completion_rate'] * 100) . 
                '% del test para finalizarlo.'
            );
        }
    }

    /**
     * Validar retomar test.
     */
    private function validateRetakeTest($validator, $user): void
    {
        $originalTestId = $this->input('original_test_id');

        if (!$originalTestId) {
            return;
        }

        $originalTest = $user->personalityTests()->find($originalTestId);
        
        if (!$originalTest) {
            $validator->errors()->add('original_test_id', 
                'El test original no existe o no te pertenece.'
            );
            return;
        }

        // Verificar que el test esté completado
        if ($originalTest->status !== 'completed') {
            $validator->errors()->add('original_test_id', 
                'Solo puedes repetir tests completados.'
            );
        }

        // Verificar límite de repeticiones
        if ($originalTest->retake_count >= 3) {
            $validator->errors()->add('original_test_id', 
                'Has alcanzado el límite máximo de repeticiones para este test.'
            );
        }
    }

    /**
     * Validar configuración de test.
     */
    private function validateConfigureTest($validator, $user): void
    {
        $testType = $this->input('test_type');
        $customSettings = $this->input('custom_settings', []);

        if (!$testType) {
            return;
        }

        // Validar configuración personalizada
        if (isset($customSettings['question_count'])) {
            $testConfig = self::PERSONALITY_TESTS[$testType];
            $defaultQuestions = $testConfig['questions'];
            $customQuestions = $customSettings['question_count'];

            if ($customQuestions < ($defaultQuestions * 0.5)) {
                $validator->errors()->add('custom_settings.question_count', 
                    'El número de preguntas no puede ser menor al 50% del test estándar.'
                );
            }

            if ($customQuestions > ($defaultQuestions * 2)) {
                $validator->errors()->add('custom_settings.question_count', 
                    'El número de preguntas no puede ser mayor al 200% del test estándar.'
                );
            }
        }
    }

    /**
     * Validar respuestas por tipo de test.
     */
    private function validateAnswersByTestType($validator, string $testType, array $answers): void
    {
        $testConfig = self::PERSONALITY_TESTS[$testType] ?? null;
        
        if (!$testConfig) {
            return;
        }

        // Validar que las respuestas estén en el rango correcto
        foreach ($answers as $index => $answer) {
            if (!isset($answer['answer'])) {
                continue;
            }

            $answerValue = $answer['answer'];
            
            // Validar escala Likert (1-7)
            if ($answerValue < 1 || $answerValue > 7) {
                $validator->errors()->add("answers.{$index}.answer", 
                    'La respuesta debe estar en la escala de 1 a 7.'
                );
            }
        }

        // Validar consistencia de respuestas (detección de patrones sospechosos)
        $this->validateAnswerConsistency($validator, $answers);
    }

    /**
     * Validar consistencia de respuestas.
     */
    private function validateAnswerConsistency($validator, array $answers): void
    {
        if (count($answers) < 5) {
            return;
        }

        $answerValues = array_column($answers, 'answer');
        
        // Detectar respuestas idénticas (posible spam)
        $uniqueAnswers = array_unique($answerValues);
        
        if (count($uniqueAnswers) === 1) {
            $validator->errors()->add('answers', 
                'Las respuestas parecen inconsistentes. Por favor, responde honestamente.'
            );
        }

        // Detectar patrones extremos (demasiados 1s o 7s)
        $extremeAnswers = array_filter($answerValues, fn($value) => $value === 1 || $value === 7);
        $extremePercentage = count($extremeAnswers) / count($answerValues);

        if ($extremePercentage > 0.8) {
            $validator->errors()->add('answers', 
                'Las respuestas parecen muy extremas. Considera respuestas más moderadas.'
            );
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // Limpiar y formatear datos
        if (isset($data['answers']) && is_array($data['answers'])) {
            foreach ($data['answers'] as $index => $answer) {
                // Asegurar que answer sea entero
                if (isset($answer['answer'])) {
                    $data['answers'][$index]['answer'] = (int) $answer['answer'];
                }
                
                // Limpiar campos opcionales
                if (isset($answer['response_time'])) {
                    $data['answers'][$index]['response_time'] = (int) $answer['response_time'];
                }
                
                if (isset($answer['confidence'])) {
                    $data['answers'][$index]['confidence'] = (int) $answer['confidence'];
                }
            }
        }

        // Convertir valores booleanos
        $booleanFields = [
            'sharing_preferences.is_public',
            'sharing_preferences.use_for_matching',
            'sharing_preferences.show_in_profile',
            'custom_settings.include_insights',
            'custom_settings.detailed_results'
        ];

        foreach ($booleanFields as $field) {
            if (isset($data[explode('.', $field)[0]]) && is_array($data[explode('.', $field)[0]])) {
                $fieldParts = explode('.', $field);
                $parent = $fieldParts[0];
                $child = $fieldParts[1];
                
                if (isset($data[$parent][$child])) {
                    $data[$parent][$child] = filter_var($data[$parent][$child], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
        }

        $this->merge($data);
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        if (is_array($validated)) {
            // Agregar metadatos adicionales
            $validated['user_id'] = $this->user()->id;
            $validated['ip_address'] = $this->ip();
            $validated['user_agent'] = $this->userAgent();
            $validated['timestamp'] = now();
            
            // Agregar información del test si está disponible
            if (isset($validated['test_type'])) {
                $validated['test_info'] = self::PERSONALITY_TESTS[$validated['test_type']] ?? null;
            }
        }
        
        return $validated;
    }

    /**
     * Obtener información del test por tipo.
     */
    public function getTestInfo(string $testType): ?array
    {
        return self::PERSONALITY_TESTS[$testType] ?? null;
    }

    /**
     * Verificar si el usuario puede iniciar un test específico.
     */
    public function canStartTest(string $testType): bool
    {
        $user = $this->user();
        
        if (!$user) {
            return false;
        }

        // Verificar si ya tiene un test activo de este tipo
        $activeTest = $user->personalityTests()
            ->where('test_type', $testType)
            ->where('status', 'in_progress')
            ->exists();

        if ($activeTest) {
            return false;
        }

        // Verificar límite de tests completados recientemente
        $recentCompletedTests = $user->personalityTests()
            ->where('test_type', $testType)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        return $recentCompletedTests === 0;
    }

    /**
     * Obtener estadísticas de tests del usuario.
     */
    public function getUserTestStatistics(): array
    {
        $user = $this->user();
        
        if (!$user) {
            return [];
        }

        $completedTests = $user->personalityTests()->completed()->get();
        $activeTests = $user->personalityTests()->inProgress()->get();

        return [
            'completed_tests' => [
                'count' => $completedTests->count(),
                'types' => $completedTests->pluck('test_type')->unique()->toArray(),
                'latest_completion' => $completedTests->max('completed_at')
            ],
            'active_tests' => [
                'count' => $activeTests->count(),
                'types' => $activeTests->pluck('test_type')->toArray(),
                'can_start_more' => $activeTests->count() < 3
            ],
            'available_tests' => array_keys(self::PERSONALITY_TESTS),
            'test_limits' => [
                'max_active_tests' => 3,
                'retake_cooldown_days' => 7,
                'max_retakes_per_test' => 3
            ]
        ];
    }
}
