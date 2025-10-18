<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Events\GroupChatJoined;
use App\Domain\Chat\Events\MessageSent;
use App\Domain\Chat\Exceptions\GroupChatException;
use App\Domain\Chat\Exceptions\UnauthorizedGroupAccessException;
use App\Domain\Chat\Exceptions\GroupNotFoundException;
use App\Domain\Chat\Exceptions\GroupLimitExceededException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Group Chat Functionality Service
 * 
 * Comprehensive service for managing group chats, member management,
 * moderation, and group-specific features following Clean Architecture principles.
 * 
 * @package App\Domain\Chat\Services
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Service Layer Pattern with Domain-Driven Design
 * 
 * Dependencies:
 * - ChatRepositoryInterface for group chat data operations
 * - MessageRepositoryInterface for group message management
 * - ProfileRepositoryInterface for member data and permissions
 * - Laravel Events for group notifications and broadcasting
 * - Carbon for date/time handling
 * 
 * Key Features:
 * - Group chat creation and management
 * - Member invitation and management system
 * - Role-based permissions (admin, moderator, member)
 * - Group moderation and safety controls
 * - Themed group chats and customization
 * - Group events and activities scheduling
 * - Message broadcasting and notifications
 * - Privacy and security controls
 * - Analytics and engagement tracking
 * - Integration with Happy Hour events
 * - Premium group features and limits
 */
class GroupChatService
{
    /**
     * Group type constants
     */
    private const TYPE_PUBLIC = 'public';
    private const TYPE_PRIVATE = 'private';
    private const TYPE_HAPPY_HOUR = 'happy_hour';
    private const TYPE_THEMED = 'themed';
    private const TYPE_EVENT_BASED = 'event_based';

    /**
     * Member role constants
     */
    private const ROLE_OWNER = 'owner';
    private const ROLE_ADMIN = 'admin';
    private const ROLE_MODERATOR = 'moderator';
    private const ROLE_MEMBER = 'member';
    private const ROLE_GUEST = 'guest';

    /**
     * Group status constants
     */
    private const STATUS_ACTIVE = 'active';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_ARCHIVED = 'archived';
    private const STATUS_SUSPENDED = 'suspended';

    /**
     * Group limits and configuration
     */
    private const MAX_MEMBERS_FREE = 10;
    private const MAX_MEMBERS_PREMIUM = 50;
    private const MAX_GROUPS_PER_USER = 20;
    private const MAX_GROUPS_PER_USER_PREMIUM = 100;
    private const GROUP_CACHE_TTL = 600; // 10 minutes
    private const MAX_GROUP_NAME_LENGTH = 50;
    private const MAX_GROUP_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * ========================================
     * GROUP CREATION AND SETUP
     * ========================================
     */

