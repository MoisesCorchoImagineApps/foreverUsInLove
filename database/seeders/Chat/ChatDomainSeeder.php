<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Chat\Chat;
use App\Models\Chat\GroupChat;
use App\Models\Chat\Message;
use App\Models\Chat\VideoCall;
use App\Models\Chat\CallHistory;
use Illuminate\Support\Facades\DB;

class ChatDomainSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🗨️  Seeding Chat Domain...');
        
        // Ensure we have users to work with
        $users = User::inRandomOrder()->limit(200)->get();
        if ($users->count() < 50) {
            $this->command->warn('Not enough users found. Creating additional users for chat seeding...');
            $users = User::factory(200)->create();
        }
        
        $userIds = $users->pluck('id')->toArray();
        
        DB::transaction(function () use ($userIds) {
            // 1. Create Group Chats (various types)
            $this->seedGroupChats($userIds);
            
            // 2. Create Private Chats
            $this->seedPrivateChats($userIds);
            
            // 3. Create Video Calls and related data
            $this->seedVideoCalls($userIds);
            
            // 4. Create Messages for all chats
            $this->seedMessages();
            
            // 5. Create Chat Participants (pivot data)
            $this->seedChatParticipants($userIds);
        });
        
        $this->command->info('✅ Chat Domain seeding completed!');
        $this->displaySeedingStats();
    }
    
    private function seedGroupChats(array $userIds): void
    {
        $this->command->info('Creating Group Chats...');
        
        // Public Community Groups
        GroupChat::factory()
            ->count(25)
            ->publicCommunity()
            ->create(['owner_id' => fake()->randomElement($userIds)])
            ->each(function ($groupChat) use ($userIds) {
                // Create associated Chat
                $chat = Chat::factory()
                    ->forGroupChat($groupChat)
                    ->create();
                
                // Add members to group
                $memberCount = fake()->numberBetween(15, 45);
                $selectedMembers = fake()->randomElements($userIds, $memberCount);
                
                foreach ($selectedMembers as $userId) {
                    DB::table('group_chat_members')->insert([
                        'id' => fake()->uuid(),
                        'group_chat_id' => $groupChat->id,
                        'user_id' => $userId,
                        'role' => $userId === $groupChat->owner_id ? 'owner' : 
                                 (fake()->boolean(10) ? 'moderator' : 'member'),
                        'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
                        'joined_at' => fake()->dateTimeBetween('-6 months', 'now'),
                        'invited_by' => $groupChat->owner_id,
                        'join_method' => fake()->randomElement(['invited', 'public_join', 'invite_link']),
                        'can_invite_members' => fake()->boolean(20),
                        'total_messages_sent' => fake()->numberBetween(0, 500),
                        'total_reactions_given' => fake()->numberBetween(0, 200),
                        'contribution_score' => fake()->numberBetween(0, 1000),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        
        // Private Study/Work Groups
        GroupChat::factory()
            ->count(40)
            ->privateGroup()
            ->create(['owner_id' => fake()->randomElement($userIds)])
            ->each(function ($groupChat) use ($userIds) {
                Chat::factory()->forGroupChat($groupChat)->create();
                
                // Smaller, more intimate groups
                $memberCount = fake()->numberBetween(5, 15);
                $selectedMembers = fake()->randomElements($userIds, $memberCount);
                
                foreach ($selectedMembers as $userId) {
                    DB::table('group_chat_members')->insert([
                        'id' => fake()->uuid(),
                        'group_chat_id' => $groupChat->id,
                        'user_id' => $userId,
                        'role' => $userId === $groupChat->owner_id ? 'owner' : 
                                 (fake()->boolean(30) ? 'admin' : 'member'),
                        'status' => 'active',
                        'joined_at' => fake()->dateTimeBetween('-3 months', 'now'),
                        'invited_by' => fake()->randomElement($selectedMembers),
                        'join_method' => 'invited',
                        'membership_tier' => fake()->randomElement(['basic', 'premium', 'vip']),
                        'total_messages_sent' => fake()->numberBetween(10, 800),
                        'events_attended' => fake()->numberBetween(0, 10),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        
        // Happy Hour Groups
        GroupChat::factory()
            ->count(15)
            ->happyHour()
            ->create(['owner_id' => fake()->randomElement($userIds)])
            ->each(function ($groupChat) use ($userIds) {
                Chat::factory()->forGroupChat($groupChat)->create();
                
                $memberCount = fake()->numberBetween(8, 25);
                $selectedMembers = fake()->randomElements($userIds, $memberCount);
                
                foreach ($selectedMembers as $userId) {
                    DB::table('group_chat_members')->insert([
                        'id' => fake()->uuid(),
                        'group_chat_id' => $groupChat->id,
                        'user_id' => $userId,
                        'role' => $userId === $groupChat->owner_id ? 'owner' : 'member',
                        'status' => 'active',
                        'joined_at' => fake()->dateTimeBetween('-2 months', 'now'),
                        'happy_hour_participant' => true,
                        'happy_hour_sessions_attended' => fake()->numberBetween(1, 20),
                        'last_happy_hour_activity' => fake()->dateTimeBetween('-1 week', 'now'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        
        // Themed Discussion Groups
        GroupChat::factory()
            ->count(30)
            ->themed()
            ->create(['owner_id' => fake()->randomElement($userIds)])
            ->each(function ($groupChat) use ($userIds) {
                Chat::factory()->forGroupChat($groupChat)->create();
                
                $memberCount = fake()->numberBetween(12, 35);
                $selectedMembers = fake()->randomElements($userIds, $memberCount);
                
                foreach ($selectedMembers as $userId) {
                    DB::table('group_chat_members')->insert([
                        'id' => fake()->uuid(),
                        'group_chat_id' => $groupChat->id,
                        'user_id' => $userId,
                        'role' => 'member',
                        'status' => 'active',
                        'joined_at' => fake()->dateTimeBetween('-4 months', 'now'),
                        'topic_contributions' => fake()->numberBetween(0, 50),
                        'topic_relevance_score' => fake()->randomFloat(2, 3.0, 5.0),
                        'contributor_level' => fake()->randomElement(['newbie', 'regular', 'veteran']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        
        // Event-Based Groups
        GroupChat::factory()
            ->count(20)
            ->eventBased()
            ->create(['owner_id' => fake()->randomElement($userIds)])
            ->each(function ($groupChat) use ($userIds) {
                Chat::factory()->forGroupChat($groupChat)->create();
                
                $memberCount = fake()->numberBetween(10, 50);
                $selectedMembers = fake()->randomElements($userIds, $memberCount);
                
                foreach ($selectedMembers as $userId) {
                    DB::table('group_chat_members')->insert([
                        'id' => fake()->uuid(),
                        'group_chat_id' => $groupChat->id,
                        'user_id' => $userId,
                        'role' => 'member',
                        'status' => 'active',
                        'joined_at' => fake()->dateTimeBetween('-1 month', 'now'),
                        'attending_current_event' => fake()->boolean(70),
                        'event_rsvp_status' => fake()->randomElement(['attending', 'maybe', 'not_attending']),
                        'events_attended' => fake()->numberBetween(0, 5),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
    
    private function seedPrivateChats(array $userIds): void
    {
        $this->command->info('Creating Private Chats...');
        
        // Create private chats between random users
        for ($i = 0; $i < 300; $i++) {
            $participants = fake()->randomElements($userIds, 2);
            
            $chat = Chat::factory()
                ->privateChat()
                ->create();
            
            // Add participants
            foreach ($participants as $userId) {
                DB::table('chat_participants')->insert([
                    'id' => fake()->uuid(),
                    'chat_id' => $chat->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'status' => fake()->randomElement(['active', 'active', 'active', 'left']),
                    'joined_at' => fake()->dateTimeBetween('-1 year', 'now'),
                    'total_messages_sent' => fake()->numberBetween(0, 1000),
                    'last_activity_at' => fake()->dateTimeBetween('-1 week', 'now'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
    
    private function seedVideoCalls(array $userIds): void
    {
        $this->command->info('Creating Video Calls...');
        
        // Get some group chats for video calls
        $groupChats = GroupChat::limit(50)->get();
        
        // Group video calls
        foreach ($groupChats->random(30) as $groupChat) {
            $videoCall = VideoCall::factory()
                ->groupCall()
                ->create(['initiated_by' => $groupChat->owner_id]);
            
            // Create associated Chat
            Chat::factory()
                ->forVideoCall($videoCall)
                ->create();
            
            // Add participants from group members
            $groupMembers = DB::table('group_chat_members')
                ->where('group_chat_id', $groupChat->id)
                ->where('status', 'active')
                ->limit(fake()->numberBetween(3, 8))
                ->get();
            
            foreach ($groupMembers as $member) {
                DB::table('video_call_participants')->insert([
                    'id' => fake()->uuid(),
                    'video_call_id' => $videoCall->id,
                    'user_id' => $member->user_id,
                    'status' => fake()->randomElement(['joined', 'left', 'declined']),
                    'role' => $member->user_id === $videoCall->initiated_by ? 'host' : 'participant',
                    'invited_at' => $videoCall->created_at,
                    'joined_at' => fake()->dateTimeBetween($videoCall->created_at, 'now'),
                    'connection_quality' => fake()->numberBetween(2, 5),
                    'speaking_time' => fake()->numberBetween(30, 1800),
                    'reactions_sent' => fake()->numberBetween(0, 20),
                    'call_experience_rating' => fake()->numberBetween(3, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Create call history for each participant
                if (fake()->boolean(80)) {
                    CallHistory::factory()->create([
                        'video_call_id' => $videoCall->id,
                        'user_id' => $member->user_id,
                        'joined_at' => fake()->dateTimeBetween($videoCall->created_at, 'now'),
                    ]);
                }
            }
        }
        
        // Private video calls
        for ($i = 0; $i < 150; $i++) {
            $participants = fake()->randomElements($userIds, fake()->numberBetween(2, 4));
            
            $videoCall = VideoCall::factory()
                ->privateCall()
                ->create(['initiated_by' => $participants[0]]);
            
            Chat::factory()
                ->forVideoCall($videoCall)
                ->create();
            
            foreach ($participants as $userId) {
                DB::table('video_call_participants')->insert([
                    'id' => fake()->uuid(),
                    'video_call_id' => $videoCall->id,
                    'user_id' => $userId,
                    'status' => fake()->randomElement(['joined', 'left', 'no_answer']),
                    'role' => $userId === $videoCall->initiated_by ? 'host' : 'participant',
                    'invited_at' => $videoCall->created_at,
                    'joined_at' => fake()->boolean(70) ? fake()->dateTimeBetween($videoCall->created_at, 'now') : null,
                    'connection_quality' => fake()->numberBetween(1, 5),
                    'speaking_time' => fake()->numberBetween(0, 3600),
                    'call_experience_rating' => fake()->numberBetween(2, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                if (fake()->boolean(60)) {
                    CallHistory::factory()->create([
                        'video_call_id' => $videoCall->id,
                        'user_id' => $userId,
                        'joined_at' => fake()->dateTimeBetween($videoCall->created_at, 'now'),
                    ]);
                }
            }
        }
    }
    
    private function seedMessages(): void
    {
        $this->command->info('Creating Messages...');
        
        $allChats = Chat::with(['chatable'])->get();
        
        foreach ($allChats as $chat) {
            // Get participants for this chat
            $participantIds = [];
            
            if ($chat->chatable_type === 'App\\Models\\Chat\\GroupChat') {
                $participantIds = DB::table('group_chat_members')
                    ->where('group_chat_id', $chat->chatable_id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();
            } else {
                $participantIds = DB::table('chat_participants')
                    ->where('chat_id', $chat->id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();
            }
            
            if (empty($participantIds)) continue;
            
            // Create messages for this chat
            $messageCount = fake()->numberBetween(5, 100);
            
            for ($i = 0; $i < $messageCount; $i++) {
                $senderId = fake()->randomElement($participantIds);
                
                Message::factory()
                    ->create([
                        'chat_id' => $chat->id,
                        'sender_id' => $senderId,
                        'type' => fake()->randomElement([
                            'text', 'text', 'text', 'text', 'text', // Text messages more common
                            'image', 'voice', 'sticker', 'gif', 'file'
                        ]),
                        'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
                    ]);
            }
            
            // Add some system messages for group chats
            if ($chat->chatable_type === 'App\\Models\\Chat\\GroupChat' && fake()->boolean(70)) {
                Message::factory()
                    ->systemMessage()
                    ->create([
                        'chat_id' => $chat->id,
                        'sender_id' => fake()->randomElement($participantIds),
                        'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
                    ]);
            }
            
            // Add icebreaker messages to some chats
            if (fake()->boolean(30)) {
                Message::factory()
                    ->icebreaker()
                    ->create([
                        'chat_id' => $chat->id,
                        'sender_id' => fake()->randomElement($participantIds),
                        'created_at' => fake()->dateTimeBetween('-2 months', 'now'),
                    ]);
            }
        }
    }
    
    private function seedChatParticipants(array $userIds): void
    {
        $this->command->info('Updating Chat Participants data...');
        
        // Update chat participants with realistic read receipts and activity
        $privateChats = Chat::where('type', 'private')->get();
        
        foreach ($privateChats as $chat) {
            $participants = DB::table('chat_participants')
                ->where('chat_id', $chat->id)
                ->get();
            
            $lastMessage = Message::where('chat_id', $chat->id)
                ->latest()
                ->first();
            
            foreach ($participants as $participant) {
                DB::table('chat_participants')
                    ->where('id', $participant->id)
                    ->update([
                        'last_read_message_id' => $lastMessage ? $lastMessage->id : null,
                        'last_read_at' => fake()->dateTimeBetween('-1 week', 'now'),
                        'unread_message_count' => fake()->numberBetween(0, 25),
                        'last_activity_at' => fake()->dateTimeBetween('-3 days', 'now'),
                        'total_reactions_given' => fake()->numberBetween(0, 100),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
    
    private function displaySeedingStats(): void
    {
        $stats = [
            'Group Chats' => GroupChat::count(),
            'Private Chats' => Chat::where('type', 'private')->count(),
            'Video Calls' => VideoCall::count(),
            'Messages' => Message::count(),
            'Call Histories' => CallHistory::count(),
            'Chat Participants' => DB::table('chat_participants')->count(),
            'Group Chat Members' => DB::table('group_chat_members')->count(),
            'Video Call Participants' => DB::table('video_call_participants')->count(),
        ];
        
        $this->command->info('📊 Chat Domain Seeding Statistics:');
        foreach ($stats as $entity => $count) {
            $this->command->info("   {$entity}: {$count}");
        }
    }
}