<?php

namespace Database\Seeders;

use App\Models\Discovery;
use App\Models\Filter;
use App\Models\Like;
use App\Models\Match;
use App\Models\View;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class MatchingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the matching domain seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Iniciando seeder del dominio Matching...');

        // Obtener usuarios existentes
        $users = User::all();
        
        if ($users->count() === 0) {
            $this->command->error('❌ No hay usuarios en la base de datos. Ejecuta UserSeeder primero.');
            return;
        }

        $this->command->info("👥 Usuarios encontrados: {$users->count()}");

        // 1. Crear Filtros
        $this->seedFilters($users);
        
        // 2. Crear Sesiones de Descubrimiento
        $this->seedDiscoveries($users);
        
        // 3. Crear Likes (incluye algunos matches mutuos)
        $this->seedLikes($users);
        
        // 4. Crear Matches explícitos
        $this->seedMatches($users);
        
        // 5. Crear Vistas de Perfiles
        $this->seedViews($users);

        $this->command->info('✅ Seeder del dominio Matching completado exitosamente!');
        $this->printSeedingStats();
    }

    /**
     * Seed filters for users
     */
    private function seedFilters(Collection $users): void
    {
        $this->command->info('🔍 Creando filtros de búsqueda...');
        
        $filterCounts = [
            'basic' => 0,
            'demographic' => 0,
            'lifestyle' => 0,
            'interest' => 0,
            'relationship' => 0,
            'premium' => 0,
            'behavioral' => 0,
            'custom' => 0,
        ];

        foreach ($users as $user) {
            // Cada usuario tiene al menos un filtro básico
            Filter::factory()->basicCategory()->defaultFilter()->create(['user_id' => $user->id]);
            $filterCounts['basic']++;
            
            // 80% tienen filtro demográfico
            if (fake()->boolean(80)) {
                Filter::factory()->demographicCategory()->active()->create(['user_id' => $user->id]);
                $filterCounts['demographic']++;
            }
            
            // 70% tienen filtro de estilo de vida
            if (fake()->boolean(70)) {
                Filter::factory()->lifestyleCategory()->active()->create(['user_id' => $user->id]);
                $filterCounts['lifestyle']++;
            }
            
            // 60% tienen filtro de intereses
            if (fake()->boolean(60)) {
                Filter::factory()->interestCategory()->frequentlyUsed()->create(['user_id' => $user->id]);
                $filterCounts['interest']++;
            }
            
            // 40% tienen filtro de relación
            if (fake()->boolean(40)) {
                Filter::factory()->relationshipCategory()->active()->create(['user_id' => $user->id]);
                $filterCounts['relationship']++;
            }
            
            // 25% tienen filtros premium (usuarios premium)
            if (fake()->boolean(25)) {
                Filter::factory()->premiumCategory()->highSuccessRate()->create(['user_id' => $user->id]);
                $filterCounts['premium']++;
            }
            
            // 20% tienen filtro comportamental
            if (fake()->boolean(20)) {
                Filter::factory()->behavioralCategory()->active()->create(['user_id' => $user->id]);
                $filterCounts['behavioral']++;
            }
            
            // 15% tienen filtros personalizados
            if (fake()->boolean(15)) {
                Filter::factory()->customCategory()->create(['user_id' => $user->id]);
                $filterCounts['custom']++;
            }
        }

        $total = array_sum($filterCounts);
        $this->command->info("   ✅ Filtros creados: {$total}");
        foreach ($filterCounts as $category => $count) {
            $this->command->line("      - {$category}: {$count}");
        }
    }

    /**
     * Seed discovery sessions
     */
    private function seedDiscoveries(Collection $users): void
    {
        $this->command->info('🔍 Creando sesiones de descubrimiento...');
        
        $sessionCounts = [
            'standard' => 0,
            'explore' => 0,
            'boost' => 0,
            'local' => 0,
            'global' => 0,
            'interest' => 0,
            'second_chance' => 0,
            'trending' => 0,
        ];

        foreach ($users as $user) {
            // Cada usuario activo tiene algunas sesiones de descubrimiento
            $sessionCount = fake()->numberBetween(2, 15);
            
            for ($i = 0; $i < $sessionCount; $i++) {
                $mode = fake()->randomElement([
                    'standard', 'explore', 'boost', 'local', 
                    'global', 'interest', 'second_chance', 'trending'
                ]);
                
                $factory = Discovery::factory();
                
                // Aplicar estado específico según el modo
                switch ($mode) {
                    case 'standard':
                        $discovery = $factory->standardMode();
                        break;
                    case 'explore':
                        $discovery = $factory->exploreMode();
                        break;
                    case 'boost':
                        $discovery = $factory->boostMode()->premiumUser();
                        break;
                    case 'local':
                        $discovery = $factory->localMode();
                        break;
                    case 'global':
                        $discovery = $factory->globalMode()->premiumUser();
                        break;
                    case 'interest':
                        $discovery = $factory->interestBasedMode();
                        break;
                    case 'second_chance':
                        $discovery = $factory->secondChanceMode();
                        break;
                    case 'trending':
                        $discovery = $factory->trendingMode();
                        break;
                }
                
                // 70% de las sesiones están completadas
                if (fake()->boolean(70)) {
                    $discovery = $discovery->completed();
                } else {
                    $discovery = $discovery->active();
                }
                
                // 20% de las sesiones tienen alto engagement
                if (fake()->boolean(20)) {
                    $discovery = $discovery->highEngagement();
                }
                
                $discovery->create(['user_id' => $user->id]);
                $sessionCounts[$mode]++;
            }
        }

        $total = array_sum($sessionCounts);
        $this->command->info("   ✅ Sesiones de descubrimiento creadas: {$total}");
        foreach ($sessionCounts as $mode => $count) {
            $this->command->line("      - {$mode}: {$count}");
        }
    }

    /**
     * Seed likes and create some mutual matches
     */
    private function seedLikes(Collection $users): void
    {
        $this->command->info('❤️ Creando likes y interacciones...');
        
        $likeCounts = [
            'like' => 0,
            'pass' => 0,
            'super_like' => 0,
            'mutual_matches' => 0,
        ];

        foreach ($users as $user) {
            // Cada usuario ha hecho varias interacciones
            $interactionCount = fake()->numberBetween(10, 80);
            $otherUsers = $users->where('id', '!=', $user->id)->random(min($interactionCount, $users->count() - 1));
            
            foreach ($otherUsers as $targetUser) {
                $likeType = fake()->randomElement(['like', 'pass', 'super_like']);
                
                // Verificar que no existe ya un like entre estos usuarios
                $existingLike = Like::where('user_id', $user->id)
                    ->where('target_user_id', $targetUser->id)
                    ->first();
                
                if ($existingLike) {
                    continue;
                }
                
                $factory = Like::factory();
                
                // Aplicar tipo específico
                switch ($likeType) {
                    case 'like':
                        $factory = $factory->like();
                        break;
                    case 'pass':
                        $factory = $factory->pass();
                        break;
                    case 'super_like':
                        $factory = $factory->superLike();
                        break;
                }
                
                // Determinar fuente
                $source = fake()->randomElement([
                    'fromDiscoveryStandard', 'fromDiscoveryBoost', 'fromDiscoveryLocal', 'fromSecondChance'
                ]);
                
                $like = $factory->$source()->create([
                    'user_id' => $user->id,
                    'target_user_id' => $targetUser->id,
                ]);
                
                $likeCounts[$likeType]++;
                
                // 15% chance de que sea mutual (para likes y super likes)
                if (in_array($likeType, ['like', 'super_like']) && fake()->boolean(15)) {
                    // Crear el like recíproco si no existe
                    $reciprocalLike = Like::where('user_id', $targetUser->id)
                        ->where('target_user_id', $user->id)
                        ->first();
                    
                    if (!$reciprocalLike) {
                        $reciprocalLike = Like::factory()->like()->mutual()->create([
                            'user_id' => $targetUser->id,
                            'target_user_id' => $user->id,
                        ]);
                        
                        // Actualizar el like original para marcarlo como mutual
                        $like->update([
                            'is_mutual' => true,
                            'matched_at' => $reciprocalLike->created_at,
                        ]);
                        
                        $likeCounts['mutual_matches']++;
                    }
                }
            }
        }

        $total = $likeCounts['like'] + $likeCounts['pass'] + $likeCounts['super_like'];
        $this->command->info("   ✅ Likes creados: {$total}");
        foreach ($likeCounts as $type => $count) {
            $this->command->line("      - {$type}: {$count}");
        }
    }

    /**
     * Seed explicit matches
     */
    private function seedMatches(Collection $users): void
    {
        $this->command->info('💖 Creando matches explícitos...');
        
        $matchCounts = [
            'active' => 0,
            'expired' => 0,
            'unmatched' => 0,
            'blocked' => 0,
            'with_messages' => 0,
            'high_engagement' => 0,
        ];

        // Obtener likes mutuos para crear matches
        $mutualLikes = Like::where('is_mutual', true)->get()->groupBy('user_id');
        
        foreach ($mutualLikes as $userId => $userLikes) {
            $user = $users->firstWhere('id', $userId);
            
            foreach ($userLikes as $like) {
                // Verificar si ya existe un match
                $existingMatch = Match::where(function ($query) use ($userId, $like) {
                    $query->where('user_id', $userId)->where('matched_user_id', $like->target_user_id);
                })->orWhere(function ($query) use ($userId, $like) {
                    $query->where('user_id', $like->target_user_id)->where('matched_user_id', $userId);
                })->first();
                
                if ($existingMatch) {
                    continue;
                }
                
                $factory = Match::factory();
                
                // 80% matches activos
                if (fake()->boolean(80)) {
                    $factory = $factory->active();
                    $matchCounts['active']++;
                } else {
                    // Estados alternativos
                    $status = fake()->randomElement(['expired', 'unmatched', 'blocked']);
                    switch ($status) {
                        case 'expired':
                            $factory = $factory->expired();
                            $matchCounts['expired']++;
                            break;
                        case 'unmatched':
                            $factory = $factory->unmatched();
                            $matchCounts['unmatched']++;
                            break;
                        case 'blocked':
                            $factory = $factory->blocked();
                            $matchCounts['blocked']++;
                            break;
                    }
                }
                
                // 30% con alta compatibilidad
                if (fake()->boolean(30)) {
                    $factory = $factory->highCompatibility();
                }
                
                // 40% con primer mensaje enviado
                if (fake()->boolean(40)) {
                    $factory = $factory->withFirstMessage();
                    $matchCounts['with_messages']++;
                }
                
                // 20% con alto engagement
                if (fake()->boolean(20)) {
                    $factory = $factory->highEngagement();
                    $matchCounts['high_engagement']++;
                }
                
                // Determinar source basado en el tipo de like
                $source = $like->type === 'super_like' ? 'super_like' : 'mutual_like';
                
                $factory->create([
                    'user_id' => $userId,
                    'matched_user_id' => $like->target_user_id,
                    'match_source' => $source,
                ]);
            }
        }

        // Crear algunos matches adicionales para variedad
        $additionalMatches = min(50, intval($users->count() * 0.8));
        
        for ($i = 0; $i < $additionalMatches; $i++) {
            $user = $users->random();
            $matchedUser = $users->where('id', '!=', $user->id)->random();
            
            // Verificar duplicados
            $exists = Match::where(function ($query) use ($user, $matchedUser) {
                $query->where('user_id', $user->id)->where('matched_user_id', $matchedUser->id);
            })->orWhere(function ($query) use ($user, $matchedUser) {
                $query->where('user_id', $matchedUser->id)->where('matched_user_id', $user->id);
            })->exists();
            
            if (!$exists) {
                $factory = Match::factory()->active();
                
                if (fake()->boolean(25)) {
                    $factory = $factory->progressedToDate();
                }
                
                $factory->create([
                    'user_id' => $user->id,
                    'matched_user_id' => $matchedUser->id,
                ]);
                
                $matchCounts['active']++;
            }
        }

        $total = array_sum(array_slice($matchCounts, 0, 4)); // Solo contar los estados principales
        $this->command->info("   ✅ Matches creados: {$total}");
        foreach ($matchCounts as $type => $count) {
            $this->command->line("      - {$type}: {$count}");
        }
    }

    /**
     * Seed profile views
     */
    private function seedViews(Collection $users): void
    {
        $this->command->info('👀 Creando vistas de perfiles...');
        
        $viewCounts = [
            'profile_card' => 0,
            'detailed_profile' => 0,
            'photo_focus' => 0,
            'bio_focus' => 0,
            'quick_glance' => 0,
            'with_conversion' => 0,
            'high_engagement' => 0,
        ];

        foreach ($users as $user) {
            // Cada usuario ha visto varios perfiles
            $viewCount = fake()->numberBetween(20, 150);
            $viewedUsers = $users->where('id', '!=', $user->id)->random(min($viewCount, $users->count() - 1));
            
            foreach ($viewedUsers as $viewedUser) {
                $viewType = fake()->randomElement([
                    'profile_card', 'detailed_profile', 'photo_focus', 'bio_focus', 'quick_glance'
                ]);
                
                $factory = View::factory();
                
                // Aplicar tipo de vista específico
                switch ($viewType) {
                    case 'profile_card':
                        $factory = $factory->profileCard();
                        break;
                    case 'detailed_profile':
                        $factory = $factory->detailedProfile();
                        break;
                    case 'photo_focus':
                        $factory = $factory->photoFocus();
                        break;
                    case 'bio_focus':
                        $factory = $factory->bioFocus();
                        break;
                    case 'quick_glance':
                        $factory = $factory->quickGlance();
                        break;
                }
                
                // Fuente de la vista
                $source = fake()->randomElement([
                    'fromDiscovery', 'fromSearch', 'fromLikedYou'
                ]);
                $factory = $factory->$source();
                
                // 30% son visitantes recurrentes
                if (fake()->boolean(30)) {
                    $factory = $factory->returnVisitor();
                }
                
                // 25% tienen conversión
                if (fake()->boolean(25)) {
                    $factory = $factory->withConversion();
                    $viewCounts['with_conversion']++;
                }
                
                // 15% tienen alto engagement
                if (fake()->boolean(15)) {
                    $factory = $factory->highEngagement();
                    $viewCounts['high_engagement']++;
                }
                
                // 70% desde móvil, 30% desde web
                if (fake()->boolean(70)) {
                    $factory = $factory->mobileDevice();
                } else {
                    $factory = $factory->webDevice();
                }
                
                $factory->create([
                    'viewer_id' => $user->id,
                    'viewed_user_id' => $viewedUser->id,
                ]);
                
                $viewCounts[$viewType]++;
            }
        }

        $total = array_sum(array_slice($viewCounts, 0, 5)); // Solo contar los tipos de vista
        $this->command->info("   ✅ Vistas de perfiles creadas: {$total}");
        foreach (['profile_card', 'detailed_profile', 'photo_focus', 'bio_focus', 'quick_glance'] as $type) {
            $this->command->line("      - {$type}: {$viewCounts[$type]}");
        }
        $this->command->line("      - con conversión: {$viewCounts['with_conversion']}");
        $this->command->line("      - alto engagement: {$viewCounts['high_engagement']}");
    }

    /**
     * Print final seeding statistics
     */
    private function printSeedingStats(): void
    {
        $this->command->info('');
        $this->command->info('📊 Estadísticas finales del seeding:');
        
        $filters = Filter::count();
        $discoveries = Discovery::count();
        $likes = Like::count();
        $matches = Match::count();
        $views = View::count();
        
        $this->command->table(
            ['Modelo', 'Cantidad', 'Descripción'],
            [
                ['Filters', number_format($filters), 'Filtros de búsqueda de usuarios'],
                ['Discoveries', number_format($discoveries), 'Sesiones de descubrimiento'],
                ['Likes', number_format($likes), 'Likes, passes y super likes'],
                ['Matches', number_format($matches), 'Matches mutuos entre usuarios'],
                ['Views', number_format($views), 'Vistas de perfiles de usuarios'],
            ]
        );
        
        // Estadísticas adicionales
        $mutualLikes = Like::where('is_mutual', true)->count();
        $activeMatches = Match::where('status', 'active')->count();
        $highEngagementViews = View::where('engagement_score', '>', 70)->count();
        
        $this->command->info('');
        $this->command->info('🎯 Métricas clave:');
        $this->command->line("   - Likes mutuos: " . number_format($mutualLikes));
        $this->command->line("   - Matches activos: " . number_format($activeMatches));
        $this->command->line("   - Vistas alto engagement: " . number_format($highEngagementViews));
        
        $this->command->info('');
        $this->command->info('✨ El dominio Matching está listo para la aplicación ForeverUsInLove!');
    }
}