    /**
     * Create a new group chat
     * 
     * @param UserId $creatorId Creator user ID
     * @param array $groupData Group configuration data
     * @param array $options Creation options
     * @return array Created group data
     * 
     * @throws GroupChatException
     */
    public function createGroupChat(
        UserId $creatorId,
        array $groupData,
        array $options = []
    ): array {
        try {
            Log::info('Creating group chat', [
                'creator_id' => $creatorId->toInt(),
                'group_name' => $groupData['name'] ?? 'Unknown',
                'group_type' => $groupData['type'] ?? self::TYPE_PRIVATE
            ]);

            // Validate creator permissions and limits
            $this->validateGroupCreation($creatorId, $groupData);

            // Validate and sanitize group data
            $validatedData = $this->validateGroupData($groupData);

            // Prepare group chat data
            $groupChatData = [
                'id' => Str::uuid()->toString(),
                'type' => 'group',
                'subtype' => $validatedData['type'],
                'status' => self::STATUS_ACTIVE,
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? '',
                'avatar_url' => $validatedData['avatar_url'] ?? null,
                'created_by' => $creatorId->toInt(),
                'participants' => [$creatorId->toInt()],
                'settings' => [
                    'privacy' => $validatedData['privacy'] ?? 'private',
                    'join_approval_required' => $validatedData['join_approval'] ?? true,
                    'invite_only' => $validatedData['invite_only'] ?? true,
                    'message_moderation' => $validatedData['moderation'] ?? false,
                    'max_members' => $this->getMaxMembersLimit($creatorId, $validatedData['type']),
                    'allow_media_sharing' => $validatedData['allow_media'] ?? true,
                    'notifications_enabled' => true,
                    'read_receipts_enabled' => true,
                    'admin_only_messaging' => false
                ],
                'members' => [
                    $creatorId->toInt() => [
                        'role' => self::ROLE_OWNER,
                        'joined_at' => Carbon::now(),
                        'invited_by' => null,
                        'permissions' => $this->getOwnerPermissions(),
                        'status' => 'active',
                        'last_seen' => Carbon::now()
                    ]
                ],
                'metadata' => [
                    'theme' => $validatedData['theme'] ?? 'default',
                    'tags' => $validatedData['tags'] ?? [],
                    'location' => $validatedData['location'] ?? null,
                    'event_date' => $validatedData['event_date'] ?? null,
                    'auto_delete_after_days' => $validatedData['auto_delete'] ?? null,
                    'language' => $validatedData['language'] ?? 'en',
                    'timezone' => $validatedData['timezone'] ?? 'UTC'
                ],
                'analytics' => [
                    'created_at' => Carbon::now(),
                    'last_activity_at' => Carbon::now(),
                    'total_messages' => 0,
                    'member_count' => 1,
                    'engagement_score' => 0.0
                ],
                'moderation' => [
                    'banned_users' => [],
                    'muted_users' => [],
                    'flagged_content_count' => 0,
                    'auto_moderation_enabled' => $validatedData['auto_moderation'] ?? false
                ]
            ];

            // Create the group chat
            $groupChatEntity = $this->chatRepository->create($groupChatData);
            $groupChat = $this->mapChatEntityToArray($groupChatEntity);

            // Add welcome message
            $this->addWelcomeMessage($groupChat['id'], $creatorId);

            // Cache group data
            $this->cacheGroupData($groupChat);

            // Track analytics
            $this->trackGroupAnalytics($groupChat, 'group_created');

            Log::info('Group chat created successfully', [
                'group_id' => $groupChat['id'],
                'creator_id' => $creatorId->toInt(),
                'group_name' => $groupChat['name']
            ]);

            return $groupChat;

        } catch (\Exception $e) {
            Log::error('Failed to create group chat', [
                'creator_id' => $creatorId->toInt(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GroupChatException('Failed to create group chat: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * MEMBER MANAGEMENT
     * ========================================
     */

    /**
     * Invite users to group chat
     * 
     * @param string $groupId Group chat identifier
     * @param UserId $inviterId User sending invitations
     * @param array $inviteeIds Array of user IDs to invite
     * @param array $options Invitation options
     * @return array Invitation results
     */
    public function inviteMembers(
        string $groupId,
        UserId $inviterId,
        array $inviteeIds,
        array $options = []
    ): array {
        try {
            Log::info('Inviting members to group', [
                'group_id' => $groupId,
                'inviter_id' => $inviterId->toInt(),
                'invitee_count' => count($inviteeIds)
            ]);

            // Validate group and inviter permissions
            $group = $this->validateGroupAccess($groupId, $inviterId);
            $this->validateInvitePermissions($group, $inviterId);

            $results = [
                'successful_invites' => [],
                'failed_invites' => [],
                'pending_approvals' => []
            ];

            foreach ($inviteeIds as $inviteeId) {
                try {
                    $userId = new UserId($inviteeId);
                    $result = $this->inviteSingleMember($group, $inviterId, $userId, $options);
                    
                    if ($result['status'] === 'invited') {
                        $results['successful_invites'][] = $result;
                    } elseif ($result['status'] === 'pending_approval') {
                        $results['pending_approvals'][] = $result;
                    }
                    
                } catch (\Exception $e) {
                    $results['failed_invites'][] = [
                        'user_id' => $inviteeId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Update group member count if any successful invites
            if (!empty($results['successful_invites'])) {
                $this->updateGroupMemberCount($groupId);
            }

            // Invalidate cache
            $this->invalidateGroupCache($groupId);

            Log::info('Group invitation process completed', [
                'group_id' => $groupId,
                'successful' => count($results['successful_invites']),
                'failed' => count($results['failed_invites']),
                'pending' => count($results['pending_approvals'])
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to invite members to group', [
                'group_id' => $groupId,
                'inviter_id' => $inviterId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to invite members: ' . $e->getMessage());
        }
    }

    /**
     * Join a group chat
     * 
     * @param string $groupId Group chat identifier
     * @param UserId $userId User joining the group
     * @param array $options Join options
     * @return array Join result data
     */
    public function joinGroup(
        string $groupId,
        UserId $userId,
        array $options = []
    ): array {
        try {
            Log::info('User joining group', [
                'group_id' => $groupId,
                'user_id' => $userId->toInt()
            ]);

            // Validate group and join eligibility
            $group = $this->validateGroupJoinEligibility($groupId, $userId);

            // Check if approval is required
            if ($group['settings']['join_approval_required']) {
                return $this->requestGroupJoin($group, $userId, $options);
            }

            // Add member directly
            $memberData = [
                'role' => self::ROLE_MEMBER,
                'joined_at' => Carbon::now(),
                'invited_by' => $options['invited_by'] ?? null,
                'permissions' => $this->getMemberPermissions(),
                'status' => 'active',
                'last_seen' => Carbon::now()
            ];

            $success = $this->chatRepository->addParticipant($groupId, $userId, 'member', $memberData);

            if ($success) {
                // Trigger group joined event
                Event::dispatch(new GroupChatJoined(
                    $groupId,
                    $userId,
                    $memberData['role'],
                    $options
                ));

                // Add join announcement
                $this->addJoinAnnouncementMessage($groupId, $userId);

                // Update analytics
                $this->updateGroupMemberCount($groupId);
                $this->trackGroupAnalytics($group, 'member_joined', ['user_id' => $userId->toInt()]);

                // Invalidate cache
                $this->invalidateGroupCache($groupId);

                Log::info('User joined group successfully', [
                    'group_id' => $groupId,
                    'user_id' => $userId->toInt()
                ]);

                return [
                    'status' => 'joined',
                    'group_id' => $groupId,
                    'user_id' => $userId->toInt(),
                    'role' => $memberData['role'],
                    'joined_at' => $memberData['joined_at']
                ];
            }

            throw new GroupChatException('Failed to join group');

        } catch (\Exception $e) {
            Log::error('Failed to join group', [
                'group_id' => $groupId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to join group: ' . $e->getMessage());
        }
    }

    /**
     * Remove member from group
     * 
     * @param string $groupId Group chat identifier
     * @param UserId $adminId Admin performing the action
     * @param UserId $memberId Member to remove
     * @param array $options Removal options
     * @return bool Success status
     */
    public function removeMember(
        string $groupId,
        UserId $adminId,
        UserId $memberId,
        array $options = []
    ): bool {
        try {
            Log::info('Removing member from group', [
                'group_id' => $groupId,
                'admin_id' => $adminId->toInt(),
                'member_id' => $memberId->toInt(),
                'reason' => $options['reason'] ?? 'admin_action'
            ]);

            // Validate permissions
            $group = $this->validateGroupAccess($groupId, $adminId);
            $this->validateRemovePermissions($group, $adminId, $memberId);

            // Remove member
            $success = $this->chatRepository->removeParticipant($groupId, $memberId, [
                'removed_by' => $adminId->toInt(),
                'removed_at' => Carbon::now(),
                'reason' => $options['reason'] ?? 'admin_action',
                'ban_user' => $options['ban'] ?? false
            ]);

            if ($success) {
                // Add removal announcement if not banned
                if (!($options['ban'] ?? false)) {
                    $this->addRemovalAnnouncementMessage($groupId, $memberId, $options);
                }

                // Update member count
                $this->updateGroupMemberCount($groupId);

                // Track analytics
                $this->trackGroupAnalytics($group, 'member_removed', [
                    'removed_user_id' => $memberId->toInt(),
                    'admin_id' => $adminId->toInt(),
                    'reason' => $options['reason'] ?? 'admin_action'
                ]);

                // Invalidate cache
                $this->invalidateGroupCache($groupId);

                Log::info('Member removed from group successfully', [
                    'group_id' => $groupId,
                    'member_id' => $memberId->toInt()
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to remove member from group', [
                'group_id' => $groupId,
                'admin_id' => $adminId->toInt(),
                'member_id' => $memberId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to remove member: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * GROUP MANAGEMENT AND SETTINGS
     * ========================================
     */

    /**
     * Update group information and settings
     * 
     * @param string $groupId Group chat identifier
     * @param UserId $adminId Admin making changes
     * @param array $updates Data to update
     * @param array $options Update options
     * @return bool Success status
     */
    public function updateGroupSettings(
        string $groupId,
        UserId $adminId,
        array $updates,
        array $options = []
    ): bool {
        try {
            Log::info('Updating group settings', [
                'group_id' => $groupId,
                'admin_id' => $adminId->toInt(),
                'updates' => array_keys($updates)
            ]);

            // Validate permissions
            $group = $this->validateGroupAccess($groupId, $adminId);
            $this->validateAdminPermissions($group, $adminId);

            // Validate and sanitize updates
            $validatedUpdates = $this->validateGroupUpdates($updates);

            // Apply updates
            $validatedUpdates['updated_by'] = $adminId->toInt();
            $validatedUpdates['updated_at'] = Carbon::now();
            $updatedChatEntity = $this->chatRepository->update($groupId, $validatedUpdates);
            $success = $updatedChatEntity !== null;

            if ($success) {
                // Add settings change announcement
                $this->addSettingsChangeMessage($groupId, $adminId, $validatedUpdates);

                // Track analytics
                $this->trackGroupAnalytics($group, 'settings_updated', [
                    'admin_id' => $adminId->toInt(),
                    'changes' => array_keys($validatedUpdates)
                ]);

                // Invalidate cache
                $this->invalidateGroupCache($groupId);

                Log::info('Group settings updated successfully', [
                    'group_id' => $groupId,
                    'updates' => array_keys($validatedUpdates)
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to update group settings', [
                'group_id' => $groupId,
                'admin_id' => $adminId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to update group settings: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * GROUP DISCOVERY AND LISTING
     * ========================================
     */

    /**
     * Get user's group chats
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param array $options Query options
     * @return Collection User's group chats
     */
    public function getUserGroups(
        UserId $userId,
        array $filters = [],
        array $options = []
    ): Collection {
        try {
            // Check cache first
            $cacheKey = "user_groups:{$userId->toInt()}:" . md5(serialize($filters));
            if ($options['use_cache'] ?? true) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    return collect($cached);
                }
            }

            // Default filters
            $defaultFilters = [
                'status' => [self::STATUS_ACTIVE],
                'role' => null, // any role
                'type' => null, // any type
                'recently_active' => true
            ];

            $mergedFilters = array_merge($defaultFilters, $filters);
            $mergedFilters['user_id'] = $userId->toInt();

            // Get groups from repository
            $groupsPaginator = $this->chatRepository->getUserChats($userId, $mergedFilters);
            $groups = collect($groupsPaginator->items());

            // Enrich with additional data
            $enrichedGroups = $groups->map(function ($group) use ($userId) {
                return $this->enrichGroupDataForUser($group, $userId);
            });

            // Sort by last activity
            $sortedGroups = $enrichedGroups->sortByDesc('last_activity_at')->values();

            // Cache results
            if ($options['use_cache'] ?? true) {
                Cache::put($cacheKey, $sortedGroups->toArray(), self::GROUP_CACHE_TTL);
            }

            return $sortedGroups;

        } catch (\Exception $e) {
            Log::error('Failed to get user groups', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to retrieve user groups: ' . $e->getMessage());
        }
    }

    /**
     * Discover public groups
     * 
     * @param UserId $userId User discovering groups
     * @param array $criteria Discovery criteria
     * @param array $options Discovery options
     * @return Collection Discovered groups
     */
    public function discoverPublicGroups(
        UserId $userId,
        array $criteria = [],
        array $options = []
    ): Collection {
        try {
            $searchParams = [
                'privacy' => 'public',
                'status' => self::STATUS_ACTIVE,
                'exclude_member' => $userId->toInt(),
                'location_radius' => $criteria['location_radius'] ?? null,
                'interests' => $criteria['interests'] ?? [],
                'tags' => $criteria['tags'] ?? [],
                'language' => $criteria['language'] ?? null,
                'min_members' => $criteria['min_members'] ?? 2,
                'max_members' => $criteria['max_members'] ?? null
            ];

            // Get public groups
            $groupsPaginator = $this->chatRepository->searchChats($searchParams);
            $groups = collect($groupsPaginator->items());

            // Enrich with join eligibility and recommendations
            $enrichedGroups = $groups->map(function ($group) use ($userId) {
                $group['can_join'] = $this->canUserJoinGroup($group, $userId);
                $group['recommendation_score'] = $this->calculateRecommendationScore($group, $userId);
                return $group;
            });

            // Sort by recommendation score
            return $enrichedGroups->sortByDesc('recommendation_score')->values();

        } catch (\Exception $e) {
            Log::error('Failed to discover public groups', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new GroupChatException('Failed to discover groups: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Validate group creation permissions and limits
     */
    private function validateGroupCreation(UserId $creatorId, array $groupData): void
    {
        // Check user's group creation limits
        $userProfile = $this->profileRepository->findById($creatorId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        $currentGroups = $this->getUserGroupCount($creatorId);
        $maxGroups = $isPremium ? self::MAX_GROUPS_PER_USER_PREMIUM : self::MAX_GROUPS_PER_USER;
        
        if ($currentGroups >= $maxGroups) {
            throw new GroupLimitExceededException("Maximum group limit reached: {$maxGroups}");
        }

        // Validate group type permissions
        $restrictedTypes = [self::TYPE_HAPPY_HOUR, self::TYPE_EVENT_BASED];
        if (in_array($groupData['type'] ?? '', $restrictedTypes) && !$isPremium) {
            throw new GroupChatException("Premium subscription required for {$groupData['type']} groups");
        }
    }

    /**
     * Validate and sanitize group data
     */
    private function validateGroupData(array $groupData): array
    {
        $validated = [];

        // Validate name
        if (empty($groupData['name'])) {
            throw new GroupChatException('Group name is required');
        }
        $validated['name'] = substr(trim($groupData['name']), 0, self::MAX_GROUP_NAME_LENGTH);

        // Validate description
        if (isset($groupData['description'])) {
            $validated['description'] = substr(trim($groupData['description']), 0, self::MAX_GROUP_DESCRIPTION_LENGTH);
        }

        // Validate type
        $allowedTypes = [self::TYPE_PUBLIC, self::TYPE_PRIVATE, self::TYPE_THEMED, self::TYPE_HAPPY_HOUR, self::TYPE_EVENT_BASED];
        $validated['type'] = in_array($groupData['type'] ?? '', $allowedTypes) ? $groupData['type'] : self::TYPE_PRIVATE;

        // Validate other fields
        $validated['privacy'] = in_array($groupData['privacy'] ?? '', ['public', 'private']) ? $groupData['privacy'] : 'private';
        $validated['join_approval'] = $groupData['join_approval'] ?? true;
        $validated['invite_only'] = $groupData['invite_only'] ?? true;
        $validated['moderation'] = $groupData['moderation'] ?? false;
        $validated['allow_media'] = $groupData['allow_media'] ?? true;
        $validated['auto_moderation'] = $groupData['auto_moderation'] ?? false;

        return $validated;
    }

    /**
     * Get maximum members limit based on user status and group type
     */
    private function getMaxMembersLimit(UserId $userId, string $groupType): int
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        $baseLimit = $isPremium ? self::MAX_MEMBERS_PREMIUM : self::MAX_MEMBERS_FREE;
        
        // Special group types might have different limits
        return match($groupType) {
            self::TYPE_HAPPY_HOUR => min($baseLimit * 2, 100),
            self::TYPE_EVENT_BASED => min($baseLimit * 3, 200),
            default => $baseLimit
        };
    }

    /**
     * Get owner permissions array
     */
    private function getOwnerPermissions(): array
    {
        return [
            'invite_members' => true,
            'remove_members' => true,
            'change_settings' => true,
            'delete_group' => true,
            'assign_roles' => true,
            'moderate_content' => true,
            'view_analytics' => true
        ];
    }

    /**
     * Get member permissions array
     */
    private function getMemberPermissions(): array
    {
        return [
            'send_messages' => true,
            'share_media' => true,
            'invite_members' => false,
            'remove_members' => false,
            'change_settings' => false,
            'moderate_content' => false
        ];
    }

    /**
     * Validate group access and return group data
     */
    private function validateGroupAccess(string $groupId, UserId $userId): array
    {
        $groupEntity = $this->chatRepository->findById($groupId);
        if (!$groupEntity || $groupEntity->type()->value() !== 'group') {
            throw new GroupNotFoundException("Group not found: {$groupId}");
        }

        $group = $this->mapChatEntityToArray($groupEntity);
        if (!$this->isGroupMember($group, $userId)) {
            throw new UnauthorizedGroupAccessException('User not authorized to access this group');
        }

        return $group;
    }

    /**
     * Check if user is a member of the group
     */
    private function isGroupMember(array $group, UserId $userId): bool
    {
        return isset($group['members'][$userId->toInt()]);
    }

    /**
     * Additional helper methods for group management...
     */
    
    private function validateInvitePermissions(array $group, UserId $inviterId): void
    {
        $member = $group['members'][$inviterId->toInt()] ?? null;
        if (!$member || !($member['permissions']['invite_members'] ?? false)) {
            throw new UnauthorizedGroupAccessException('User not authorized to invite members');
        }
    }

    private function inviteSingleMember(array $group, UserId $inviterId, UserId $inviteeId, array $options): array
    {
        // Check if user is already a member
        if ($this->isGroupMember($group, $inviteeId)) {
            throw new GroupChatException('User is already a member of this group');
        }

        // Check group member limit
        $memberCount = count($group['members']);
        if ($memberCount >= $group['settings']['max_members']) {
            throw new GroupLimitExceededException('Group has reached maximum member limit');
        }

        // Check if user can be invited (not banned, etc.)
        $this->validateInviteeEligibility($group, $inviteeId);

        // Create invitation
        return $this->createGroupInvitation($group['id'], $inviterId, $inviteeId, $options);
    }

    private function validateInviteeEligibility(array $group, UserId $inviteeId): void
    {
        // Check if user is banned
        if (in_array($inviteeId->toInt(), $group['moderation']['banned_users'] ?? [])) {
            throw new GroupChatException('User is banned from this group');
        }

        // Check user profile status
        $profile = $this->profileRepository->findById($inviteeId->toInt());
        if (!$profile || ($profile['status'] ?? '') !== 'active') {
            throw new GroupChatException('User profile not found or inactive');
        }
    }

    private function createGroupInvitation(string $groupId, UserId $inviterId, UserId $inviteeId, array $options): array
    {
        // For now, add member directly (in production, this would create a pending invitation)
        $memberData = [
            'role' => self::ROLE_MEMBER,
            'joined_at' => Carbon::now(),
            'invited_by' => $inviterId->toInt(),
            'permissions' => $this->getMemberPermissions(),
            'status' => 'active',
            'last_seen' => null
        ];

        $success = $this->chatRepository->addParticipant($groupId, $inviteeId, 'member', $memberData);

        if ($success) {
            // Trigger event
            Event::dispatch(new GroupChatJoined($groupId, $inviteeId, $memberData['role'], [
                'invited_by' => $inviterId->toInt()
            ]));

            return [
                'status' => 'invited',
                'user_id' => $inviteeId->toInt(),
                'invited_by' => $inviterId->toInt(),
                'invited_at' => Carbon::now()
            ];
        }

        throw new GroupChatException('Failed to invite user to group');
    }

    private function validateGroupJoinEligibility(string $groupId, UserId $userId): array
    {
        $groupEntity = $this->chatRepository->findById($groupId);
        if (!$groupEntity || $groupEntity->type()->value() !== 'group') {
            throw new GroupNotFoundException("Group not found: {$groupId}");
        }

        $group = $this->mapChatEntityToArray($groupEntity);
        if ($group['status'] !== self::STATUS_ACTIVE) {
            throw new GroupChatException('Group is not active');
        }

        if ($this->isGroupMember($group, $userId)) {
            throw new GroupChatException('User is already a member of this group');
        }

        // Check if group is invite-only
        if ($group['settings']['invite_only'] ?? false) {
            throw new GroupChatException('Group is invite-only');
        }

        // Check if user is banned
        if (in_array($userId->toInt(), $group['moderation']['banned_users'] ?? [])) {
            throw new GroupChatException('User is banned from this group');
        }

        // Check member limit
        if (count($group['members']) >= $group['settings']['max_members']) {
            throw new GroupLimitExceededException('Group has reached maximum member limit');
        }

        return $group;
    }

    private function requestGroupJoin(array $group, UserId $userId, array $options): array
    {
        // Create join request (in production, this would create a pending approval)
        return [
            'status' => 'pending_approval',
            'group_id' => $group['id'],
            'user_id' => $userId->toInt(),
            'requested_at' => Carbon::now()
        ];
    }

    private function addWelcomeMessage(string $groupId, UserId $creatorId): void
    {
        $this->messageRepository->create([
            'chat_id' => $groupId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => 'Welcome to the group! Start chatting and get to know each other.',
            'metadata' => [
                'message_type' => 'welcome',
                'created_by' => $creatorId->toInt()
            ]
        ]);
    }

    private function addJoinAnnouncementMessage(string $groupId, UserId $userId): void
    {
        $profile = $this->profileRepository->findById($userId->toInt());
        $userName = $profile['name'] ?? 'Someone';
        
        $this->messageRepository->create([
            'chat_id' => $groupId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "{$userName} joined the group",
            'metadata' => [
                'message_type' => 'member_joined',
                'user_id' => $userId->toInt()
            ]
        ]);
    }

    private function cacheGroupData(array $group): void
    {
        Cache::put("group:{$group['id']}", $group, self::GROUP_CACHE_TTL);
    }

    private function invalidateGroupCache(string $groupId): void
    {
        Cache::forget("group:{$groupId}");
    }

    private function updateGroupMemberCount(string $groupId): void
    {
        $this->updateGroupAnalytics($groupId, [
            'member_count_updated' => Carbon::now()
        ]);
    }

    private function trackGroupAnalytics(array $group, string $event, array $data = []): void
    {
        Log::info("Group analytics: {$event}", array_merge([
            'group_id' => $group['id'],
            'event' => $event
        ], $data));
    }

    private function enrichGroupDataForUser(array $group, UserId $userId): array
    {
        // Add user-specific data
        $member = $group['members'][$userId->toInt()] ?? null;
        $group['user_role'] = $member['role'] ?? null;
        $group['user_permissions'] = $member['permissions'] ?? [];
        
        // Add last message preview
        $lastMessages = $this->messageRepository->getChatMessages($group['id'], [
            'per_page' => 1,
            'sort_direction' => 'desc'
        ]);
        $lastMessage = $lastMessages->items()[0] ?? null;
        $group['last_message'] = $lastMessage ? [
            'preview' => Str::limit($lastMessage->content(), 50),
            'sender_name' => $this->getUserName($lastMessage->senderId()->toString()),
            'sent_at' => $lastMessage->createdAt()
        ] : null;

        // Add unread count
        $unreadMessages = $this->messageRepository->getUnreadMessages($userId, [
            'chat_ids' => [$group['id']]
        ]);
        $group['unread_count'] = $unreadMessages->count();

        return $group;
    }

    private function canUserJoinGroup(array $group, UserId $userId): bool
    {
        try {
            $this->validateGroupJoinEligibility($group['id'], $userId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function calculateRecommendationScore(array $group, UserId $userId): float
    {
        $score = 0.0;

        // Base score
        $score += 0.3;

        // Member count factor (sweet spot around 5-15 members)
        $memberCount = count($group['members']);
        if ($memberCount >= 5 && $memberCount <= 15) {
            $score += 0.2;
        }

        // Activity factor
        $lastActivity = Carbon::parse($group['analytics']['last_activity_at']);
        $daysSinceActivity = $lastActivity->diffInDays(Carbon::now());
        if ($daysSinceActivity <= 1) {
            $score += 0.3;
        } elseif ($daysSinceActivity <= 7) {
            $score += 0.1;
        }

        // Interest matching (would need user profile data)
        // Location proximity (would need location data)

        return min(1.0, $score);
    }

    private function getUserName(string $userId): string
    {
        if ($userId === 'system') {
            return 'System';
        }
        
        $profile = $this->profileRepository->findById((int) $userId);
        return $profile['name'] ?? 'Unknown User';
    }

    // Additional helper methods would go here...
    private function validateRemovePermissions(array $group, UserId $adminId, UserId $memberId): void
    {
        $adminMember = $group['members'][$adminId->toInt()] ?? null;
        if (!$adminMember || !($adminMember['permissions']['remove_members'] ?? false)) {
            throw new UnauthorizedGroupAccessException('User not authorized to remove members');
        }

        // Cannot remove group owner
        $targetMember = $group['members'][$memberId->toInt()] ?? null;
        if ($targetMember && $targetMember['role'] === self::ROLE_OWNER) {
            throw new GroupChatException('Cannot remove group owner');
        }
    }

    private function validateAdminPermissions(array $group, UserId $adminId): void
    {
        $member = $group['members'][$adminId->toInt()] ?? null;
        if (!$member || !in_array($member['role'], [self::ROLE_OWNER, self::ROLE_ADMIN])) {
            throw new UnauthorizedGroupAccessException('Admin permissions required');
        }
    }

    private function validateGroupUpdates(array $updates): array
    {
        $allowed = ['name', 'description', 'avatar_url', 'settings', 'metadata'];
        return array_intersect_key($updates, array_flip($allowed));
    }

    private function addRemovalAnnouncementMessage(string $groupId, UserId $memberId, array $options): void
    {
        $userName = $this->getUserName($memberId->toString());
        $reason = $options['reason'] ?? 'left the group';
        
        $this->messageRepository->create([
            'chat_id' => $groupId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "{$userName} {$reason}",
            'metadata' => [
                'message_type' => 'member_removed',
                'user_id' => $memberId->toInt()
            ]
        ]);
    }

    private function addSettingsChangeMessage(string $groupId, UserId $adminId, array $changes): void
    {
        $adminName = $this->getUserName($adminId->toString());
        
        $this->messageRepository->create([
            'chat_id' => $groupId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "{$adminName} updated group settings",
            'metadata' => [
                'message_type' => 'settings_changed',
                'admin_id' => $adminId->toInt(),
                'changes' => array_keys($changes)
            ]
        ]);
    }

    /**
     * Map Chat entity to array format expected by GroupChatService
     */
    private function mapChatEntityToArray($chatEntity): array
    {
        if (!$chatEntity) {
            return [];
        }

        return [
            'id' => $chatEntity->id()->value(),
            'type' => $chatEntity->type()->value(),
            'name' => $chatEntity->name(),
            'description' => $chatEntity->description(),
            'creator_id' => $chatEntity->creatorId()->toInt(),
            'status' => 'active', // Default status
            'settings' => $chatEntity->settings() ?? [],
            'metadata' => $chatEntity->metadata() ?? [],
            'members' => [], // Will be populated from participants
            'moderation' => [
                'banned_users' => [],
                'muted_users' => [],
                'flagged_content_count' => 0,
                'auto_moderation_enabled' => false
            ],
            'analytics' => [
                'created_at' => $chatEntity->createdAt(),
                'last_activity_at' => $chatEntity->lastActivityAt(),
                'total_messages' => 0,
                'member_count' => 0,
                'engagement_score' => 0.0
            ]
        ];
    }

    /**
     * Get user's group count
     */
    private function getUserGroupCount(UserId $userId): int
    {
        $userChats = $this->chatRepository->getUserChats($userId, [
            'types' => ['group'],
            'per_page' => 1000 // Get all to count
        ]);
        
        return $userChats->total();
    }

    /**
     * Update group analytics
     */
    private function updateGroupAnalytics(string $groupId, array $analytics): void
    {
        // This would typically update analytics in a separate analytics service
        // For now, we'll just log the update
        Log::info('Group analytics updated', [
            'group_id' => $groupId,
            'analytics' => $analytics
        ]);
    }
}