<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat\VideoCall;
use App\Models\Chat\CallHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VideoCallAnalyticsSeeder extends Seeder
{
    /**
     * Create realistic video call analytics and performance data
     */
    public function run(): void
    {
        $this->command->info('📹 Creating video call analytics data...');
        
        $this->createScheduledCalls();
        $this->createRecurringMeetingSeries();
        $this->createCallWithTechnicalIssues();
        $this->createHighEngagementCalls();
        $this->createPremiumFeatureCalls();
        
        $this->command->info('✅ Video call analytics seeding completed!');
    }
    
    private function createScheduledCalls(): void
    {
        $this->command->info('Creating scheduled video calls...');
        
        $users = User::inRandomOrder()->limit(50)->get();
        
        // Weekly team meetings
        for ($i = 0; $i < 10; $i++) {
            $host = $users->random();
            $scheduledTime = fake()->dateTimeBetween('now', '+2 weeks');
            
            $videoCall = VideoCall::factory()->create([
                'initiated_by' => $host->id,
                'title' => fake()->randomElement([
                    'Weekly Team Standup',
                    'Project Review Meeting',
                    'Monthly Planning Session',
                    'Client Presentation',
                    'Team Building Session'
                ]),
                'type' => 'conference',
                'status' => 'pending',
                'scheduled_at' => $scheduledTime,
                'is_recurring' => fake()->boolean(60),
                'recurrence_settings' => fake()->boolean(60) ? [
                    'pattern' => 'weekly',
                    'interval' => 1,
                    'days' => ['monday'],
                    'end_date' => fake()->dateTimeBetween('+1 month', '+6 months')
                ] : null,
                'max_participants' => fake()->numberBetween(5, 25),
                'is_recorded' => fake()->boolean(40),
                'waiting_room_enabled' => fake()->boolean(70),
                'require_authentication' => true,
            ]);
            
            // Add participants
            $participantCount = fake()->numberBetween(3, 8);
            $participants = $users->random($participantCount);
            
            foreach ($participants as $participant) {
                DB::table('video_call_participants')->insert([
                    'id' => fake()->uuid(),
                    'video_call_id' => $videoCall->id,
                    'user_id' => $participant->id,
                    'status' => 'invited',
                    'role' => $participant->id === $host->id ? 'host' : 'participant',
                    'invited_at' => now(),
                    'can_share_screen' => fake()->boolean(80),
                    'can_record' => $participant->id === $host->id,
                    'billing_tier' => fake()->randomElement(['free', 'premium', 'enterprise']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
    
    private function createRecurringMeetingSeries(): void
    {
        $this->command->info('Creating recurring meeting series...');
        
        $users = User::inRandomOrder()->limit(20)->get();
        $seriesId = fake()->uuid();
        
        // Create series of 5 past meetings
        for ($week = 4; $week >= 0; $week--) {
            $meetingTime = Carbon::now()->subWeeks($week)->setTime(14, 0, 0);
            
            $videoCall = VideoCall::factory()->create([
                'initiated_by' => $users->first()->id,
                'title' => 'Weekly Product Review',
                'type' => 'conference',
                'status' => $week === 0 ? 'active' : 'ended',
                'scheduled_at' => $meetingTime,
                'started_at' => $week > 0 ? $meetingTime->copy()->addMinutes(2) : null,
                'ended_at' => $week > 0 ? $meetingTime->copy()->addMinutes(fake()->numberBetween(45, 90)) : null,
                'duration' => $week > 0 ? fake()->numberBetween(2700, 5400) : null,
                'is_recurring' => true,
                'recurring_series_id' => $seriesId,
                'is_recorded' => true,
                'recording_url' => $week > 0 ? 'https://recordings.example.com/' . fake()->uuid() : null,
            ]);
            
            if ($week > 0) {
                // Add detailed analytics for completed calls
                $this->addDetailedCallMetrics($videoCall, $users->take(6));
            }
        }
    }
    
    private function createCallWithTechnicalIssues(): void
    {
        $this->command->info('Creating calls with technical issues...');
        
        $users = User::inRandomOrder()->limit(10)->get();
        
        $videoCall = VideoCall::factory()->create([
            'initiated_by' => $users->first()->id,
            'title' => 'Emergency Client Call',
            'status' => 'ended',
            'started_at' => fake()->dateTimeBetween('-1 week', '-1 day'),
            'ended_at' => fake()->dateTimeBetween('-1 week', '-1 day'),
            'duration' => 1800, // 30 minutes
            'connection_metrics' => [
                'average_latency' => 150.5,
                'peak_latency' => 350.2,
                'packet_loss_rate' => 2.3,
                'connection_drops' => 3,
                'quality_degradations' => 8
            ],
            'quality_metrics' => [
                'overall_score' => 2.1,
                'audio_score' => 3.2,
                'video_score' => 1.8,
                'stability_score' => 2.0
            ],
            'technical_issues' => [
                'bandwidth_limitations' => 4,
                'audio_dropouts' => 12,
                'video_freezing' => 8,
                'connection_timeouts' => 3
            ]
        ]);
        
        foreach ($users->take(5) as $user) {
            DB::table('video_call_participants')->insert([
                'id' => fake()->uuid(),
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'status' => 'left',
                'role' => $user->id === $users->first()->id ? 'host' : 'participant',
                'invited_at' => $videoCall->created_at,
                'joined_at' => $videoCall->started_at,
                'left_at' => $videoCall->ended_at,
                'connection_quality' => fake()->numberBetween(1, 3),
                'connection_drops' => fake()->numberBetween(1, 4),
                'reconnection_attempts' => fake()->numberBetween(2, 6),
                'technical_issues' => [
                    'audio_issues' => fake()->boolean(70),
                    'video_issues' => fake()->boolean(60), 
                    'connection_problems' => fake()->boolean(80),
                    'browser_crashes' => fake()->boolean(20)
                ],
                'call_experience_rating' => fake()->numberBetween(1, 3),
                'audio_quality_rating' => fake()->numberBetween(1, 3),
                'video_quality_rating' => fake()->numberBetween(1, 2),
                'feedback_comments' => fake()->randomElement([
                    'Audio kept cutting out',
                    'Video was very laggy',
                    'Had to reconnect multiple times',
                    'Poor connection quality throughout'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Create call history with issues
            CallHistory::factory()->create([
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'joined_at' => $videoCall->started_at,
                'left_at' => $videoCall->ended_at,
                'duration' => fake()->numberBetween(1200, 1800),
                'connection_quality_score' => fake()->numberBetween(1, 3),
                'audio_quality_score' => fake()->numberBetween(1, 3),
                'video_quality_score' => fake()->numberBetween(1, 2),
                'connection_drops' => fake()->numberBetween(1, 4),
                'technical_issues' => [
                    'network_instability' => true,
                    'bandwidth_issues' => true,
                    'device_problems' => fake()->boolean(30)
                ],
                'overall_rating' => fake()->numberBetween(1, 3),
                'feedback_comments' => 'Technical difficulties throughout the call'
            ]);
        }
    }
    
    private function createHighEngagementCalls(): void
    {
        $this->command->info('Creating high engagement video calls...');
        
        $users = User::inRandomOrder()->limit(15)->get();
        
        $videoCall = VideoCall::factory()->create([
            'initiated_by' => $users->first()->id,
            'title' => 'All-Hands Company Meeting',
            'type' => 'broadcast',
            'status' => 'ended',
            'started_at' => fake()->dateTimeBetween('-3 days', '-1 day'),
            'ended_at' => fake()->dateTimeBetween('-3 days', '-1 day'),
            'duration' => 3600, // 1 hour
            'max_participants' => 50,
            'is_recorded' => true,
            'allow_chat' => true,
            'allow_reactions' => true,
            'connection_metrics' => [
                'average_latency' => 45.2,
                'peak_latency' => 89.1,
                'packet_loss_rate' => 0.2,
                'connection_drops' => 0,
                'overall_stability' => 4.8
            ],
            'engagement_metrics' => [
                'total_reactions' => 287,
                'total_chat_messages' => 156,
                'average_attention_score' => 4.3,
                'speaking_time_distribution' => 'balanced',
                'interaction_rate' => 0.85
            ]
        ]);
        
        foreach ($users->take(12) as $index => $user) {
            $speakingTime = $index < 3 ? fake()->numberBetween(300, 900) : fake()->numberBetween(0, 120);
            
            DB::table('video_call_participants')->insert([
                'id' => fake()->uuid(),
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'status' => 'left',
                'role' => $index === 0 ? 'host' : ($index < 3 ? 'moderator' : 'participant'),
                'invited_at' => $videoCall->created_at,
                'joined_at' => $videoCall->started_at,
                'left_at' => $videoCall->ended_at,
                'speaking_time' => $speakingTime,
                'speaking_percentage' => round(($speakingTime / 3600) * 100, 2),
                'reactions_sent' => fake()->numberBetween(5, 25),
                'chat_messages_sent' => fake()->numberBetween(2, 20),
                'used_reactions' => true,
                'used_chat' => true,
                'used_screen_share' => $index < 3,
                'connection_quality' => fake()->numberBetween(4, 5),
                'call_experience_rating' => fake()->numberBetween(4, 5),
                'audio_quality_rating' => fake()->numberBetween(4, 5),
                'video_quality_rating' => fake()->numberBetween(4, 5),
                'feedback_comments' => fake()->randomElement([
                    'Great presentation, very engaging!',
                    'Loved the interactive format',
                    'Crystal clear audio and video quality',
                    'Well organized and informative'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            CallHistory::factory()->create([
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'joined_at' => $videoCall->started_at,
                'duration' => 3600,
                'connection_quality_score' => fake()->numberBetween(4, 5),
                'speaking_time' => $speakingTime,
                'speaking_percentage' => round(($speakingTime / 3600) * 100, 2),
                'reactions_sent' => fake()->numberBetween(5, 25),
                'messages_sent' => fake()->numberBetween(2, 20),
                'overall_rating' => fake()->numberBetween(4, 5),
                'audio_quality_rating' => fake()->numberBetween(4, 5),
                'video_quality_rating' => fake()->numberBetween(4, 5),
                'engagement_metrics' => [
                    'attention_score' => fake()->randomFloat(2, 3.5, 5.0),
                    'participation_level' => fake()->randomElement(['high', 'medium', 'low']),
                    'interaction_frequency' => fake()->numberBetween(10, 50)
                ]
            ]);
        }
    }
    
    private function createPremiumFeatureCalls(): void
    {
        $this->command->info('Creating premium feature video calls...');
        
        $users = User::inRandomOrder()->limit(8)->get();
        
        $videoCall = VideoCall::factory()->create([
            'initiated_by' => $users->first()->id,
            'title' => 'Executive Strategy Session',
            'type' => 'conference',
            'status' => 'ended',
            'started_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'ended_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'duration' => 7200, // 2 hours
            'video_quality' => '4k',
            'audio_quality' => 'hifi',
            'is_recorded' => true,
            'recording_size' => 2147483648, // 2GB
            'is_encrypted' => true,
            'waiting_room_enabled' => true,
            'background_blur_enabled' => true,
            'virtual_backgrounds_enabled' => true,
            'noise_cancellation_enabled' => true,
            'live_captions_enabled' => true,
            'allow_breakout_rooms' => true,
            'billing_tier' => 'enterprise',
            'total_cost' => 89.50,
            'ai_features' => [
                'live_transcription' => true,
                'sentiment_analysis' => true,
                'meeting_summary' => true,
                'action_items_extraction' => true
            ]
        ]);
        
        foreach ($users->take(6) as $index => $user) {
            DB::table('video_call_participants')->insert([
                'id' => fake()->uuid(),
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'status' => 'left',
                'role' => $index === 0 ? 'host' : 'participant',
                'invited_at' => $videoCall->created_at,
                'joined_at' => $videoCall->started_at,
                'left_at' => $videoCall->ended_at,
                'went_through_waiting_room' => true,
                'waiting_room_duration' => fake()->numberBetween(30, 180),
                'used_virtual_background' => fake()->boolean(60),
                'used_background_blur' => fake()->boolean(40),
                'used_noise_cancellation' => fake()->boolean(80),
                'consented_to_recording' => true,
                'end_to_end_encryption_used' => true,
                'billing_tier' => 'enterprise',
                'participation_cost' => fake()->randomFloat(2, 10.00, 20.00),
                'premium_features_used' => [
                    'hd_video' => true,
                    'cloud_recording' => true,
                    'ai_transcription' => true,
                    'advanced_analytics' => true
                ],
                'connection_quality' => 5,
                'call_experience_rating' => 5,
                'audio_quality_rating' => 5,
                'video_quality_rating' => 5,
                'ease_of_use_rating' => 5,
                'feedback_comments' => fake()->randomElement([
                    'Excellent premium features, worth the cost',
                    'AI transcription was incredibly accurate',
                    'Best video call quality I\'ve experienced',
                    'Professional grade features for business use'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            CallHistory::factory()->create([
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'joined_at' => $videoCall->started_at,
                'duration' => 7200,
                'connection_quality_score' => 5,
                'audio_quality_score' => 5,
                'video_quality_score' => 5,
                'overall_rating' => 5,
                'billing_tier' => 'enterprise',
                'call_cost' => fake()->randomFloat(2, 10.00, 20.00),
                'premium_features_used' => [
                    'enterprise_tier' => true,
                    'ai_features' => true,
                    '4k_video' => true,
                    'unlimited_duration' => true
                ]
            ]);
        }
    }
    
    private function addDetailedCallMetrics(VideoCall $videoCall, $participants): void
    {
        foreach ($participants as $index => $user) {
            $joinTime = $videoCall->started_at->copy()->addMinutes(fake()->numberBetween(0, 5));
            $duration = $videoCall->duration - fake()->numberBetween(0, 300);
            
            DB::table('video_call_participants')->insert([
                'id' => fake()->uuid(),
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'status' => 'left',
                'role' => $index === 0 ? 'host' : 'participant',
                'invited_at' => $videoCall->created_at,
                'joined_at' => $joinTime,
                'left_at' => $videoCall->ended_at,
                'speaking_time' => fake()->numberBetween(120, 900),
                'reactions_sent' => fake()->numberBetween(0, 15),
                'chat_messages_sent' => fake()->numberBetween(0, 8),
                'connection_quality' => fake()->numberBetween(3, 5),
                'call_experience_rating' => fake()->numberBetween(3, 5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            CallHistory::factory()->create([
                'video_call_id' => $videoCall->id,
                'user_id' => $user->id,
                'joined_at' => $joinTime,
                'duration' => $duration,
                'connection_quality_score' => fake()->numberBetween(3, 5),
                'overall_rating' => fake()->numberBetween(3, 5),
            ]);
        }
    }
}