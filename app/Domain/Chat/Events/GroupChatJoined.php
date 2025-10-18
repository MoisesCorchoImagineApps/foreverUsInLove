<?php

declare(strict_types=1);

namespace App\Domain\Chat\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Group Chat Joined Event
 * 
 * Comprehensive event for real-time group chat participation notifications,
 * member management, engagement tracking, and social interaction triggers.
 * 
 * @package App\Domain\Chat\Events
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Domain Events with Laravel Broadcasting
 * 
 * Key Features:
 * - Real-time member join notifications
 * - Group presence and activity updates
 * - Social matching and recommendation triggers
 * - Engagement analytics and tracking
 * - Welcome automation and onboarding
 * - Privacy and permission management
 * - Happy Hour event integration
 * - Gamification and achievement triggers
 * - Anti-spam and moderation checks
 * - Cross-platform synchronization
 */
class GroupChatJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event data
     */
    public readonly string $groupId;
    public readonly UserId $userId;
    public readonly string $memberRole;
    public readonly array $joinOptions;
    public readonly Carbon $timestamp;

    /**
     * Broadcasting configuration
     */
    private array $broadcastChannels = [];
    private array $memberData = [];
    private array $groupContext = [];
    private array $engagementTriggers = [];
    private array $analyticsData = [];

    /**
     * Create a new event instance
     * 
     * @param string $groupId Group chat identifier
     * @param UserId $userId User who joined the group
     * @param string $memberRole Role assigned to the new member
     * @param array $joinOptions Join configuration and context
     */
    public function __construct(
        string $groupId,
        UserId $userId,
        string $memberRole,
        array $joinOptions = []
    ) {
        $this->groupId = $groupId;
        $this->userId = $userId;
        $this->memberRole = $memberRole;
        $this->joinOptions = $joinOptions;
        $this->timestamp = Carbon::now();

        // Initialize member and group context
        $this->initializeMemberData();
        $this->initializeGroupContext();

        // Initialize broadcasting configuration
        $this->initializeBroadcastingConfig();

        // Prepare engagement triggers
        $this->prepareEngagementTriggers();

        // Prepare analytics data
        $this->prepareAnalyticsData();

        Log::info('GroupChatJoined event created', [
            'group_id' => $this->groupId,
            'user_id' => $this->userId->toInt(),
            'role' => $this->memberRole,
            'group_type' => $this->getGroupType()
        ]);
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return $this->broadcastChannels;
    }

    /**
     * Get the data to broadcast
     * 
     * @return array Broadcast payload
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'group_chat.member_joined',
            'group' => [
                'id' => $this->groupId,
                'name' => $this->getGroupName(),
                'type' => $this->getGroupType(),
                'theme' => $this->getGroupTheme(),
                'member_count' => $this->getCurrentMemberCount(),
                'max_members' => $this->getMaxMembers(),
                'privacy' => $this->getGroupPrivacy(),
                'activity_level' => $this->getActivityLevel()
            ],
            'member' => [
                'user_id' => $this->userId->toInt(),
                'name' => $this->getMemberName(),
                'avatar' => $this->getMemberAvatar(),
                'role' => $this->memberRole,
                'joined_at' => $this->timestamp->toISOString(),
                'invited_by' => $this->getInvitedBy(),
                'join_method' => $this->getJoinMethod(),
                'member_number' => $this->getMemberNumber(),
                'first_time_member' => $this->isFirstTimeGroupMember()
            ],
            'welcome' => [
                'welcome_message' => $this->getWelcomeMessage(),
                'group_rules' => $this->getGroupRules(),
                'introduction_prompt' => $this->getIntroductionPrompt(),
                'icebreaker_questions' => $this->getIcebreakerQuestions(),
                'member_highlights' => $this->getMemberHighlights()
            ],
            'interactions' => [
                'suggested_connections' => $this->getSuggestedConnections(),
                'conversation_starters' => $this->getConversationStarters(),
                'shared_interests' => $this->getSharedInterests(),
                'compatibility_scores' => $this->getCompatibilityScores(),
                'mutual_connections' => $this->getMutualConnections()
            ],
            'engagement' => [
                'activities_available' => $this->getAvailableActivities(),
                'challenges_active' => $this->getActiveChallenges(),
                'polls_open' => $this->getOpenPolls(),
                'events_upcoming' => $this->getUpcomingEvents(),
                'achievement_opportunities' => $this->getAchievementOpportunities()
            ],
            'notifications' => [
                'notify_existing_members' => $this->shouldNotifyExistingMembers(),
                'announcement_type' => $this->getAnnouncementType(),
                'celebration_level' => $this->getCelebrationLevel(),
                'milestone_reached' => $this->getMilestoneReached(),
                'special_recognition' => $this->getSpecialRecognition()
            ],
            'permissions' => [
                'can_invite_others' => $this->canInviteOthers(),
                'can_post_messages' => $this->canPostMessages(),
                'can_share_media' => $this->canShareMedia(),
                'can_create_polls' => $this->canCreatePolls(),
                'can_start_activities' => $this->canStartActivities(),
                'moderation_level' => $this->getModerationLevel()
            ],
            'context' => [
                'join_reason' => $this->getJoinReason(),
                'referral_source' => $this->getReferralSource(),
                'onboarding_stage' => $this->getOnboardingStage(),
                'user_experience_level' => $this->getUserExperienceLevel(),
                'group_familiarity' => $this->getGroupFamiliarity()
            ]
        ];
    }

    /**
     * Get broadcast event name
     * 
     * @return string Event name
     */
    public function broadcastAs(): string
    {
        return 'group_chat.member_joined';
    }

    /**
     * Determine if the event should be queued
     * 
     * @return bool Whether to queue the broadcast
     */
    public function shouldQueue(): bool
    {
        // Group join events should broadcast immediately for real-time experience
        return false;
    }

    /**
     * Get event tags for queue monitoring
     * 
     * @return array Queue tags
     */
    public function tags(): array
    {
        return [
            'group:' . $this->groupId,
            'user:' . $this->userId->toInt(),
            'role:' . $this->memberRole,
            'type:' . $this->getGroupType()
        ];
    }

    /**
     * ========================================
     * EVENT DATA GETTERS
     * ========================================
     */

    /**
     * Get group ID
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * Get user ID
     */
    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * Get member role
     */
    public function getMemberRole(): string
    {
        return $this->memberRole;
    }

    /**
     * Get join options
     */
    public function getJoinOptions(): array
    {
        return $this->joinOptions;
    }

    /**
     * Get event timestamp
     */
    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    /**
     * Get member data
     */
    public function getMemberData(): array
    {
        return $this->memberData;
    }

    /**
     * Get group context
     */
    public function getGroupContext(): array
    {
        return $this->groupContext;
    }

    /**
     * Get engagement triggers
     */
    public function getEngagementTriggers(): array
    {
        return $this->engagementTriggers;
    }

    /**
     * Get analytics data
     */
    public function getAnalyticsData(): array
    {
        return $this->analyticsData;
    }

    /**
     * ========================================
     * GROUP CONTEXT GETTERS
     * ========================================
     */

    /**
     * Get group name
     */
    public function getGroupName(): string
    {
        return $this->groupContext['name'] ?? 'Unknown Group';
    }

    /**
     * Get group type
     */
    public function getGroupType(): string
    {
        return $this->groupContext['type'] ?? 'general';
    }

    /**
     * Get group subtype (happy_hour, themed, etc.)
     */
    public function getGroupSubtype(): string
    {
        return $this->groupContext['subtype'] ?? 'general';
    }

    /**
     * Get group theme
     */
    public function getGroupTheme(): string
    {
        return $this->groupContext['theme'] ?? 'default';
    }

    /**
     * Get current member count
     */
    public function getCurrentMemberCount(): int
    {
        return $this->groupContext['member_count'] ?? 1;
    }

    /**
     * Get maximum members allowed
     */
    public function getMaxMembers(): int
    {
        return $this->groupContext['max_members'] ?? 50;
    }

    /**
     * Get group privacy setting
     */
    public function getGroupPrivacy(): string
    {
        return $this->groupContext['privacy'] ?? 'public';
    }

    /**
     * Get group activity level
     */
    public function getActivityLevel(): string
    {
        return $this->groupContext['activity_level'] ?? 'medium';
    }

    /**
     * Check if this is a Happy Hour event group
     */
    public function isHappyHourGroup(): bool
    {
        return $this->getGroupSubtype() === 'happy_hour';
    }

    /**
     * Check if this is a themed group
     */
    public function isThemedGroup(): bool
    {
        return $this->getGroupSubtype() === 'themed';
    }

    /**
     * Check if this is a location-based group
     */
    public function isLocationBasedGroup(): bool
    {
        return $this->groupContext['location_based'] ?? false;
    }

    /**
     * ========================================
     * MEMBER CONTEXT GETTERS
     * ========================================
     */

    /**
     * Get member display name
     */
    public function getMemberName(): string
    {
        return $this->memberData['name'] ?? 'Unknown User';
    }

    /**
     * Get member avatar URL
     */
    public function getMemberAvatar(): ?string
    {
        return $this->memberData['avatar'] ?? null;
    }

    /**
     * Get who invited this member
     */
    public function getInvitedBy(): ?string
    {
        return $this->joinOptions['invited_by'] ?? null;
    }

    /**
     * Get join method (invite, discovery, link, etc.)
     */
    public function getJoinMethod(): string
    {
        return $this->joinOptions['join_method'] ?? 'manual';
    }

    /**
     * Get member number in group (1st, 2nd, etc.)
     */
    public function getMemberNumber(): int
    {
        return $this->getCurrentMemberCount();
    }

    /**
     * Check if this is user's first time joining any group
     */
    public function isFirstTimeGroupMember(): bool
    {
        return $this->memberData['first_time_group_member'] ?? false;
    }

    /**
     * Check if member is verified
     */
    public function isMemberVerified(): bool
    {
        return $this->memberData['verified'] ?? false;
    }

    /**
     * Check if member has premium status
     */
    public function isMemberPremium(): bool
    {
        return $this->memberData['premium_status'] ?? false;
    }

    /**
     * ========================================
     * WELCOME AND ONBOARDING GETTERS
     * ========================================
     */

    /**
     * Get personalized welcome message
     */
    public function getWelcomeMessage(): string
    {
        $name = $this->getMemberName();
        $groupName = $this->getGroupName();
        
        if ($this->isHappyHourGroup()) {
            return "🎉 Welcome to {$groupName}, {$name}! Let's make some connections and have fun!";
        }
        
        if ($this->isThemedGroup()) {
            $theme = $this->getGroupTheme();
            return "👋 Hey {$name}! Welcome to our {$theme} community in {$groupName}!";
        }
        
        return "Welcome to {$groupName}, {$name}! We're excited to have you here.";
    }

    /**
     * Get group rules summary
     */
    public function getGroupRules(): array
    {
        return [
            'Be respectful and kind to all members',
            'Keep conversations relevant to the group topic',
            'No spam or inappropriate content',
            'Respect privacy and boundaries',
            'Have fun and make meaningful connections!'
        ];
    }

    /**
     * Get introduction prompt for new member
     */
    public function getIntroductionPrompt(): string
    {
        if ($this->isHappyHourGroup()) {
            return "Tell everyone a fun fact about yourself to break the ice! 🧊";
        }
        
        return "Introduce yourself to the group! Share what brings you here and what you're interested in.";
    }

    /**
     * Get icebreaker questions
     */
    public function getIcebreakerQuestions(): array
    {
        $questions = [
            "What's your favorite way to spend a weekend?",
            "If you could travel anywhere right now, where would you go?",
            "What's a hobby you've always wanted to try?",
            "What's the best advice you've ever received?"
        ];
        
        if ($this->isHappyHourGroup()) {
            $questions = array_merge($questions, [
                "What's your go-to conversation starter?",
                "What would be your perfect first date?",
                "What's something that always makes you smile?"
            ]);
        }
        
        return array_slice($questions, 0, 3); // Return 3 random questions
    }

    /**
     * Get highlights of existing members
     */
    public function getMemberHighlights(): array
    {
        return [
            'active_members' => $this->getActiveMemberCount(),
            'featured_members' => $this->getFeaturedMembers(),
            'member_diversity' => $this->getMemberDiversityStats(),
            'success_stories' => $this->getGroupSuccessStories()
        ];
    }

    /**
     * ========================================
     * SOCIAL INTERACTION GETTERS
     * ========================================
     */

    /**
     * Get suggested connections for the new member
     */
    public function getSuggestedConnections(): array
    {
        return $this->joinOptions['suggested_connections'] ?? [];
    }

    /**
     * Get conversation starters relevant to the group
     */
    public function getConversationStarters(): array
    {
        $starters = [
            "What brought everyone to this group?",
            "Has anyone tried the group activities yet?",
            "What's everyone up to this weekend?"
        ];
        
        if ($this->isHappyHourGroup()) {
            $starters = [
                "What's everyone's ideal date night?",
                "Any fun plans for the week?",
                "What's your best dating story?"
            ];
        }
        
        return $starters;
    }

    /**
     * Get shared interests with existing members
     */
    public function getSharedInterests(): array
    {
        return $this->memberData['shared_interests'] ?? [];
    }

    /**
     * Get compatibility scores with existing members
     */
    public function getCompatibilityScores(): array
    {
        return $this->memberData['compatibility_scores'] ?? [];
    }

    /**
     * Get mutual connections with existing members
     */
    public function getMutualConnections(): array
    {
        return $this->memberData['mutual_connections'] ?? [];
    }

    /**
     * ========================================
     * ENGAGEMENT AND ACTIVITY GETTERS
     * ========================================
     */

    /**
     * Get available activities in the group
     */
    public function getAvailableActivities(): array
    {
        $activities = ['group_chat', 'member_introductions', 'polls'];
        
        if ($this->isHappyHourGroup()) {
            $activities = array_merge($activities, [
                'speed_introductions',
                'icebreaker_games',
                'compatibility_quiz',
                'group_challenges'
            ]);
        }
        
        return $activities;
    }

    /**
     * Get active challenges in the group
     */
    public function getActiveChallenges(): array
    {
        return $this->groupContext['active_challenges'] ?? [];
    }

    /**
     * Get open polls in the group
     */
    public function getOpenPolls(): array
    {
        return $this->groupContext['open_polls'] ?? [];
    }

    /**
     * Get upcoming events in the group
     */
    public function getUpcomingEvents(): array
    {
        return $this->groupContext['upcoming_events'] ?? [];
    }

    /**
     * Get achievement opportunities for the new member
     */
    public function getAchievementOpportunities(): array
    {
        return [
            'first_group_join' => $this->isFirstTimeGroupMember(),
            'group_ambassador' => $this->canBecomeGroupAmbassador(),
            'conversation_starter' => true,
            'welcome_wagon' => $this->canWelcomeOthers()
        ];
    }

    /**
     * ========================================
     * NOTIFICATION AND ANNOUNCEMENT GETTERS
     * ========================================
     */

    /**
     * Check if existing members should be notified
     */
    public function shouldNotifyExistingMembers(): bool
    {
        return $this->joinOptions['notify_members'] ?? true;
    }

    /**
     * Get announcement type
     */
    public function getAnnouncementType(): string
    {
        if ($this->isFirstTimeGroupMember()) {
            return 'new_user_celebration';
        }
        
        if ($this->isMemberVerified()) {
            return 'verified_member_joined';
        }
        
        return 'standard_join';
    }

    /**
     * Get celebration level
     */
    public function getCelebrationLevel(): string
    {
        $memberCount = $this->getCurrentMemberCount();
        
        if ($memberCount === 1) {
            return 'group_creation';
        } elseif ($memberCount % 10 === 0) {
            return 'milestone';
        } elseif ($this->isFirstTimeGroupMember()) {
            return 'new_user';
        }
        
        return 'standard';
    }

    /**
     * Get milestone reached (if any)
     */
    public function getMilestoneReached(): ?array
    {
        $memberCount = $this->getCurrentMemberCount();
        
        if (in_array($memberCount, [10, 25, 50, 100])) {
            return [
                'type' => 'member_count',
                'value' => $memberCount,
                'message' => "🎉 We've reached {$memberCount} members!"
            ];
        }
        
        return null;
    }

    /**
     * Get special recognition for the new member
     */
    public function getSpecialRecognition(): ?array
    {
        if ($this->isMemberVerified() && $this->isMemberPremium()) {
            return [
                'type' => 'vip_member',
                'badge' => '⭐ VIP Member',
                'message' => 'Welcome our verified premium member!'
            ];
        }
        
        if ($this->isFirstTimeGroupMember()) {
            return [
                'type' => 'first_timer',
                'badge' => '🎈 New to Groups',
                'message' => 'Let\'s give them a warm welcome!'
            ];
        }
        
        return null;
    }

    /**
     * ========================================
     * PERMISSION AND ROLE GETTERS
     * ========================================
     */

    /**
     * Check if member can invite others
     */
    public function canInviteOthers(): bool
    {
        return in_array($this->memberRole, ['owner', 'admin', 'moderator']) ||
               $this->groupContext['allow_member_invites'] ?? false;
    }

    /**
     * Check if member can post messages
     */
    public function canPostMessages(): bool
    {
        return !($this->groupContext['admin_only_messaging'] ?? false) ||
               in_array($this->memberRole, ['owner', 'admin', 'moderator']);
    }

    /**
     * Check if member can share media
     */
    public function canShareMedia(): bool
    {
        return $this->groupContext['allow_media_sharing'] ?? true;
    }

    /**
     * Check if member can create polls
     */
    public function canCreatePolls(): bool
    {
        return in_array($this->memberRole, ['owner', 'admin', 'moderator']) ||
               $this->groupContext['allow_member_polls'] ?? false;
    }

    /**
     * Check if member can start activities
     */
    public function canStartActivities(): bool
    {
        return in_array($this->memberRole, ['owner', 'admin', 'moderator']) ||
               ($this->isHappyHourGroup() && $this->groupContext['allow_member_activities'] ?? true);
    }

    /**
     * Get moderation level for the member
     */
    public function getModerationLevel(): string
    {
        if (in_array($this->memberRole, ['owner', 'admin'])) {
            return 'none';
        } elseif ($this->memberRole === 'moderator') {
            return 'light';
        } elseif ($this->isMemberVerified()) {
            return 'light';
        }
        
        return 'standard';
    }

    /**
     * ========================================
     * CONTEXT AND ANALYTICS GETTERS
     * ========================================
     */

    /**
     * Get join reason
     */
    public function getJoinReason(): string
    {
        return $this->joinOptions['reason'] ?? 'discovery';
    }

    /**
     * Get referral source
     */
    public function getReferralSource(): string
    {
        return $this->joinOptions['referral_source'] ?? 'organic';
    }

    /**
     * Get onboarding stage
     */
    public function getOnboardingStage(): string
    {
        return $this->joinOptions['onboarding_stage'] ?? 'new_user';
    }

    /**
     * Get user experience level with the platform
     */
    public function getUserExperienceLevel(): string
    {
        return $this->memberData['experience_level'] ?? 'beginner';
    }

    /**
     * Get group familiarity level
     */
    public function getGroupFamiliarity(): string
    {
        if ($this->getInvitedBy()) {
            return 'invited';
        } elseif ($this->getMutualConnections()) {
            return 'has_connections';
        } elseif ($this->getSharedInterests()) {
            return 'shared_interests';
        }
        
        return 'newcomer';
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Initialize member data
     */
    private function initializeMemberData(): void
    {
        $this->memberData = [
            'name' => $this->joinOptions['member_name'] ?? 'Unknown User',
            'avatar' => $this->joinOptions['member_avatar'] ?? null,
            'verified' => $this->joinOptions['member_verified'] ?? false,
            'premium_status' => $this->joinOptions['member_premium'] ?? false,
            'experience_level' => $this->joinOptions['experience_level'] ?? 'beginner',
            'first_time_group_member' => $this->joinOptions['first_time_group_member'] ?? false,
            'shared_interests' => $this->joinOptions['shared_interests'] ?? [],
            'compatibility_scores' => $this->joinOptions['compatibility_scores'] ?? [],
            'mutual_connections' => $this->joinOptions['mutual_connections'] ?? []
        ];
    }

    /**
     * Initialize group context
     */
    private function initializeGroupContext(): void
    {
        $this->groupContext = [
            'name' => $this->joinOptions['group_name'] ?? 'Unknown Group',
            'type' => $this->joinOptions['group_type'] ?? 'general',
            'subtype' => $this->joinOptions['group_subtype'] ?? 'general',
            'theme' => $this->joinOptions['group_theme'] ?? 'default',
            'privacy' => $this->joinOptions['group_privacy'] ?? 'public',
            'member_count' => $this->joinOptions['member_count'] ?? 1,
            'max_members' => $this->joinOptions['max_members'] ?? 50,
            'activity_level' => $this->joinOptions['activity_level'] ?? 'medium',
            'location_based' => $this->joinOptions['location_based'] ?? false,
            'allow_member_invites' => $this->joinOptions['allow_member_invites'] ?? false,
            'allow_media_sharing' => $this->joinOptions['allow_media_sharing'] ?? true,
            'allow_member_polls' => $this->joinOptions['allow_member_polls'] ?? false,
            'allow_member_activities' => $this->joinOptions['allow_member_activities'] ?? true,
            'admin_only_messaging' => $this->joinOptions['admin_only_messaging'] ?? false,
            'active_challenges' => $this->joinOptions['active_challenges'] ?? [],
            'open_polls' => $this->joinOptions['open_polls'] ?? [],
            'upcoming_events' => $this->joinOptions['upcoming_events'] ?? []
        ];
    }

    /**
     * Initialize broadcasting configuration
     */
    private function initializeBroadcastingConfig(): void
    {
        // Main group channel for member updates
        $this->broadcastChannels[] = new PrivateChannel("group.{$this->groupId}");

        // Group presence channel
        $this->broadcastChannels[] = new PresenceChannel("group.{$this->groupId}.presence");

        // Individual member notification channels
        $existingMembers = $this->joinOptions['existing_member_ids'] ?? [];
        foreach ($existingMembers as $memberId) {
            if ($memberId !== $this->userId->toInt()) {
                $this->broadcastChannels[] = new PrivateChannel("user.{$memberId}.group_notifications");
            }
        }

        // New member's personal channel
        $this->broadcastChannels[] = new PrivateChannel("user.{$this->userId->toInt()}.group_updates");

        // Happy Hour specific channels
        if ($this->isHappyHourGroup()) {
            $this->broadcastChannels[] = new Channel("happy_hour.{$this->groupId}.activity");
            $this->broadcastChannels[] = new Channel('happy_hour.global.member_joins');
        }

        // Analytics and engagement channels
        $this->broadcastChannels[] = new Channel('analytics.group_joins');
        $this->broadcastChannels[] = new Channel('engagement.group_activity');

        // Moderation channel for suspicious activity monitoring
        $this->broadcastChannels[] = new PrivateChannel('admin.group_monitoring');
    }

    /**
     * Prepare engagement triggers
     */
    private function prepareEngagementTriggers(): void
    {
        $this->engagementTriggers = [
            'welcome_automation' => true,
            'introduction_prompt' => $this->isFirstTimeGroupMember(),
            'icebreaker_questions' => $this->isHappyHourGroup(),
            'member_matching' => $this->getCurrentMemberCount() > 1,
            'activity_suggestions' => true,
            'achievement_unlock' => $this->isFirstTimeGroupMember(),
            'milestone_celebration' => $this->getMilestoneReached() !== null,
            'conversation_starters' => true,
            'onboarding_flow' => $this->getUserExperienceLevel() === 'beginner'
        ];
    }

    /**
     * Prepare analytics data
     */
    private function prepareAnalyticsData(): void
    {
        $this->analyticsData = [
            'event_type' => 'group_chat_joined',
            'group_id' => $this->groupId,
            'user_id' => $this->userId->toInt(),
            'member_role' => $this->memberRole,
            'group_type' => $this->getGroupType(),
            'group_subtype' => $this->getGroupSubtype(),
            'is_happy_hour_group' => $this->isHappyHourGroup(),
            'is_themed_group' => $this->isThemedGroup(),
            'is_location_based' => $this->isLocationBasedGroup(),
            'member_count_before' => $this->getCurrentMemberCount() - 1,
            'member_count_after' => $this->getCurrentMemberCount(),
            'join_method' => $this->getJoinMethod(),
            'invited_by' => $this->getInvitedBy(),
            'referral_source' => $this->getReferralSource(),
            'member_verified' => $this->isMemberVerified(),
            'member_premium' => $this->isMemberPremium(),
            'first_time_group_member' => $this->isFirstTimeGroupMember(),
            'user_experience_level' => $this->getUserExperienceLevel(),
            'group_familiarity' => $this->getGroupFamiliarity(),
            'shared_interests_count' => count($this->getSharedInterests()),
            'mutual_connections_count' => count($this->getMutualConnections()),
            'milestone_reached' => $this->getMilestoneReached() !== null,
            'celebration_level' => $this->getCelebrationLevel(),
            'timestamp' => $this->timestamp->toISOString(),
            'platform' => $this->joinOptions['platform'] ?? 'web',
            'device_info' => $this->joinOptions['device_info'] ?? 'unknown',
            'join_duration_seconds' => $this->joinOptions['join_duration'] ?? null,
            'onboarding_completed' => $this->joinOptions['onboarding_completed'] ?? false
        ];
    }

    /**
     * Additional helper methods
     */
    private function getActiveMemberCount(): int
    {
        return $this->groupContext['active_member_count'] ?? $this->getCurrentMemberCount();
    }

    private function getFeaturedMembers(): array
    {
        return $this->groupContext['featured_members'] ?? [];
    }

    private function getMemberDiversityStats(): array
    {
        return $this->groupContext['member_diversity'] ?? [];
    }

    private function getGroupSuccessStories(): array
    {
        return $this->groupContext['success_stories'] ?? [];
    }

    private function canBecomeGroupAmbassador(): bool
    {
        return $this->isMemberVerified() && $this->getUserExperienceLevel() !== 'beginner';
    }

    private function canWelcomeOthers(): bool
    {
        return !$this->isFirstTimeGroupMember() && $this->isMemberVerified();
    }
}