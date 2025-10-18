<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Matching\Entities\Icebreaker;
use App\Domain\Matching\Entities\IcebreakerTemplate;
use App\Domain\Matching\Entities\CustomIcebreaker;
use App\Domain\Matching\ValueObjects\IcebreakerId;
use App\Domain\Matching\ValueObjects\IcebreakerType;
use App\Domain\Matching\ValueObjects\IcebreakerCategory;
use App\Domain\Matching\ValueObjects\PersonalizationContext;
use App\Domain\Matching\ValueObjects\IcebreakerTemplate as IcebreakerTemplateVO;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\Exceptions\IcebreakerException;
use App\Domain\Matching\Exceptions\InsufficientContextException;
use App\Domain\Matching\Exceptions\IcebreakerGenerationException;
use App\Domain\Matching\Exceptions\InvalidIcebreakerTemplateException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * IcebreakerService
 * 
 * Advanced conversation starter and icebreaker generation service for the
 * ForeverUsInLove dating platform. Provides intelligent, personalized
 * conversation starters based on shared interests, profile analysis, and
 * sophisticated matching context following Domain-Driven Design principles.
 * 
 * Key Responsibilities:
 * - Generate personalized icebreaker messages and conversation starters
 * - Manage icebreaker templates and categories
 * - Analyze profile compatibility for context-aware suggestions
 * - Handle AI-powered icebreaker generation and optimization
 * - Support multiple icebreaker types and interaction styles
 * - Provide icebreaker effectiveness tracking and analytics
 * - Manage user preferences for icebreaker styles
 * - Handle premium icebreaker features and customization
 * 
 * Icebreaker Categories:
 * 1. Shared Interest - Based on common hobbies, activities, or preferences
 * 2. Lifestyle - Related to daily routines, habits, and lifestyle choices
 * 3. Travel & Adventure - Focused on travel experiences and adventure stories
 * 4. Entertainment - Movies, music, books, games, and pop culture
 * 5. Food & Dining - Culinary preferences, cooking, and dining experiences
 * 6. Career & Ambition - Professional interests and career aspirations
 * 7. Humor & Fun - Light-hearted, funny, and playful conversation starters
 * 8. Deep Questions - Thoughtful questions for meaningful conversations
 * 9. Current Events - Topical discussions about recent events or trends
 * 10. Personal Growth - Self-improvement, goals, and personal development
 * 
 * Personalization Features:
 * - AI-powered content analysis for context-aware suggestions
 * - Personality type adaptation for communication style matching
 * - Success rate tracking for continuous improvement
 * - A/B testing for icebreaker effectiveness optimization
 * - Cultural and linguistic sensitivity considerations
 * - Premium custom icebreaker creation and templates
 * 
 * @package ForeverUsInLove\Domain\Matching\Services
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @phpstan-consistent-constructor
 */
final class IcebreakerService
{
    private const CACHE_TTL = 7200; // 2 hours
    private const AI_API_TIMEOUT = 10; // 10 seconds
    private const DEFAULT_SUGGESTIONS_COUNT = 5;
    private const PREMIUM_SUGGESTIONS_COUNT = 15;
    private const MAX_ICEBREAKER_LENGTH = 300;
    private const MIN_ICEBREAKER_LENGTH = 20;
    
    // Icebreaker effectiveness weights for optimization
    private const EFFECTIVENESS_WEIGHTS = [
        'response_rate' => 0.40,
        'conversation_length' => 0.25,
        'positive_feedback' => 0.20,
        'match_success_rate' => 0.15
    ];
    
