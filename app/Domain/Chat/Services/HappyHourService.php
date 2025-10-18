<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\Location;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Chat\Events\GroupChatJoined;
use App\Domain\Chat\Events\MessageSent;
use App\Domain\Chat\Exceptions\HappyHourException;
use App\Domain\Chat\Exceptions\EventNotFoundException;
use App\Domain\Chat\Exceptions\EventCapacityExceededException;
use App\Domain\Chat\Exceptions\UnauthorizedEventAccessException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Happy Hour Chat Events Service
 * 
 * Comprehensive service for managing Happy Hour events, social gatherings,
 * themed chat rooms, and real-time social interactions following Clean Architecture principles.
 * 
 * @package App\Domain\Chat\Services
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2024-01-20
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Service Layer Pattern with Domain-Driven Design
 * 
 * Dependencies:
 * - ChatRepositoryInterface for event chat management
 * - MessageRepositoryInterface for event messaging
 * - ProfileRepositoryInterface for participant data
 * - MatchRepositoryInterface for compatibility matching in events
 * - Laravel Events for real-time event notifications
 * - Carbon for date/time handling
 * 
 * Key Features:
 * - Scheduled Happy Hour events with themes
 * - Real-time participant matching and suggestions
 * - Location-based and interest-based event creation
 * - Ice-breaker activities and conversation starters
 * - Event moderation and safety controls
 * - Analytics and engagement tracking
 * - Premium event features and hosting
 * - Integration with video calls and group chats
 * - Event discovery and recommendation system
 * - Gamification and reward systems
 * - Social challenges and mini-games
 * - Live polls and interactive features
 */
class HappyHourService
{
    /**
     * Event type constants
     */
    private const TYPE_SCHEDULED = 'scheduled';
    private const TYPE_INSTANT = 'instant';
    private const TYPE_RECURRING = 'recurring';
    private const TYPE_LOCATION_BASED = 'location_based';
    private const TYPE_INTEREST_BASED = 'interest_based';
    private const TYPE_SPEED_DATING = 'speed_dating';
    private const TYPE_THEMED = 'themed';

    /**
     * Event status constants
     */
    private const STATUS_SCHEDULED = 'scheduled';
    private const STATUS_STARTING = 'starting';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_ENDING = 'ending';
    private const STATUS_ENDED = 'ended';
    private const STATUS_CANCELLED = 'cancelled';

    /**
     * Event themes
     */
    private const THEMES = [
        'casual_chat' => 'Casual Conversations',
        'hobby_lovers' => 'Hobby Enthusiasts',
        'foodies' => 'Food & Dining',
        'travelers' => 'Travel Stories',
        'fitness' => 'Fitness & Health',
        'movies_tv' => 'Movies & TV Shows',
        'music' => 'Music Lovers',
        'books' => 'Book Club',
        'games' => 'Gaming Community',
        'professionals' => 'Professional Networking',
        'artists' => 'Creative Minds',
        'entrepreneurs' => 'Startup Stories'
    ];

    /**
     * Event limits and configuration
     */
    private const MIN_PARTICIPANTS = 4;
    private const MAX_PARTICIPANTS_FREE = 20;
    private const MAX_PARTICIPANTS_PREMIUM = 50;
    private const EVENT_DURATION_DEFAULT = 3600; // 1 hour
    private const EVENT_DURATION_MAX = 7200; // 2 hours
    private const CACHE_TTL = 300; // 5 minutes
    private const MATCHING_INTERVAL = 300; // 5 minutes for re-matching

