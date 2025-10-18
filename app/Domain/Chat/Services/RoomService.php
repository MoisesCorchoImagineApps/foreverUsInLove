<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Models\Chat\Chat;
use App\Models\Chat\GroupChat;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Room Service for managing chat room lifecycle and expiration
 * 
 * This service handles the complex logic for room expiration,
 * cleanup, and status management using the existing Chat and GroupChat models.
 */
class RoomService
{
    /**
     * Expire a chat room (convert from active to inactive)
     * 
     * @param Chat $chat
     * @return bool
     */
    public function expireRoom(Chat $chat): bool
    {
        try {
            DB::beginTransaction();

            // Update chat status to inactive
            $chat->update([
                'status' => Chat::STATUS_INACTIVE,
                'last_activity_at' => now(),
            ]);

            // If it's a group chat, also update the GroupChat model
            if ($chat->chatable_type === GroupChat::class && $chat->chatable) {
                $chat->chatable->update([
                    'status' => GroupChat::STATUS_INACTIVE,
                    'last_activity_at' => now(),
                ]);
            }

            // Archive the chat
            $chat->archive();

            // Clean up any active sessions
            $this->cleanupActiveSessions($chat->id);

            DB::commit();

            Log::info('Room expired successfully', [
                'chat_id' => $chat->id,
                'type' => $chat->type,
                'participant_count' => $chat->participant_count,
                'expired_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to expire room', [
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Delete a chat room completely
     * 
     * @param Chat $chat
     * @return bool
     */
    public function deleteRoom(Chat $chat): bool
    {
        try {
            DB::beginTransaction();

            $chatId = $chat->id;
            $chatType = $chat->type;

            // Delete associated messages first
            $chat->messages()->delete();

            // Delete participants
            $chat->participants()->detach();

            // If it's a group chat, delete the GroupChat model
            if ($chat->chatable_type === GroupChat::class && $chat->chatable) {
                $chat->chatable->delete();
            }

            // Delete the chat itself
            $chat->delete();

            // Clean up cache and sessions
            $this->cleanupActiveSessions($chatId);

            DB::commit();

            Log::info('Room deleted successfully', [
                'chat_id' => $chatId,
                'type' => $chatType,
                'deleted_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete room', [
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Clean up active sessions for a chat
     * 
     * @param int $chatId
     * @return void
     */
    private function cleanupActiveSessions(int $chatId): void
    {
        try {
            // Clear any cached chat data
            \Illuminate\Support\Facades\Cache::forget("chat:{$chatId}");
            \Illuminate\Support\Facades\Cache::forget("chat:{$chatId}:messages");
            \Illuminate\Support\Facades\Cache::forget("chat:{$chatId}:statistics");

            // Clear user chat lists cache for all participants
            $chat = Chat::with('participants')->find($chatId);
            if ($chat) {
                foreach ($chat->participants as $participant) {
                    \Illuminate\Support\Facades\Cache::forget("user_chats:{$participant->id}");
                }
            }

            // Clear any WebSocket session data
            \Illuminate\Support\Facades\Cache::tags(["chat_sessions:{$chatId}"])->flush();

        } catch (\Exception $e) {
            Log::warning('Failed to cleanup sessions for chat', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get expired chats that need to be marked as inactive
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpiredChats(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return Chat::where('status', Chat::STATUS_ACTIVE)
            ->where(function ($query) {
                // For private chats, expire after 30 days of inactivity
                $query->where(function ($q) {
                    $q->where('type', Chat::TYPE_PRIVATE)
                      ->where('last_activity_at', '<', Carbon::now()->subDays(30));
                })
                // For group chats, expire after 7 days of inactivity
                ->orWhere(function ($q) {
                    $q->where('type', Chat::TYPE_GROUP)
                      ->where('last_activity_at', '<', Carbon::now()->subDays(7));
                })
                // For video call chats, expire after 1 day of inactivity
                ->orWhere(function ($q) {
                    $q->where('type', Chat::TYPE_VIDEO_CALL)
                      ->where('last_activity_at', '<', Carbon::now()->subDay());
                });
            })
            ->with(['participants', 'chatable'])
            ->orderBy('last_activity_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get empty inactive chats that can be cleaned up
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEmptyInactiveChats(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return Chat::where('status', Chat::STATUS_INACTIVE)
            ->where('updated_at', '<', Carbon::now()->subDays(7))
            ->whereDoesntHave('participants')
            ->whereDoesntHave('messages', function ($q) {
                $q->where('created_at', '>', Carbon::now()->subDays(30));
            })
            ->with(['chatable'])
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
