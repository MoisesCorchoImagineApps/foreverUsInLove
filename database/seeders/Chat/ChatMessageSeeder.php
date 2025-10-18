<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat\Chat;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatMessageSeeder extends Seeder
{
    /**
     * Seed realistic chat conversations with threading and replies
     */
    public function run(): void
    {
        $this->command->info('🗨️ Creating realistic chat conversations...');
        
        $chats = Chat::with('chatable')->get();
        
        foreach ($chats->random(min(50, $chats->count())) as $chat) {
            $this->createConversationThread($chat);
        }
        
        $this->command->info('✅ Chat conversations created successfully!');
    }
    
    private function createConversationThread(Chat $chat): void
    {
        // Get participants
        $participants = $this->getChatParticipants($chat);
        
        if (empty($participants)) return;
        
        // Create a conversation starter
        $starterMessage = Message::factory()->create([
            'chat_id' => $chat->id,
            'sender_id' => fake()->randomElement($participants),
            'type' => 'text',
            'content' => $this->getConversationStarter($chat),
            'is_thread_starter' => true,
            'created_at' => fake()->dateTimeBetween('-1 week', '-6 hours'),
        ]);
        
        // Create replies to the starter
        $replyCount = fake()->numberBetween(2, 8);
        $threadParticipants = [];
        
        for ($i = 0; $i < $replyCount; $i++) {
            $replySender = fake()->randomElement($participants);
            $threadParticipants[] = $replySender;
            
            Message::factory()->create([
                'chat_id' => $chat->id,
                'sender_id' => $replySender,
                'reply_to_message_id' => $starterMessage->id,
                'type' => fake()->randomElement(['text', 'text', 'text', 'voice', 'sticker']),
                'content' => $this->getReplyContent($i),
                'created_at' => fake()->dateTimeBetween($starterMessage->created_at, 'now'),
            ]);
        }
        
        // Update thread info
        $starterMessage->update([
            'thread_message_count' => $replyCount,
            'thread_participants' => array_unique($threadParticipants)
        ]);
        
        // Create some media sharing
        if (fake()->boolean(40)) {
            $this->createMediaSharingSequence($chat, $participants);
        }
        
        // Create some voice message conversations
        if (fake()->boolean(30)) {
            $this->createVoiceMessageSequence($chat, $participants);
        }
        
        // Create system messages for group events
        if ($chat->type === 'group' && fake()->boolean(60)) {
            $this->createSystemMessages($chat, $participants);
        }
    }
    
    private function createMediaSharingSequence(Chat $chat, array $participants): void
    {
        $sender = fake()->randomElement($participants);
        
        // Photo sharing with reactions
        $photoMessage = Message::factory()->create([
            'chat_id' => $chat->id,
            'sender_id' => $sender,
            'type' => 'image',
            'content' => fake()->randomElement([
                'Check out this amazing sunset! 🌅',
                'Look what I found at the market today',
                'New haircut, what do you think?',
                'This view is incredible!',
            ]),
            'media_url' => 'https://picsum.photos/800/600?random=' . fake()->numberBetween(1, 1000),
            'thumbnail_url' => 'https://picsum.photos/200/150?random=' . fake()->numberBetween(1, 1000),
            'media_metadata' => [
                'width' => 800,
                'height' => 600,
                'size' => fake()->numberBetween(100000, 500000),
                'format' => 'jpeg'
            ],
            'reactions' => [
                '❤️' => fake()->numberBetween(1, 5),
                '😍' => fake()->numberBetween(0, 3),
                '👍' => fake()->numberBetween(0, 2),
            ],
            'created_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);
        
        // Replies to photo
        $replyCount = fake()->numberBetween(1, 4);
        for ($i = 0; $i < $replyCount; $i++) {
            Message::factory()->create([
                'chat_id' => $chat->id,
                'sender_id' => fake()->randomElement($participants),
                'reply_to_message_id' => $photoMessage->id,
                'type' => 'text',
                'content' => fake()->randomElement([
                    'Wow, that\'s beautiful! 😍',
                    'Amazing shot!',
                    'Love it! ❤️',
                    'Where is this?',
                    'So pretty! 🌟',
                ]),
                'created_at' => fake()->dateTimeBetween($photoMessage->created_at, 'now'),
            ]);
        }
    }
    
    private function createVoiceMessageSequence(Chat $chat, array $participants): void
    {
        $conversationStarters = [
            'Hey guys, I can\'t type right now but wanted to update you on...',
            'Quick voice message while I\'m driving...',
            'Thought you might want to hear this funny story...',
            'Can\'t believe what just happened!',
        ];
        
        Message::factory()->create([
            'chat_id' => $chat->id,
            'sender_id' => fake()->randomElement($participants),
            'type' => 'voice',
            'content' => fake()->randomElement($conversationStarters),
            'voice_duration' => fake()->numberBetween(15, 120),
            'voice_transcription' => fake()->sentence(20),
            'is_transcribed' => fake()->boolean(70),
            'created_at' => fake()->dateTimeBetween('-2 days', 'now'),
        ]);
        
        // Text replies to voice message
        $replyCount = fake()->numberBetween(1, 3);
        for ($i = 0; $i < $replyCount; $i++) {
            Message::factory()->create([
                'chat_id' => $chat->id,
                'sender_id' => fake()->randomElement($participants),
                'type' => 'text',
                'content' => fake()->randomElement([
                    'Haha, thanks for sharing!',
                    'That\'s crazy!',
                    'Tell me more later',
                    'Can\'t listen right now, will catch up later',
                ]),
                'created_at' => fake()->dateTimeBetween('-2 days', 'now'),
            ]);
        }
    }
    
    private function createSystemMessages(Chat $chat, array $participants): void
    {
        $systemEvents = [
            [
                'action' => 'user_joined',
                'content' => fake()->name() . ' joined the group',
                'data' => ['user_id' => fake()->randomElement($participants)]
            ],
            [
                'action' => 'settings_changed',
                'content' => 'Group settings were updated',
                'data' => ['changed_by' => fake()->randomElement($participants), 'changes' => ['name', 'description']]
            ],
            [
                'action' => 'user_promoted',
                'content' => fake()->name() . ' was promoted to admin',
                'data' => ['user_id' => fake()->randomElement($participants), 'new_role' => 'admin']
            ],
        ];
        
        foreach (fake()->randomElements($systemEvents, fake()->numberBetween(1, 2)) as $event) {
            Message::factory()->create([
                'chat_id' => $chat->id,
                'sender_id' => fake()->randomElement($participants),
                'type' => 'system',
                'content' => $event['content'],
                'system_action' => $event['action'],
                'system_data' => $event['data'],
                'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
            ]);
        }
    }
    
    private function getChatParticipants(Chat $chat): array
    {
        if ($chat->chatable_type === 'App\\Models\\Chat\\GroupChat') {
            return DB::table('group_chat_members')
                ->where('group_chat_id', $chat->chatable_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();
        } else {
            return DB::table('chat_participants')
                ->where('chat_id', $chat->id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();
        }
    }
    
    private function getConversationStarter(Chat $chat): string
    {
        if ($chat->chatable_type === 'App\\Models\\Chat\\GroupChat') {
            $groupStarters = [
                'Hey everyone! How\'s your week going so far?',
                'Did anyone catch the latest episode last night?',
                'Planning something fun for the weekend, anyone interested?',
                'Quick question for the group - what do you think about...',
                'Good morning team! Ready for another productive day?',
                'Anyone else excited about the upcoming event?',
                'Sharing this amazing article I found...',
                'Can\'t believe how fast this month is flying by!',
            ];
            return fake()->randomElement($groupStarters);
        } else {
            $privateStarters = [
                'Hey! How are you doing today?',
                'Just wanted to check in and see how things are going',
                'You won\'t believe what happened to me today...',
                'Thanks for the other day, really appreciated it!',
                'Quick question - are you free this weekend?',
                'Hope you\'re having a great day!',
                'Thinking of you, hope everything is well',
                'Got a minute to chat about something?',
            ];
            return fake()->randomElement($privateStarters);
        }
    }
    
    private function getReplyContent(int $position): string
    {
        $replies = [
            // Early replies
            [
                'That sounds great!',
                'I totally agree with you',
                'Really? Tell me more!',
                'Interesting perspective',
                'Count me in!',
            ],
            // Middle replies
            [
                'I was thinking the same thing',
                'That makes a lot of sense',
                'Good point!',
                'I hadn\'t thought of it that way',
                'Absolutely!',
            ],
            // Later replies
            [
                'Thanks for sharing that',
                'This has been really helpful',
                'Great discussion everyone!',
                'Looking forward to hearing more',
                'Let\'s definitely follow up on this',
            ],
        ];
        
        $replySet = $position < 2 ? 0 : ($position < 5 ? 1 : 2);
        return fake()->randomElement($replies[$replySet]);
    }
}