    /**
     * Activity types for engagement
     */
    private const ACTIVITIES = [
        'icebreaker_questions',
        'two_truths_one_lie',
        'would_you_rather',
        'speed_introductions',
        'topic_discussions',
        'polls_and_votes',
        'mini_games',
        'compatibility_quiz'
    ];

    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MatchRepositoryInterface $matchRepository
    ) {}

    /**
     * ========================================
     * EVENT CREATION AND MANAGEMENT
     * ========================================
     */

    /**
     * Create a new Happy Hour event
     * 
     * @param UserId $hostId Event host user ID
     * @param array $eventData Event configuration data
     * @param array $options Creation options
     * @return array Created event data
     * 
     * @throws HappyHourException
     */
    public function createHappyHourEvent(
        UserId $hostId,
        array $eventData,
        array $options = []
    ): array {
        try {
            Log::info('Creating Happy Hour event', [
                'host_id' => $hostId->toInt(),
                'event_type' => $eventData['type'] ?? self::TYPE_INSTANT,
                'theme' => $eventData['theme'] ?? 'casual_chat'
            ]);

            // Validate host permissions
            $this->validateEventCreation($hostId, $eventData);

            // Validate and sanitize event data
            $validatedData = $this->validateEventData($eventData);

            // Calculate event timing
            $eventTiming = $this->calculateEventTiming($validatedData);

            // Prepare event data
            $happyHourData = [
                'id' => Str::uuid()->toString(),
                'type' => 'group',
                'subtype' => 'happy_hour',
                'status' => $eventTiming['starts_immediately'] ? self::STATUS_STARTING : self::STATUS_SCHEDULED,
                'name' => $validatedData['title'],
                'description' => $validatedData['description'],
                'host_id' => $hostId->toInt(),
                'participants' => [$hostId->toInt()],
                'event_config' => [
                    'event_type' => $validatedData['type'],
                    'theme' => $validatedData['theme'],
                    'max_participants' => $this->getMaxParticipants($hostId, $validatedData),
                    'privacy' => $validatedData['privacy'] ?? 'public',
                    'location_based' => $validatedData['location_based'] ?? false,
                    'interest_matching' => $validatedData['interest_matching'] ?? true,
                    'age_range' => $validatedData['age_range'] ?? ['min' => 21, 'max' => 50],
                    'language' => $validatedData['language'] ?? 'en'
                ],
                'timing' => [
                    'scheduled_start' => $eventTiming['start_time'],
                    'scheduled_end' => $eventTiming['end_time'],
                    'duration_minutes' => $eventTiming['duration_minutes'],
                    'timezone' => $validatedData['timezone'] ?? 'UTC',
                    'recurring' => $validatedData['recurring'] ?? null
                ],
                'activities' => [
                    'enabled_activities' => $validatedData['activities'] ?? ['icebreaker_questions', 'topic_discussions'],
                    'icebreaker_frequency' => $validatedData['icebreaker_frequency'] ?? 10, // minutes
                    'matching_rounds' => $validatedData['matching_rounds'] ?? 3,
                    'mini_games_enabled' => $validatedData['mini_games'] ?? false,
                    'polls_enabled' => $validatedData['polls'] ?? true
                ],
                'location' => $validatedData['location'] ?? null,
                'requirements' => [
                    'verified_only' => $validatedData['verified_only'] ?? false,
                    'premium_only' => $validatedData['premium_only'] ?? false,
                    'min_profile_completion' => $validatedData['min_completion'] ?? 0.7,
                    'interests_required' => $validatedData['interests_required'] ?? []
                ],
                'settings' => [
                    'notifications_enabled' => true,
                    'auto_matching_enabled' => true,
                    'moderation_enabled' => true,
                    'recording_allowed' => false,
                    'invite_friends_enabled' => $validatedData['invite_friends'] ?? true
                ],
                'members' => [
                    $hostId->toInt() => [
                        'role' => 'host',
                        'joined_at' => Carbon::now(),
                        'status' => 'active',
                        'engagement_score' => 0.0,
                        'matches_made' => 0,
                        'activities_completed' => []
                    ]
                ],
                'analytics' => [
                    'created_at' => Carbon::now(),
                    'participant_count' => 1,
                    'messages_sent' => 0,
                    'matches_facilitated' => 0,
                    'average_engagement' => 0.0,
                    'completion_rate' => 0.0
                ],
                'metadata' => [
                    'platform_created' => $options['platform'] ?? 'web',
                    'creation_source' => $options['source'] ?? 'manual',
                    'featured_event' => $validatedData['featured'] ?? false,
                    'hashtags' => $validatedData['hashtags'] ?? [],
                    'external_links' => $validatedData['links'] ?? []
                ]
            ];

            // Create the Happy Hour event
            $chatEntity = $this->chatRepository->create($happyHourData);

            // Convert to array for compatibility with existing code
            $event = [
                'id' => $chatEntity->id()->value(),
                'name' => $chatEntity->name(),
                'description' => $chatEntity->description(),
                'status' => self::STATUS_STARTING,
                'event_config' => $happyHourData['event_config'],
                'timing' => $happyHourData['timing'],
                'activities' => $happyHourData['activities'],
                'location' => $happyHourData['location'],
                'requirements' => $happyHourData['requirements'],
                'settings' => $happyHourData['settings'],
                'members' => $happyHourData['members'],
                'analytics' => $happyHourData['analytics'],
                'metadata' => $happyHourData['metadata']
            ];

            // Schedule event activities if needed
            if (!$eventTiming['starts_immediately']) {
                $this->scheduleEventStart($event);
            } else {
                $this->startEventActivities($event);
            }

            // Add welcome message and initial activities
            $this->initializeEventContent($event);

            // Cache event data
            $this->cacheEventData($event);

            // Track analytics
            $this->trackEventAnalytics($event, 'event_created');

            Log::info('Happy Hour event created successfully', [
                'event_id' => $event['id'],
                'host_id' => $hostId->toInt(),
                'theme' => $event['event_config']['theme'],
                'starts_at' => $event['timing']['scheduled_start']
            ]);

            return $event;

        } catch (\Exception $e) {
            Log::error('Failed to create Happy Hour event', [
                'host_id' => $hostId->toInt(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new HappyHourException('Failed to create Happy Hour event: ' . $e->getMessage());
        }
    }

    /**
     * Join a Happy Hour event
     * 
     * @param string $eventId Event identifier
     * @param UserId $userId User joining the event
     * @param array $joinOptions Join configuration
     * @return array Join result data
     */
    public function joinHappyHourEvent(
        string $eventId,
        UserId $userId,
        array $joinOptions = []
    ): array {
        try {
            Log::info('User joining Happy Hour event', [
                'event_id' => $eventId,
                'user_id' => $userId->toInt()
            ]);

            // Validate event and user eligibility
            $event = $this->validateEventAccess($eventId, $userId);
            $this->validateJoinEligibility($event, $userId);

            // Add participant to event
            $participantData = [
                'role' => 'participant',
                'joined_at' => Carbon::now(),
                'status' => 'active',
                'engagement_score' => 0.0,
                'matches_made' => 0,
                'activities_completed' => [],
                'interests' => $this->getUserInterests($userId),
                'preferences' => $joinOptions['preferences'] ?? []
            ];

            $success = $this->chatRepository->addParticipant($eventId, $userId, 'member', $participantData);

            if ($success) {
                // Trigger group joined event
                Event::dispatch(new GroupChatJoined(
                    $eventId,
                    $userId,
                    'participant',
                    ['event_type' => 'happy_hour']
                ));

                // Add join announcement
                $this->addEventJoinMessage($eventId, $userId);

                // Find and suggest matches for the new participant
                $suggestedMatches = $this->findEventMatches($event, $userId);

                // Start personalized activities
                $this->initializeParticipantActivities($eventId, $userId);

                // Update event analytics
                $this->updateEventParticipantCount($eventId);

                // Cache updates
                $this->invalidateEventCache($eventId);

                Log::info('User joined Happy Hour event successfully', [
                    'event_id' => $eventId,
                    'user_id' => $userId->toInt(),
                    'suggested_matches' => count($suggestedMatches)
                ]);

                return [
                    'status' => 'joined',
                    'event_id' => $eventId,
                    'user_id' => $userId->toInt(),
                    'role' => 'participant',
                    'suggested_matches' => $suggestedMatches,
                    'current_activities' => $this->getCurrentActivities($event),
                    'event_info' => $this->getEventInfoForParticipant($event, $userId)
                ];
            }

            throw new HappyHourException('Failed to join Happy Hour event');

        } catch (\Exception $e) {
            Log::error('Failed to join Happy Hour event', [
                'event_id' => $eventId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new HappyHourException('Failed to join event: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * EVENT DISCOVERY AND RECOMMENDATIONS
     * ========================================
     */

    /**
     * Discover available Happy Hour events
     * 
     * @param UserId $userId User discovering events
     * @param array $criteria Discovery criteria
     * @param array $options Discovery options
     * @return Collection Available events
     */
    public function discoverHappyHourEvents(
        UserId $userId,
        array $criteria = [],
        array $options = []
    ): Collection {
        try {
            // Get user profile for personalization
            $userProfile = $this->profileRepository->findById($userId->toInt(), [
                'with_preferences' => true,
                'with_interests' => true,
                'with_location' => true
            ]);
            
            if (!$userProfile) {
                throw new HappyHourException('User profile not found');
            }

            // Prepare search criteria
            $searchCriteria = [
                'status' => [self::STATUS_SCHEDULED, self::STATUS_ACTIVE],
                'subtype' => 'happy_hour',
                'exclude_member' => $userId->toInt(),
                'has_capacity' => true,
                'upcoming_only' => true,
                'location_radius' => $criteria['location_radius'] ?? 50,
                'interests' => $criteria['interests'] ?? ($userProfile['interests'] ?? []),
                'age_compatibility' => $this->calculateAgeCompatibility($userProfile->toArray()),
                'language' => $criteria['language'] ?? ($userProfile['language'] ?? 'en'),
                'event_type' => $criteria['event_type'] ?? null,
                'theme' => $criteria['theme'] ?? null,
                'verified_only' => $criteria['verified_only'] ?? false
            ];

            // Get events from repository
            $events = $this->chatRepository->searchChats($searchCriteria);

            // Personalize and score events
            $personalizedEvents = $events->map(function ($event) use ($userProfile, $userId) {
                return $this->personalizeEventForUser($event, $userProfile->toArray(), $userId);
            });

            // Sort by recommendation score
            $sortedEvents = $personalizedEvents->sortByDesc('recommendation_score')->values();

            // Add real-time data
            $enrichedEvents = $sortedEvents->map(function ($event) {
                return $this->enrichEventWithRealTimeData($event);
            });

            Log::info('Happy Hour events discovered', [
                'user_id' => $userId->toInt(),
                'events_found' => $enrichedEvents->count(),
                'criteria' => array_keys($criteria)
            ]);

            return $enrichedEvents;

        } catch (\Exception $e) {
            Log::error('Failed to discover Happy Hour events', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new HappyHourException('Failed to discover events: ' . $e->getMessage());
        }
    }

    /**
     * Get user's Happy Hour event history and statistics
     * 
     * @param UserId $userId User identifier
     * @param array $options Query options
     * @return array Event history and stats
     */
    public function getUserEventHistory(
        UserId $userId,
        array $options = []
    ): array {
        try {
            // Get event participation history
            $eventHistory = $this->chatRepository->getUserEventHistory($userId, [
                'subtype' => 'happy_hour',
                'include_analytics' => true,
                'date_range' => $options['date_range'] ?? null
            ]);

            // Calculate user statistics
            $statistics = $this->calculateUserEventStatistics($userId, $eventHistory);

            // Get achievements and badges
            $achievements = $this->getUserEventAchievements($userId, $statistics);

            return [
                'history' => $eventHistory,
                'statistics' => $statistics,
                'achievements' => $achievements,
                'recommendations' => $this->getPersonalizedEventRecommendations($userId, $statistics)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user event history', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new HappyHourException('Failed to get event history: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * EVENT ACTIVITIES AND ENGAGEMENT
     * ========================================
     */

    /**
     * Start an activity within an event
     * 
     * @param string $eventId Event identifier
     * @param UserId $userId User starting the activity
     * @param string $activityType Type of activity
     * @param array $activityData Activity configuration
     * @return array Activity result
     */
    public function startEventActivity(
        string $eventId,
        UserId $userId,
        string $activityType,
        array $activityData = []
    ): array {
        try {
            // Validate event and user permissions
            $event = $this->validateEventAccess($eventId, $userId);
            $this->validateActivityPermissions($event, $userId, $activityType);

            // Execute activity based on type
            $result = match($activityType) {
                'icebreaker_questions' => $this->startIcebreakerActivity($event, $userId, $activityData),
                'speed_introductions' => $this->startSpeedIntroductions($event, $userId, $activityData),
                'compatibility_quiz' => $this->startCompatibilityQuiz($event, $userId, $activityData),
                'topic_discussions' => $this->startTopicDiscussion($event, $userId, $activityData),
                'polls_and_votes' => $this->startPoll($event, $userId, $activityData),
                'mini_games' => $this->startMiniGame($event, $userId, $activityData),
                default => throw new HappyHourException("Unknown activity type: {$activityType}")
            };

            // Update participant engagement
            $this->updateParticipantEngagement($eventId, $userId, $activityType, $result);

            // Track analytics
            $this->trackEventAnalytics($event, 'activity_started', [
                'activity_type' => $activityType,
                'started_by' => $userId->toInt()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to start event activity', [
                'event_id' => $eventId,
                'user_id' => $userId->toInt(),
                'activity_type' => $activityType,
                'error' => $e->getMessage()
            ]);
            throw new HappyHourException('Failed to start activity: ' . $e->getMessage());
        }
    }

    /**
     * Find and suggest matches within the event
     * 
     * @param string $eventId Event identifier
     * @param UserId $userId User to find matches for
     * @param array $options Matching options
     * @return Collection Suggested matches
     */
    public function findEventMatches(
        array|string $event,
        UserId $userId,
        array $options = []
    ): Collection {
        try {
            if (is_string($event)) {
                $event = $this->validateEventAccess($event, $userId);
            }

            // Get user profile and preferences
            $userProfile = $this->profileRepository->findById($userId->toInt(), [
                'with_preferences' => true,
                'with_interests' => true
            ]);

            // Get other event participants
            $otherParticipants = collect($event['members'])
                ->filter(fn($member, $memberId) => $memberId !== $userId->toInt())
                ->keys()
                ->toArray();

            if (empty($otherParticipants)) {
                return collect();
            }

            // Calculate compatibility with each participant
            $compatibilityScores = [];
            foreach ($otherParticipants as $participantId) {
                $participantProfile = $this->profileRepository->findById($participantId);
                if ($participantProfile) {
                    $compatibility = $this->calculateEventCompatibility($userProfile->toArray(), $participantProfile->toArray(), $event);
                    $compatibilityScores[$participantId] = $compatibility;
                }
            }

            // Sort by compatibility and return top matches
            $sortedMatches = collect($compatibilityScores)
                ->sortByDesc('overall_score')
                ->take($options['limit'] ?? 5);

            // Enrich with additional data
            $enrichedMatches = $sortedMatches->map(function ($compatibility, $participantId) use ($event) {
                $profile = $this->profileRepository->findById($participantId);
                $member = $event['members'][$participantId] ?? [];
                
                return [
                    'user_id' => $participantId,
                    'profile' => $profile,
                    'compatibility' => $compatibility,
                    'event_engagement' => $member['engagement_score'] ?? 0.0,
                    'common_interests' => $compatibility['common_interests'] ?? [],
                    'conversation_starters' => $this->generateConversationStarters($compatibility)
                ];
            });

            return $enrichedMatches->values();

        } catch (\Exception $e) {
            Log::error('Failed to find event matches', [
                'event_id' => is_array($event) ? $event['id'] : $event,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Validate event creation permissions
     */
    private function validateEventCreation(UserId $hostId, array $eventData): void
    {
        $hostProfile = $this->profileRepository->findById($hostId->toInt());
        if (!$hostProfile || ($hostProfile['status'] ?? '') !== 'active') {
            throw new HappyHourException('Host profile not found or inactive');
        }

        // Check if user can host events
        $eventCount = $this->chatRepository->getUserHostedEventCount($hostId);
        $isPremium = $hostProfile['premium_status'] ?? false;
        $maxEvents = $isPremium ? 10 : 3; // Daily limit

        if ($eventCount >= $maxEvents) {
            throw new HappyHourException("Daily event hosting limit exceeded: {$maxEvents}");
        }

        // Validate premium features
        if (($eventData['featured'] ?? false) && !$isPremium) {
            throw new HappyHourException('Premium subscription required for featured events');
        }
    }

    /**
     * Validate and sanitize event data
     */
    private function validateEventData(array $eventData): array
    {
        $validated = [];

        // Required fields
        $validated['title'] = trim($eventData['title'] ?? 'Happy Hour Chat');
        if (strlen($validated['title']) > 100) {
            $validated['title'] = substr($validated['title'], 0, 100);
        }

        $validated['description'] = trim($eventData['description'] ?? 'Join us for a fun chat!');
        if (strlen($validated['description']) > 500) {
            $validated['description'] = substr($validated['description'], 0, 500);
        }

        // Event type
        $allowedTypes = [self::TYPE_SCHEDULED, self::TYPE_INSTANT, self::TYPE_LOCATION_BASED, self::TYPE_INTEREST_BASED, self::TYPE_THEMED];
        $validated['type'] = in_array($eventData['type'] ?? '', $allowedTypes) ? $eventData['type'] : self::TYPE_INSTANT;

        // Theme
        $validated['theme'] = array_key_exists($eventData['theme'] ?? '', self::THEMES) ? $eventData['theme'] : 'casual_chat';

        // Privacy
        $validated['privacy'] = in_array($eventData['privacy'] ?? '', ['public', 'private']) ? $eventData['privacy'] : 'public';

        // Timing
        if (isset($eventData['start_time'])) {
            $validated['start_time'] = Carbon::parse($eventData['start_time']);
        }
        
        $validated['duration'] = min(max($eventData['duration'] ?? 60, 30), 120); // 30-120 minutes

        // Other fields
        $validated['location_based'] = (bool)($eventData['location_based'] ?? false);
        $validated['interest_matching'] = (bool)($eventData['interest_matching'] ?? true);
        $validated['verified_only'] = (bool)($eventData['verified_only'] ?? false);
        $validated['premium_only'] = (bool)($eventData['premium_only'] ?? false);

        return $validated;
    }

    /**
     * Calculate event timing based on configuration
     */
    private function calculateEventTiming(array $eventData): array
    {
        $now = Carbon::now();
        
        if ($eventData['type'] === self::TYPE_INSTANT || !isset($eventData['start_time'])) {
            // Instant event
            return [
                'starts_immediately' => true,
                'start_time' => $now,
                'end_time' => $now->copy()->addMinutes($eventData['duration']),
                'duration_minutes' => $eventData['duration']
            ];
        }

        // Scheduled event
        $startTime = $eventData['start_time'];
        if ($startTime->isPast()) {
            throw new HappyHourException('Cannot schedule event in the past');
        }

        return [
            'starts_immediately' => false,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes($eventData['duration']),
            'duration_minutes' => $eventData['duration']
        ];
    }

    /**
     * Get maximum participants based on user status
     */
    private function getMaxParticipants(UserId $hostId, array $eventData): int
    {
        $hostProfile = $this->profileRepository->findById($hostId->toInt());
        $isPremium = $hostProfile['premium_status'] ?? false;
        
        $baseLimit = $isPremium ? self::MAX_PARTICIPANTS_PREMIUM : self::MAX_PARTICIPANTS_FREE;
        
        // Adjust based on event type
        return match($eventData['type']) {
            self::TYPE_SPEED_DATING => min($baseLimit, 16), // Even numbers work better
            self::TYPE_LOCATION_BASED => min($baseLimit * 2, 100),
            default => $baseLimit
        };
    }

    /**
     * Validate event access for user
     */
    private function validateEventAccess(string $eventId, UserId $userId): array
    {
        $chatEntity = $this->chatRepository->findById($eventId);
        if (!$chatEntity) {
            throw new EventNotFoundException("Happy Hour event not found: {$eventId}");
        }

        // Convert to array format for compatibility
        $event = [
            'id' => $chatEntity->id()->value(),
            'name' => $chatEntity->name(),
            'description' => $chatEntity->description(),
            'subtype' => 'happy_hour', // This would come from metadata
            'event_config' => [
                'privacy' => 'public' // This would come from settings
            ],
            'members' => [] // This would come from participants
        ];

        // Check if user can access this event
        if ($event['event_config']['privacy'] === 'private' && !isset($event['members'][$userId->toInt()])) {
            throw new UnauthorizedEventAccessException('Event is private and user is not invited');
        }

        return $event;
    }

    /**
     * Validate join eligibility
     */
    private function validateJoinEligibility(array $event, UserId $userId): void
    {
        // Check if already a member
        if (isset($event['members'][$userId->toInt()])) {
            throw new HappyHourException('User is already a participant in this event');
        }

        // Check capacity
        $currentParticipants = count($event['members']);
        if ($currentParticipants >= $event['event_config']['max_participants']) {
            throw new EventCapacityExceededException('Event has reached maximum capacity');
        }

        // Check requirements
        $requirements = $event['requirements'];
        $userProfile = $this->profileRepository->findById($userId->toInt());
        
        if (!$userProfile) {
            throw new HappyHourException('User profile not found');
        }
        
        $userProfileArray = $userProfile->toArray();

        if ($requirements['verified_only'] && !($userProfileArray['verified'] ?? false)) {
            throw new HappyHourException('This event requires verified users only');
        }

        if ($requirements['premium_only'] && !($userProfileArray['premium_status'] ?? false)) {
            throw new HappyHourException('This event is for premium members only');
        }

        // Check profile completion
        $completionScore = $this->calculateProfileCompletion($userProfileArray);
        if ($completionScore < $requirements['min_profile_completion']) {
            throw new HappyHourException('Profile completion requirement not met');
        }

        // Check age compatibility
        $userAge = $userProfileArray['age'] ?? 0;
        $ageRange = $event['event_config']['age_range'];
        if ($userAge < $ageRange['min'] || $userAge > $ageRange['max']) {
            throw new HappyHourException('Age requirements not met for this event');
        }
    }

    /**
     * Initialize event content and activities
     */
    private function initializeEventContent(array $event): void
    {
        // Add welcome message
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "🎉 Welcome to {$event['name']}! " . self::THEMES[$event['event_config']['theme']],
            'metadata' => [
                'message_type' => 'event_welcome',
                'event_theme' => $event['event_config']['theme']
            ]
        ]);

        // Add event rules and guidelines
        $this->addEventGuidelines($event);

        // Start initial activities if event is active
        if ($event['status'] === self::STATUS_ACTIVE || $event['status'] === self::STATUS_STARTING) {
            $this->startEventActivities($event);
        }
    }

    /**
     * Calculate compatibility between event participants
     */
    private function calculateEventCompatibility(array $userProfile, array $participantProfile, array $event): array
    {
        $compatibility = [
            'overall_score' => 0.0,
            'factors' => [],
            'common_interests' => [],
            'age_compatibility' => 0.0,
            'location_compatibility' => 0.0
        ];

        // Interest compatibility
        $userInterests = $userProfile['interests'] ?? [];
        $participantInterests = $participantProfile['interests'] ?? [];
        $commonInterests = array_intersect($userInterests, $participantInterests);
        
        $compatibility['common_interests'] = $commonInterests;
        $interestScore = empty($userInterests) || empty($participantInterests) ? 0.5 : 
            (count($commonInterests) / min(count($userInterests), count($participantInterests)));

        // Age compatibility
        $userAge = $userProfile['age'] ?? 25;
        $participantAge = $participantProfile['age'] ?? 25;
        $ageDiff = abs($userAge - $participantAge);
        $ageScore = max(0, 1 - ($ageDiff * 0.05)); // Penalty for age difference

        // Location compatibility (if available)
        $locationScore = 0.7; // Default neutral score
        if (isset($userProfile['location']) && isset($participantProfile['location'])) {
            // Calculate distance-based compatibility
            $locationScore = 0.8; // Placeholder - would calculate actual distance
        }

        // Event-specific compatibility (theme alignment)
        $themeScore = $this->calculateThemeCompatibility($userProfile, $participantProfile, $event);

        // Calculate overall score
        $compatibility['overall_score'] = ($interestScore * 0.4) + ($ageScore * 0.3) + ($locationScore * 0.2) + ($themeScore * 0.1);
        $compatibility['age_compatibility'] = $ageScore;
        $compatibility['location_compatibility'] = $locationScore;
        $compatibility['factors'] = ['interests', 'age', 'location', 'theme'];

        return $compatibility;
    }

    /**
     * Additional helper methods for event management...
     */
    
    private function scheduleEventStart(array $event): void
    {
        // Schedule event start (would use job queue in production)
        Log::info('Event start scheduled', [
            'event_id' => $event['id'],
            'start_time' => $event['timing']['scheduled_start']
        ]);
    }

    private function startEventActivities(array $event): void
    {
        // Start automated activities for the event
        $enabledActivities = $event['activities']['enabled_activities'] ?? [];
        
        foreach ($enabledActivities as $activity) {
            $this->scheduleActivity($event['id'], $activity);
        }
    }

    private function scheduleActivity(string $eventId, string $activity): void
    {
        Log::info('Activity scheduled', [
            'event_id' => $eventId,
            'activity' => $activity
        ]);
    }

    private function cacheEventData(array $event): void
    {
        Cache::put("happy_hour_event:{$event['id']}", $event, self::CACHE_TTL);
    }

    private function invalidateEventCache(string $eventId): void
    {
        Cache::forget("happy_hour_event:{$eventId}");
    }

    private function trackEventAnalytics(array $event, string $eventType, array $data = []): void
    {
        Log::info("Happy Hour analytics: {$eventType}", array_merge([
            'event_id' => $event['id'],
            'event' => $eventType
        ], $data));
    }

    private function getUserInterests(UserId $userId): array
    {
        $profile = $this->profileRepository->findById($userId->toInt());
        return $profile['interests'] ?? [];
    }

    private function addEventJoinMessage(string $eventId, UserId $userId): void
    {
        $profile = $this->profileRepository->findById($userId->toInt());
        $userName = $profile['name'] ?? 'Someone';
        
        $this->messageRepository->create([
            'chat_id' => $eventId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "🎊 {$userName} joined the Happy Hour! Welcome!",
            'metadata' => [
                'message_type' => 'event_join',
                'user_id' => $userId->toInt()
            ]
        ]);
    }

    private function initializeParticipantActivities(string $eventId, UserId $userId): void
    {
        // Send personalized welcome and activity suggestions
        Log::info('Initializing participant activities', [
            'event_id' => $eventId,
            'user_id' => $userId->toInt()
        ]);
    }

    private function updateEventParticipantCount(string $eventId): void
    {
        $this->chatRepository->updateGroupAnalytics($eventId, [
            'participant_count_updated' => Carbon::now()
        ]);
    }

    private function getCurrentActivities(array $event): array
    {
        // Return currently running activities in the event
        return [
            'active_polls' => [],
            'current_icebreaker' => null,
            'mini_games' => [],
            'discussion_topics' => []
        ];
    }

    private function getEventInfoForParticipant(array $event, UserId $userId): array
    {
        return [
            'event_id' => $event['id'],
            'name' => $event['name'],
            'theme' => self::THEMES[$event['event_config']['theme']],
            'status' => $event['status'],
            'participant_count' => count($event['members']),
            'max_participants' => $event['event_config']['max_participants'],
            'time_remaining' => $this->calculateTimeRemaining($event),
            'user_role' => $event['members'][$userId->toInt()]['role'] ?? 'participant'
        ];
    }

    private function calculateTimeRemaining(array $event): ?int
    {
        if ($event['status'] === self::STATUS_ACTIVE && isset($event['timing']['scheduled_end'])) {
            return (int) Carbon::parse($event['timing']['scheduled_end'])->diffInMinutes(Carbon::now());
        }
        return null;
    }

    // Additional methods for activities, compatibility, etc. would be implemented here...

    private function startIcebreakerActivity(array $event, UserId $userId, array $data): array
    {
        $questions = [
            "What's the most interesting place you've ever visited?",
            "If you could have dinner with anyone, who would it be?",
            "What's a skill you've always wanted to learn?",
            "What's your favorite way to spend a weekend?"
        ];
        
        $randomQuestion = $questions[array_rand($questions)];
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'icebreaker',
            'content' => "🧊 Icebreaker: {$randomQuestion}",
            'metadata' => [
                'activity_type' => 'icebreaker',
                'question' => $randomQuestion,
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'icebreaker', 'question' => $randomQuestion];
    }

    private function personalizeEventForUser(array $event, array $userProfile, UserId $userId): array
    {
        // Calculate recommendation score based on user preferences
        $event['recommendation_score'] = $this->calculateEventRecommendationScore($event, $userProfile);
        
        // Add compatibility with existing participants
        $event['participant_compatibility'] = $this->calculateParticipantCompatibility($event, $userProfile);
        
        return $event;
    }

    private function calculateEventRecommendationScore(array $event, array $userProfile): float
    {
        $score = 0.5; // Base score
        
        // Interest alignment
        $userInterests = $userProfile['interests'] ?? [];
        $eventTheme = $event['event_config']['theme'];
        
        // Theme-based scoring (simplified)
        $themeInterestMap = [
            'hobby_lovers' => ['hobbies', 'crafts', 'diy'],
            'foodies' => ['cooking', 'food', 'dining'],
            'travelers' => ['travel', 'adventure', 'culture'],
            'fitness' => ['fitness', 'health', 'sports']
        ];
        
        if (isset($themeInterestMap[$eventTheme])) {
            $themeInterests = $themeInterestMap[$eventTheme];
            $commonCount = count(array_intersect($userInterests, $themeInterests));
            $score += min(0.3, $commonCount * 0.1);
        }
        
        // Time compatibility (prefer events starting soon)
        if ($event['status'] === self::STATUS_ACTIVE) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }

    private function calculateParticipantCompatibility(array $event, array $userProfile): float
    {
        $participants = collect($event['members'])->keys();
        if ($participants->isEmpty()) {
            return 0.5;
        }
        
        $compatibilityScores = [];
        foreach ($participants as $participantId) {
            $participantProfile = $this->profileRepository->findById($participantId);
            if ($participantProfile) {
                $compatibility = $this->calculateEventCompatibility($userProfile, $participantProfile->toArray(), $event);
                $compatibilityScores[] = $compatibility['overall_score'];
            }
        }
        
        return empty($compatibilityScores) ? 0.5 : array_sum($compatibilityScores) / count($compatibilityScores);
    }

    private function enrichEventWithRealTimeData(array $event): array
    {
        // Add real-time participant count, activity status, etc.
        $event['live_participant_count'] = count($event['members']);
        $event['current_activity'] = null; // Would check for ongoing activities
        $event['estimated_wait_time'] = $this->estimateJoinWaitTime($event);
        
        return $event;
    }

    private function estimateJoinWaitTime(array $event): int
    {
        // Estimate how long until user can actively participate
        return 0; // Immediate participation
    }

    /**
     * ========================================
     * MISSING HELPER METHODS
     * ========================================
     */

    /**
     * Calculate age compatibility for user profile
     */
    private function calculateAgeCompatibility(array $userProfile): array
    {
        $userAge = $userProfile['age'] ?? 25;
        return [
            'min' => max(18, $userAge - 5),
            'max' => min(100, $userAge + 10),
            'preferred_range' => [$userAge - 3, $userAge + 3]
        ];
    }

    /**
     * Calculate user event statistics
     */
    private function calculateUserEventStatistics(UserId $userId, Collection $eventHistory): array
    {
        $totalEvents = $eventHistory->count();
        $completedEvents = $eventHistory->where('status', self::STATUS_ENDED)->count();
        $averageEngagement = $eventHistory->avg('engagement_score') ?? 0.0;
        
        return [
            'total_events' => $totalEvents,
            'completed_events' => $completedEvents,
            'completion_rate' => $totalEvents > 0 ? $completedEvents / $totalEvents : 0.0,
            'average_engagement' => $averageEngagement,
            'favorite_themes' => $eventHistory->pluck('event_config.theme')->mode() ?? []
        ];
    }

    /**
     * Get user event achievements
     */
    private function getUserEventAchievements(UserId $userId, array $statistics): array
    {
        $achievements = [];
        
        if ($statistics['total_events'] >= 10) {
            $achievements[] = 'social_butterfly';
        }
        if ($statistics['completion_rate'] >= 0.8) {
            $achievements[] = 'dedicated_participant';
        }
        if ($statistics['average_engagement'] >= 0.7) {
            $achievements[] = 'high_engagement';
        }
        
        return $achievements;
    }

    /**
     * Get personalized event recommendations
     */
    private function getPersonalizedEventRecommendations(UserId $userId, array $statistics): array
    {
        // Return recommendations based on user history and preferences
        return [
            'themes' => $statistics['favorite_themes'] ?? ['casual_chat'],
            'timing' => 'evening',
            'group_size' => 'medium'
        ];
    }

    /**
     * Validate activity permissions for user
     */
    private function validateActivityPermissions(array $event, UserId $userId, string $activityType): void
    {
        $userRole = $event['members'][$userId->toInt()]['role'] ?? 'participant';
        
        // Host can start any activity
        if ($userRole === 'host') {
            return;
        }
        
        // Participants can start certain activities
        $allowedActivities = ['icebreaker_questions', 'topic_discussions'];
        if (!in_array($activityType, $allowedActivities)) {
            throw new HappyHourException("Permission denied for activity: {$activityType}");
        }
    }

    /**
     * Start speed introductions activity
     */
    private function startSpeedIntroductions(array $event, UserId $userId, array $data): array
    {
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'activity',
            'content' => "⚡ Speed Introductions: Each person has 30 seconds to introduce themselves!",
            'metadata' => [
                'activity_type' => 'speed_introductions',
                'duration' => 300, // 5 minutes
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'speed_introductions', 'duration' => 300];
    }

    /**
     * Start compatibility quiz activity
     */
    private function startCompatibilityQuiz(array $event, UserId $userId, array $data): array
    {
        $questions = [
            "What's your ideal first date?",
            "How do you prefer to spend weekends?",
            "What's most important in a relationship?"
        ];
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'activity',
            'content' => "💕 Compatibility Quiz: Let's discover how compatible we are!",
            'metadata' => [
                'activity_type' => 'compatibility_quiz',
                'questions' => $questions,
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'compatibility_quiz', 'questions' => $questions];
    }

    /**
     * Start topic discussion activity
     */
    private function startTopicDiscussion(array $event, UserId $userId, array $data): array
    {
        $topics = [
            "Travel experiences",
            "Favorite books or movies",
            "Life goals and dreams",
            "Hobbies and interests"
        ];
        
        $randomTopic = $topics[array_rand($topics)];
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'activity',
            'content' => "🗣️ Discussion Topic: {$randomTopic} - Let's share our thoughts!",
            'metadata' => [
                'activity_type' => 'topic_discussion',
                'topic' => $randomTopic,
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'topic_discussion', 'topic' => $randomTopic];
    }

    /**
     * Start poll activity
     */
    private function startPoll(array $event, UserId $userId, array $data): array
    {
        $pollQuestion = $data['question'] ?? "What's your favorite season?";
        $pollOptions = $data['options'] ?? ['Spring', 'Summer', 'Fall', 'Winter'];
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'poll',
            'content' => "📊 Poll: {$pollQuestion}",
            'metadata' => [
                'activity_type' => 'poll',
                'question' => $pollQuestion,
                'options' => $pollOptions,
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'poll', 'question' => $pollQuestion, 'options' => $pollOptions];
    }

    /**
     * Start mini game activity
     */
    private function startMiniGame(array $event, UserId $userId, array $data): array
    {
        $gameType = $data['game_type'] ?? 'trivia';
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'activity',
            'content' => "🎮 Mini Game: Let's play {$gameType}!",
            'metadata' => [
                'activity_type' => 'mini_game',
                'game_type' => $gameType,
                'started_by' => $userId->toInt()
            ]
        ]);
        
        return ['type' => 'mini_game', 'game_type' => $gameType];
    }

    /**
     * Update participant engagement
     */
    private function updateParticipantEngagement(string $eventId, UserId $userId, string $activityType, array $result): void
    {
        // Update engagement score based on activity participation
        $engagementIncrease = match($activityType) {
            'icebreaker_questions' => 0.1,
            'speed_introductions' => 0.15,
            'compatibility_quiz' => 0.2,
            'topic_discussions' => 0.1,
            'polls_and_votes' => 0.05,
            'mini_games' => 0.12,
            default => 0.05
        };
        
        // Log engagement update (would update in database in production)
        Log::info('Participant engagement updated', [
            'event_id' => $eventId,
            'user_id' => $userId->toInt(),
            'activity_type' => $activityType,
            'engagement_increase' => $engagementIncrease
        ]);
    }

    /**
     * Generate conversation starters based on compatibility
     */
    private function generateConversationStarters(array $compatibility): array
    {
        $starters = [];
        
        if (!empty($compatibility['common_interests'])) {
            $interest = $compatibility['common_interests'][0];
            $starters[] = "I see you're also interested in {$interest}!";
        }
        
        if ($compatibility['age_compatibility'] > 0.8) {
            $starters[] = "We seem to be around the same age group!";
        }
        
        $starters[] = "What brings you to this event?";
        $starters[] = "Have you been to events like this before?";
        
        return $starters;
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(array $userProfile): float
    {
        $requiredFields = ['name', 'age', 'bio', 'interests', 'location'];
        $completedFields = 0;
        
        foreach ($requiredFields as $field) {
            if (!empty($userProfile[$field])) {
                $completedFields++;
            }
        }
        
        return $completedFields / count($requiredFields);
    }

    /**
     * Add event guidelines message
     */
    private function addEventGuidelines(array $event): void
    {
        $guidelines = [
            "📋 Event Guidelines:",
            "• Be respectful and kind to everyone",
            "• Keep conversations appropriate",
            "• Participate in activities when possible",
            "• Have fun and make connections!"
        ];
        
        $this->messageRepository->create([
            'chat_id' => $event['id'],
            'sender_id' => 'system',
            'type' => 'system',
            'content' => implode("\n", $guidelines),
            'metadata' => [
                'message_type' => 'event_guidelines'
            ]
        ]);
    }

    /**
     * Calculate theme compatibility between profiles
     */
    private function calculateThemeCompatibility(array $userProfile, array $participantProfile, array $event): float
    {
        $eventTheme = $event['event_config']['theme'] ?? 'casual_chat';
        
        $userInterests = $userProfile['interests'] ?? [];
        $participantInterests = $participantProfile['interests'] ?? [];
        
        // Simple theme compatibility based on common interests
        $commonInterests = array_intersect($userInterests, $participantInterests);
        $interestScore = empty($userInterests) || empty($participantInterests) ? 0.5 : 
            (count($commonInterests) / min(count($userInterests), count($participantInterests)));
        
        return $interestScore;
    }
}