    // Template categories with success rate priorities
    private const CATEGORY_PRIORITIES = [
        'shared_interest' => 0.95,
        'humor_fun' => 0.85,
        'lifestyle' => 0.80,
        'travel_adventure' => 0.75,
        'entertainment' => 0.70,
        'food_dining' => 0.65,
        'career_ambition' => 0.60,
        'deep_questions' => 0.55,
        'current_events' => 0.50,
        'personal_growth' => 0.45
    ];

    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MatchRepositoryInterface $matchRepository
    ) {}

    // ================================================================
    // CORE ICEBREAKER GENERATION
    // ================================================================

    /**
     * Generate personalized icebreaker suggestions for a match
     * 
     * Creates context-aware conversation starters based on shared interests,
     * compatibility factors, and profile analysis using AI and template systems.
     * 
     * @param UserId $userId The user requesting icebreakers
     * @param UserId $targetUserId The target user for conversation
     * @param int $count Number of suggestions to generate
     * @param array<string> $preferredCategories Preferred icebreaker categories
     * @param bool $useAI Whether to use AI-powered generation
     * @return Collection<Icebreaker> Generated icebreaker suggestions
     * 
     * @throws IcebreakerException When generation fails
     * @throws InsufficientContextException When not enough profile data
     * @throws IcebreakerGenerationException When AI generation fails
     * 
     * @example
     * ```php
     * $icebreakers = $icebreakerService->generateIcebreakers(
     *     $userId,
     *     $matchedUserId,
     *     5,
     *     ['shared_interest', 'humor_fun', 'travel_adventure'],
     *     true
     * );
     * 
     * foreach ($icebreakers as $icebreaker) {
     *     echo $icebreaker->getMessage();
     *     echo "Category: " . $icebreaker->getCategory()->getValue();
     *     echo "Confidence: " . $icebreaker->getConfidenceScore();
     * }
     * ```
     */
    public function generateIcebreakers(
        UserId $userId,
        UserId $targetUserId,
        int $count = self::DEFAULT_SUGGESTIONS_COUNT,
        array $preferredCategories = [],
        bool $useAI = true
    ): Collection {
        try {
            Log::info('Generating personalized icebreakers', [
                'user_id' => $userId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'count' => $count,
                'preferred_categories' => $preferredCategories,
                'use_ai' => $useAI
            ]);

            // Get user profiles for context analysis
            [$userProfile, $targetProfile] = $this->getProfilesForIcebreakers($userId, $targetUserId);

            // Build personalization context
            $context = $this->buildPersonalizationContext($userProfile, $targetProfile);

            // Validate sufficient context for personalization
            if (!$this->hasSufficientContext($context)) {
                throw new InsufficientContextException('Insufficient profile data for personalized icebreakers');
            }

            // Check cache for existing suggestions
            $cacheKey = $this->buildIcebreakerCacheKey($userId, $targetUserId, $count, $preferredCategories);
            if (Cache::has($cacheKey)) {
                Log::debug('Returning cached icebreaker suggestions');
                return Cache::get($cacheKey);
            }

            // Generate icebreakers using multiple strategies
            $icebreakers = new Collection();

            // Strategy 1: AI-powered generation (if enabled and premium)
            if ($useAI && $this->canUseAI($userProfile)) {
                $aiIcebreakers = $this->generateAIIcebreakers($context, $count);
                $icebreakers = $icebreakers->merge($aiIcebreakers);
            }

            // Strategy 2: Template-based generation
            $templateIcebreakers = $this->generateTemplateIcebreakers(
                $context,
                $preferredCategories,
                $count - $icebreakers->count()
            );
            $icebreakers = $icebreakers->merge($templateIcebreakers);

            // Strategy 3: Fallback generation if needed
            if ($icebreakers->count() < $count) {
                $fallbackIcebreakers = $this->generateFallbackIcebreakers(
                    $context,
                    $count - $icebreakers->count()
                );
                $icebreakers = $icebreakers->merge($fallbackIcebreakers);
            }

            // Post-process and optimize suggestions
            $optimizedIcebreakers = $this->optimizeIcebreakers($icebreakers, $context, $count);

            // Cache results
            Cache::put($cacheKey, $optimizedIcebreakers, self::CACHE_TTL);

            // Track generation analytics
            $this->trackIcebreakerGeneration($userId, $targetUserId, $optimizedIcebreakers);

            Log::info('Icebreakers generated successfully', [
                'user_id' => $userId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'generated_count' => $optimizedIcebreakers->count(),
                'ai_powered' => $useAI && $this->canUseAI($userProfile)
            ]);

            return $optimizedIcebreakers;

        } catch (IcebreakerException $e) {
            Log::error('Icebreaker generation failed', [
                'user_id' => $userId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during icebreaker generation', [
                'user_id' => $userId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new IcebreakerGenerationException('Failed to generate icebreakers: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate AI-powered conversation starters
     * 
     * Uses advanced natural language processing to create highly personalized
     * and contextually relevant conversation starters based on profile analysis.
     * 
     * @param PersonalizationContext $context Profile and compatibility context
     * @param int $count Number of AI icebreakers to generate
     * @param array<string, mixed> $aiOptions Additional AI generation options
     * @return Collection<Icebreaker> AI-generated icebreakers
     * 
     * @throws IcebreakerGenerationException When AI generation fails
     * 
     * @example
     * ```php
     * $aiOptions = [
     *     'creativity_level' => 'high',
     *     'tone' => 'playful',
     *     'max_length' => 150,
     *     'include_emojis' => true
     * ];
     * $aiIcebreakers = $icebreakerService->generateAIIcebreakers($context, 3, $aiOptions);
     * ```
     */
    public function generateAIIcebreakers(
        PersonalizationContext $context,
        int $count = 3,
        array $aiOptions = []
    ): Collection {
        try {
            Log::info('Generating AI-powered icebreakers', [
                'context_score' => $context->getContextScore(),
                'count' => $count,
                'ai_options' => $aiOptions
            ]);

            // Prepare AI prompt with context
            $prompt = $this->buildAIPrompt($context, $aiOptions);

            // Call AI service for icebreaker generation
            $aiResponse = $this->callAIService($prompt, $count);

            if (!$aiResponse['success']) {
                throw new IcebreakerGenerationException('AI service failed to generate icebreakers');
            }

            // Parse and validate AI-generated suggestions
            $aiSuggestions = $this->parseAISuggestions($aiResponse['data']);

            // Create icebreaker entities from AI suggestions
            $icebreakers = $aiSuggestions->map(function ($suggestion) use ($context) {
                return new Icebreaker(
                    id: IcebreakerId::generate(),
                    userId: $context->getUserId(),
                    targetUserId: $context->getTargetUserId(),
                    message: $suggestion['message'],
                    type: IcebreakerType::aiGenerated(),
                    category: IcebreakerCategory::fromString($suggestion['category']),
                    confidenceScore: $suggestion['confidence_score'],
                    personalizationFactors: $suggestion['personalization_factors'],
                    createdAt: Carbon::now(),
                    isCustom: false,
                    effectiveness: null // Will be tracked over time
                );
            });

            Log::info('AI icebreakers generated successfully', [
                'generated_count' => $icebreakers->count(),
                'average_confidence' => $icebreakers->avg(fn($ice) => $ice->getConfidenceScore())
            ]);

            return $icebreakers;

        } catch (\Exception $e) {
            Log::error('AI icebreaker generation failed', [
                'error' => $e->getMessage(),
                'context_score' => $context->getContextScore()
            ]);
            
            // Fallback to template-based generation
            Log::info('Falling back to template-based generation');
            return new Collection();
        }
    }

    /**
     * Generate template-based icebreakers
     * 
     * Creates conversation starters using predefined templates personalized
     * with user profile data and shared interests.
     * 
     * @param PersonalizationContext $context Profile and compatibility context
     * @param array<string> $preferredCategories Preferred template categories
     * @param int $count Number of template icebreakers to generate
     * @return Collection<Icebreaker> Template-based icebreakers
     * 
     * @throws IcebreakerException When template generation fails
     * 
     * @example
     * ```php
     * $templateIcebreakers = $icebreakerService->generateTemplateIcebreakers(
     *     $context,
     *     ['shared_interest', 'travel_adventure'],
     *     4
     * );
     * ```
     */
    public function generateTemplateIcebreakers(
        PersonalizationContext $context,
        array $preferredCategories = [],
        int $count = 5
    ): Collection {
        try {
            Log::debug('Generating template-based icebreakers', [
                'preferred_categories' => $preferredCategories,
                'count' => $count,
                'shared_interests_count' => count($context->getSharedInterests())
            ]);

            // Get relevant templates based on context and preferences
            $availableTemplates = $this->getRelevantTemplates($context, $preferredCategories);

            if ($availableTemplates->isEmpty()) {
                Log::warning('No relevant templates found for context');
                return new Collection();
            }

            // Score and rank templates by relevance
            $scoredTemplates = $this->scoreTemplateRelevance($availableTemplates, $context);

            // Generate personalized icebreakers from top templates
            $icebreakers = new Collection();
            $usedTemplates = new Collection();

            foreach ($scoredTemplates->take($count * 2) as $templateData) {
                if ($icebreakers->count() >= $count) {
                    break;
                }

                $template = $templateData['template'];
                
                // Avoid duplicate template usage
                if ($usedTemplates->contains('id', $template->getId())) {
                    continue;
                }

                // Personalize template with context
                $personalizedMessage = $this->personalizeTemplate($template, $context);
                
                if ($personalizedMessage && strlen($personalizedMessage) >= self::MIN_ICEBREAKER_LENGTH) {
                    $icebreaker = new Icebreaker(
                        id: IcebreakerId::generate(),
                        userId: $context->getUserId(),
                        targetUserId: $context->getTargetUserId(),
                        message: $personalizedMessage,
                        type: IcebreakerType::templateBased(),
                        category: $template->getCategory(),
                        confidenceScore: $templateData['relevance_score'],
                        personalizationFactors: $this->extractPersonalizationFactors($template, $context),
                        createdAt: Carbon::now(),
                        isCustom: false,
                        templateId: $template->getId()
                    );

                    $icebreakers->push($icebreaker);
                    $usedTemplates->push($template);
                }
            }

            Log::debug('Template icebreakers generated', [
                'generated_count' => $icebreakers->count(),
                'templates_used' => $usedTemplates->count()
            ]);

            return $icebreakers;

        } catch (\Exception $e) {
            Log::error('Template icebreaker generation failed', [
                'error' => $e->getMessage(),
                'preferred_categories' => $preferredCategories
            ]);
            throw new IcebreakerException('Failed to generate template icebreakers: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // ICEBREAKER MANAGEMENT AND CUSTOMIZATION
    // ================================================================

    /**
     * Create custom icebreaker template for user
     * 
     * Allows users to create personalized icebreaker templates that can be
     * reused and shared with permission.
     * 
     * @param UserId $userId The user creating the template
     * @param string $templateName Name for the custom template
     * @param string $messageTemplate Template message with placeholders
     * @param IcebreakerCategory $category Template category
     * @param array<string, mixed> $options Additional template options
     * @return CustomIcebreaker The created custom icebreaker template
     * 
     * @throws IcebreakerException When template creation fails
     * @throws InvalidIcebreakerTemplateException When template validation fails
     * 
     * @example
     * ```php
     * $template = $icebreakerService->createCustomTemplate(
     *     $userId,
     *     'My Travel Question',
     *     "I noticed you love {shared_travel_interest}! What's the most {adjective} place you've visited?",
     *     IcebreakerCategory::TRAVEL_ADVENTURE,
     *     ['shareable' => true, 'premium_only' => false]
     * );
     * ```
     */
    public function createCustomTemplate(
        UserId $userId,
        string $templateName,
        string $messageTemplate,
        IcebreakerCategory $category,
        array $options = []
    ): CustomIcebreaker {
        try {
            Log::info('Creating custom icebreaker template', [
                'user_id' => $userId->toString(),
                'template_name' => $templateName,
                'category' => $category->getValue()
            ]);

            // Validate template content
            $this->validateIcebreakerTemplate($messageTemplate);

            // Check user limits for custom templates
            $this->validateCustomTemplateLimits($userId);

            // Create custom icebreaker template
            $customTemplate = new CustomIcebreaker(
                id: IcebreakerId::generate(),
                userId: $userId,
                name: $templateName,
                messageTemplate: $messageTemplate,
                category: $category,
                isPublic: $options['shareable'] ?? false,
                isPremiumOnly: $options['premium_only'] ?? false,
                createdAt: Carbon::now(),
                updatedAt: Carbon::now(),
                usageCount: 0,
                effectivenessScore: null
            );

            // Save custom template
            $savedTemplate = $this->saveCustomTemplate($customTemplate);

            Log::info('Custom icebreaker template created successfully', [
                'template_id' => $savedTemplate->getId()->toString(),
                'user_id' => $userId->toString()
            ]);

            return $savedTemplate;

        } catch (\Exception $e) {
            Log::error('Failed to create custom icebreaker template', [
                'user_id' => $userId->toString(),
                'template_name' => $templateName,
                'error' => $e->getMessage()
            ]);
            throw new IcebreakerException('Failed to create custom template: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get icebreaker suggestions by category
     * 
     * Retrieves icebreaker suggestions filtered by specific categories
     * with optional personalization based on user preferences.
     * 
     * @param UserId $userId The user requesting suggestions
     * @param UserId $targetUserId The target user for icebreakers
     * @param IcebreakerCategory $category The specific category to filter by
     * @param int $count Number of suggestions to retrieve
     * @param bool $personalizeForTarget Whether to personalize for target user
     * @return Collection<Icebreaker> Category-filtered icebreaker suggestions
     * 
     * @throws IcebreakerException When suggestion retrieval fails
     * 
     * @example
     * ```php
     * $travelIcebreakers = $icebreakerService->getIcebreakersByCategory(
     *     $userId,
     *     $targetUserId,
     *     IcebreakerCategory::TRAVEL_ADVENTURE,
     *     3,
     *     true
     * );
     * ```
     */
    public function getIcebreakersByCategory(
        UserId $userId,
        UserId $targetUserId,
        IcebreakerCategory $category,
        int $count = 3,
        bool $personalizeForTarget = true
    ): Collection {
        try {
            Log::debug('Getting icebreakers by category', [
                'user_id' => $userId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'category' => $category->getValue(),
                'count' => $count
            ]);

            if ($personalizeForTarget) {
                // Generate personalized icebreakers for this category
                return $this->generateIcebreakers(
                    $userId,
                    $targetUserId,
                    $count,
                    [$category->getValue()],
                    true
                );
            }

            // Get generic templates for this category
            $templates = $this->getTemplatesByCategory($category, $count * 2);
            
            return $templates->take($count)->map(function ($template) use ($userId, $targetUserId) {
                return new Icebreaker(
                    id: IcebreakerId::generate(),
                    userId: $userId,
                    targetUserId: $targetUserId,
                    message: $template->getMessage(),
                    type: IcebreakerType::templateBased(),
                    category: $template->getCategory(),
                    confidenceScore: 0.7, // Default confidence for generic templates
                    personalizationFactors: [],
                    createdAt: Carbon::now(),
                    isCustom: false,
                    templateId: $template->getId()
                );
            });

        } catch (\Exception $e) {
            Log::error('Failed to get icebreakers by category', [
                'user_id' => $userId->toString(),
                'category' => $category->getValue(),
                'error' => $e->getMessage()
            ]);
            throw new IcebreakerException('Failed to get category icebreakers: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // ANALYTICS AND OPTIMIZATION
    // ================================================================

    /**
     * Track icebreaker effectiveness and usage
     * 
     * Records icebreaker usage outcomes for continuous improvement and
     * personalization algorithm optimization.
     * 
     * @param IcebreakerId $icebreakerId The icebreaker that was used
     * @param string $outcome The interaction outcome (sent, responded, ignored, etc.)
     * @param array<string, mixed> $contextData Additional context about the interaction
     * @return bool True if tracking was successful
     * 
     * @throws IcebreakerException When tracking fails
     * 
     * @example
     * ```php
     * $success = $icebreakerService->trackIcebreakerUsage(
     *     $icebreakerId,
     *     'positive_response',
     *     [
     *         'response_time_hours' => 3,
     *         'conversation_length' => 5,
     *         'user_rating' => 4.5
     *     ]
     * );
     * ```
     */
    public function trackIcebreakerUsage(
        IcebreakerId $icebreakerId,
        string $outcome,
        array $contextData = []
    ): bool {
        try {
            Log::info('Tracking icebreaker usage', [
                'icebreaker_id' => $icebreakerId->toString(),
                'outcome' => $outcome,
                'context_keys' => array_keys($contextData)
            ]);

            // Record usage outcome
            $usageData = [
                'icebreaker_id' => $icebreakerId->toString(),
                'outcome' => $outcome,
                'timestamp' => Carbon::now(),
                'context' => $contextData
            ];

            // Store usage data for analytics
            $this->recordIcebreakerUsage($usageData);

            // Update icebreaker effectiveness metrics
            $this->updateIcebreakerEffectiveness($icebreakerId, $outcome, $contextData);

            // Update template effectiveness if applicable
            if (isset($contextData['template_id'])) {
                $this->updateTemplateEffectiveness($contextData['template_id'], $outcome, $contextData);
            }

            Log::info('Icebreaker usage tracked successfully', [
                'icebreaker_id' => $icebreakerId->toString(),
                'outcome' => $outcome
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to track icebreaker usage', [
                'icebreaker_id' => $icebreakerId->toString(),
                'outcome' => $outcome,
                'error' => $e->getMessage()
            ]);
            throw new IcebreakerException('Failed to track icebreaker usage: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get icebreaker analytics and performance metrics
     * 
     * Provides comprehensive analytics on icebreaker effectiveness,
     * category performance, and user engagement patterns.
     * 
     * @param UserId $userId The user for analytics (optional for global analytics)
     * @param CarbonInterface|null $since Start date for analytics period
     * @param CarbonInterface|null $until End date for analytics period
     * @return array<string, mixed> Comprehensive icebreaker analytics
     * 
     * @throws IcebreakerException When analytics generation fails
     * 
     * @example
     * ```php
     * $analytics = $icebreakerService->getIcebreakerAnalytics(
     *     $userId,
     *     Carbon::now()->subMonth(),
     *     Carbon::now()
     * );
     * ```
     */
    public function getIcebreakerAnalytics(
        ?UserId $userId = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            $until = $until ?? Carbon::now();

            Log::info('Generating icebreaker analytics', [
                'user_id' => $userId?->toString(),
                'period' => [$since->toISOString(), $until->toISOString()]
            ]);

            $analytics = [
                'period' => [
                    'start' => $since,
                    'end' => $until,
                    'duration_days' => $since->diffInDays($until)
                ],
                'usage_statistics' => $this->getUsageStatistics($userId, $since, $until),
                'effectiveness_metrics' => $this->getEffectivenessMetrics($userId, $since, $until),
                'category_performance' => $this->getCategoryPerformance($userId, $since, $until),
                'personalization_effectiveness' => $this->getPersonalizationEffectiveness($userId, $since, $until),
                'template_performance' => $this->getTemplatePerformance($userId, $since, $until),
                'optimization_recommendations' => $this->generateOptimizationRecommendations($userId, $since, $until)
            ];

            Log::info('Icebreaker analytics generated successfully', [
                'user_id' => $userId?->toString(),
                'total_icebreakers_analyzed' => $analytics['usage_statistics']['total_generated']
            ]);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate icebreaker analytics', [
                'user_id' => $userId?->toString(),
                'error' => $e->getMessage()
            ]);
            throw new IcebreakerException('Failed to generate analytics: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Get user profiles for icebreaker generation
     */
    private function getProfilesForIcebreakers(UserId $userId, UserId $targetUserId): array
    {
        $userProfile = $this->profileRepository->findByUserId($userId->toInt());
        $targetProfile = $this->profileRepository->findByUserId($targetUserId->toInt());

        if (!$userProfile || !$targetProfile) {
            throw new InsufficientContextException('User profiles not found or incomplete');
        }

        return [$userProfile, $targetProfile];
    }

    /**
     * Build personalization context from profiles
     */
    private function buildPersonalizationContext($userProfile, $targetProfile): PersonalizationContext
    {
        return PersonalizationContext::create(
            $userProfile->getUserId(),
            $targetProfile->getUserId(),
            [
                'shared_interests' => $this->findSharedInterests($userProfile, $targetProfile),
                'compatibility_factors' => $this->extractCompatibilityFactors($userProfile, $targetProfile),
                'personality_insights' => $this->getPersonalityInsights($userProfile, $targetProfile),
                'lifestyle_alignment' => $this->analyzeLifestyleAlignment($userProfile, $targetProfile),
                'communication_preferences' => $this->getCommunicationPreferences($userProfile),
                'cultural_context' => $this->extractCulturalContext($userProfile, $targetProfile)
            ]
        );
    }

    /**
     * Check if sufficient context exists for personalization
     */
    private function hasSufficientContext(PersonalizationContext $context): bool
    {
        return $context->getContextScore() >= 0.3; // Minimum 30% context coverage
    }

    /**
     * Build cache key for icebreaker suggestions
     */
    private function buildIcebreakerCacheKey(
        UserId $userId,
        UserId $targetUserId,
        int $count,
        array $preferredCategories
    ): string {
        $sortedCategories = $preferredCategories;
        sort($sortedCategories);
        $categoriesHash = md5(implode(',', $sortedCategories));
        return "icebreakers.{$userId->toString()}.{$targetUserId->toString()}.{$count}.{$categoriesHash}";
    }

    /**
     * Check if user can use AI-powered generation
     */
    private function canUseAI($userProfile): bool
    {
        return $userProfile->isPremium() || $userProfile->hasAIFeatures();
    }

    /**
     * Call AI service for icebreaker generation
     */
    private function callAIService(string $prompt, int $count): array
    {
        try {
            $response = Http::timeout(self::AI_API_TIMEOUT)
                ->post(config('services.ai.icebreaker_endpoint'), [
                    'prompt' => $prompt,
                    'count' => $count,
                    'max_length' => self::MAX_ICEBREAKER_LENGTH,
                    'min_length' => self::MIN_ICEBREAKER_LENGTH
                ]);

            if (!$response->successful()) {
                throw new \Exception('AI service request failed: ' . $response->status());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::warning('AI service unavailable, using fallback', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build AI prompt from personalization context
     */
    private function buildAIPrompt(PersonalizationContext $context, array $options): string
    {
        $sharedInterests = implode(', ', $context->getSharedInterests());
        $tone = $options['tone'] ?? 'friendly';
        $creativity = $options['creativity_level'] ?? 'medium';
        
        return "Generate personalized conversation starters for a dating app match. " .
               "Shared interests: {$sharedInterests}. " .
               "Tone: {$tone}. Creativity: {$creativity}. " .
               "Make them engaging, authentic, and easy to respond to.";
    }

    /**
     * Generate fallback icebreakers when other methods fail
     */
    private function generateFallbackIcebreakers(PersonalizationContext $context, int $count): Collection
    {
        $fallbackMessages = [
            "Hey! I noticed we have some things in common. How's your day going?",
            "Hi there! I'd love to get to know you better. What's something that made you smile today?",
            "Hello! I'm excited to chat with you. What's your favorite way to spend a weekend?",
            "Hi! I think we might have some interesting conversations ahead. What's something you're passionate about?",
            "Hey! I'd love to learn more about you. What's the best part of your day so far?"
        ];

        $icebreakers = new Collection();
        for ($i = 0; $i < min($count, count($fallbackMessages)); $i++) {
            $icebreakers->push(new Icebreaker(
                id: IcebreakerId::generate(),
                userId: $context->getUserId(),
                targetUserId: $context->getTargetUserId(),
                message: $fallbackMessages[$i],
                type: IcebreakerType::templateBased(),
                category: IcebreakerCategory::fromString('humor_fun'),
                confidenceScore: 0.5,
                personalizationFactors: [],
                createdAt: Carbon::now(),
                isCustom: false
            ));
        }

        return $icebreakers;
    }

    /**
     * Optimize icebreakers based on context and effectiveness
     */
    private function optimizeIcebreakers(Collection $icebreakers, PersonalizationContext $context, int $count): Collection
    {
        // Sort by confidence score and effectiveness
        return $icebreakers
            ->sortByDesc(fn($icebreaker) => $icebreaker->getConfidenceScore())
            ->take($count);
    }

    /**
     * Track icebreaker generation for analytics
     */
    private function trackIcebreakerGeneration(UserId $userId, UserId $targetUserId, Collection $icebreakers): void
    {
        // Implementation for tracking generation analytics
        Log::info('Icebreaker generation tracked', [
            'user_id' => $userId->toString(),
            'target_user_id' => $targetUserId->toString(),
            'generated_count' => $icebreakers->count()
        ]);
    }

    /**
     * Parse AI suggestions from service response
     */
    private function parseAISuggestions(array $data): Collection
    {
        $suggestions = new Collection();
        
        foreach ($data as $item) {
            $suggestions->push([
                'message' => $item['message'] ?? 'Hello! How are you?',
                'category' => $item['category'] ?? 'shared_interest',
                'confidence_score' => $item['confidence_score'] ?? 0.7,
                'personalization_factors' => $item['personalization_factors'] ?? []
            ]);
        }

        return $suggestions;
    }

    /**
     * Get relevant templates based on context
     */
    private function getRelevantTemplates(PersonalizationContext $context, array $preferredCategories): Collection
    {
        // Mock implementation - in real app, this would query template repository
        return new Collection([
            (object) [
                'id' => IcebreakerId::generate(),
                'message' => 'I noticed you love {interest}! What\'s your favorite thing about it?',
                'category' => IcebreakerCategory::fromString('shared_interest')
            ]
        ]);
    }

    /**
     * Score template relevance based on context
     */
    private function scoreTemplateRelevance(Collection $templates, PersonalizationContext $context): Collection
    {
        return $templates->map(function ($template) use ($context) {
            return [
                'template' => $template,
                'relevance_score' => 0.8 // Mock score
            ];
        });
    }

    /**
     * Personalize template with context data
     */
    private function personalizeTemplate($template, PersonalizationContext $context): ?string
    {
        $message = $template->message;
        
        // Replace placeholders with actual data
        if ($context->hasSharedInterests()) {
            $interests = $context->getSharedInterests();
            $message = str_replace('{interest}', $interests[0] ?? 'this', $message);
        }
        
        return $message;
    }

    /**
     * Extract personalization factors from template and context
     */
    private function extractPersonalizationFactors($template, PersonalizationContext $context): array
    {
        return [
            'template_id' => $template->id->toString(),
            'shared_interests_used' => $context->hasSharedInterests(),
            'personalization_level' => $context->getPersonalizationLevel()
        ];
    }

    /**
     * Validate icebreaker template content
     */
    private function validateIcebreakerTemplate(string $messageTemplate): void
    {
        if (strlen($messageTemplate) < self::MIN_ICEBREAKER_LENGTH) {
            throw new InvalidIcebreakerTemplateException('Template message too short');
        }
        
        if (strlen($messageTemplate) > self::MAX_ICEBREAKER_LENGTH) {
            throw new InvalidIcebreakerTemplateException('Template message too long');
        }
    }

    /**
     * Validate custom template limits for user
     */
    private function validateCustomTemplateLimits(UserId $userId): void
    {
        // Mock implementation - check user's custom template count
        // In real app, this would query the repository
    }

    /**
     * Save custom template
     */
    private function saveCustomTemplate(CustomIcebreaker $template): CustomIcebreaker
    {
        // Mock implementation - in real app, this would save to repository
        return $template;
    }

    /**
     * Get templates by category
     */
    private function getTemplatesByCategory(IcebreakerCategory $category, int $count): Collection
    {
        // Mock implementation - in real app, this would query template repository
        return new Collection([
            (object) [
                'id' => IcebreakerId::generate(),
                'message' => 'Generic template message',
                'category' => $category
            ]
        ]);
    }

    /**
     * Record icebreaker usage for analytics
     */
    private function recordIcebreakerUsage(array $usageData): void
    {
        // Mock implementation - in real app, this would save to analytics storage
        Log::info('Icebreaker usage recorded', $usageData);
    }

    /**
     * Update icebreaker effectiveness metrics
     */
    private function updateIcebreakerEffectiveness(IcebreakerId $icebreakerId, string $outcome, array $contextData): void
    {
        // Mock implementation - update effectiveness metrics
        Log::info('Icebreaker effectiveness updated', [
            'icebreaker_id' => $icebreakerId->toString(),
            'outcome' => $outcome
        ]);
    }

    /**
     * Update template effectiveness metrics
     */
    private function updateTemplateEffectiveness(string $templateId, string $outcome, array $contextData): void
    {
        // Mock implementation - update template effectiveness
        Log::info('Template effectiveness updated', [
            'template_id' => $templateId,
            'outcome' => $outcome
        ]);
    }

    /**
     * Get usage statistics for analytics
     */
    private function getUsageStatistics(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'total_generated' => 0,
            'total_used' => 0,
            'response_rate' => 0.0
        ];
    }

    /**
     * Get effectiveness metrics for analytics
     */
    private function getEffectivenessMetrics(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'average_confidence' => 0.0,
            'success_rate' => 0.0,
            'conversation_length' => 0.0
        ];
    }

    /**
     * Get category performance for analytics
     */
    private function getCategoryPerformance(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Get personalization effectiveness for analytics
     */
    private function getPersonalizationEffectiveness(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'personalization_score' => 0.0,
            'context_utilization' => 0.0
        ];
    }

    /**
     * Get template performance for analytics
     */
    private function getTemplatePerformance(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Find shared interests between profiles
     */
    private function findSharedInterests($userProfile, $targetProfile): array
    {
        // Mock implementation - in real app, this would analyze profiles
        return ['music', 'travel', 'food'];
    }

    /**
     * Extract compatibility factors between profiles
     */
    private function extractCompatibilityFactors($userProfile, $targetProfile): array
    {
        // Mock implementation
        return ['age_compatibility' => 0.8, 'location_compatibility' => 0.9];
    }

    /**
     * Get personality insights from profiles
     */
    private function getPersonalityInsights($userProfile, $targetProfile): array
    {
        // Mock implementation
        return ['extroversion_match' => 0.7, 'openness_match' => 0.8];
    }

    /**
     * Analyze lifestyle alignment between profiles
     */
    private function analyzeLifestyleAlignment($userProfile, $targetProfile): array
    {
        // Mock implementation
        return ['schedule_alignment' => 0.6, 'activity_preference_match' => 0.7];
    }

    /**
     * Get communication preferences from user profile
     */
    private function getCommunicationPreferences($userProfile): array
    {
        // Mock implementation
        return ['preferred_tone' => 'casual', 'response_time' => 'quick'];
    }

    /**
     * Extract cultural context from profiles
     */
    private function extractCulturalContext($userProfile, $targetProfile): array
    {
        // Mock implementation
        return ['language_preference' => 'english', 'cultural_values_match' => 0.8];
    }
}