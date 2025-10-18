<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Events\UserBlocked;
use App\Domain\Moderation\Repositories\ReportRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Exceptions\BlockNotFoundException;
use App\Exceptions\BlockValidationException;
use App\Exceptions\SelfBlockException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\DuplicateBlockException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Servicio integral para la gestión de bloqueos entre usuarios en ForeverUsInLove
 * 
 * Este servicio maneja todas las operaciones relacionadas con el bloqueo y desbloqueo
 * de usuarios, incluyendo bloqueos mutuos, temporales, y la gestión de las
 * consecuencias del bloqueo en matches, conversaciones y visibilidad.
 * 
 * Características principales:
 * - Sistema de bloqueos bidireccionales y unidireccionales
 * - Bloqueos temporales con expiración automática
 * - Bloqueos por tipo de interacción (mensajes, matches, visitas al perfil)
 * - Gestión de bloqueos masivos para protección contra acoso
 * - Analytics de patrones de bloqueo para detectar usuarios problemáticos
 * - Sistema de "soft blocks" (limitación sin notificación)
 * - Bloqueos automáticos basados en comportamiento sospechoso
 * - Integración con sistema de reportes para escalamiento automático
 * 
 * @package App\Domain\Moderation\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class BlockService
{
    /**
     * Tipos de bloqueo disponibles
     */
    public const BLOCK_TYPES = [
        'COMPLETE' => 'complete',              // Bloqueo completo (todos los interactions)
        'MESSAGING' => 'messaging',            // Solo bloqueo de mensajes
        'PROFILE_VIEW' => 'profile_view',      // Bloqueo de visualización de perfil
        'MATCHING' => 'matching',              // Bloqueo de aparición en matches
        'SEARCH_RESULTS' => 'search_results',  // Bloqueo en resultados de búsqueda
        'SOFT_BLOCK' => 'soft_block'          // Bloqueo silencioso (shadow ban)
    ];

    /**
     * Razones de bloqueo
     */
    public const BLOCK_REASONS = [
        'HARASSMENT' => 'harassment',
        'INAPPROPRIATE_MESSAGES' => 'inappropriate_messages',
        'FAKE_PROFILE' => 'fake_profile',
        'SPAM' => 'spam',
        'PERSONAL_PREFERENCE' => 'personal_preference',
        'SAFETY_CONCERNS' => 'safety_concerns',
        'OFFENSIVE_BEHAVIOR' => 'offensive_behavior',
        'UNWANTED_CONTACT' => 'unwanted_contact',
        'SCAM_ATTEMPT' => 'scam_attempt',
        'POLICY_VIOLATION' => 'policy_violation'
    ];

    /**
     * Estados del bloqueo
     */
    public const BLOCK_STATUSES = [
        'ACTIVE' => 'active',
        'EXPIRED' => 'expired',
        'LIFTED' => 'lifted',
        'PENDING_REVIEW' => 'pending_review'
    ];

    /**
     * Duración de bloqueos temporales (en horas)
     */
    public const TEMPORARY_DURATIONS = [
        '1_HOUR' => 1,
        '6_HOURS' => 6,
        '24_HOURS' => 24,
        '3_DAYS' => 72,
        '7_DAYS' => 168,
        '30_DAYS' => 720
    ];

    /**
     * Constructor del servicio de bloqueos
     */
    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MatchRepositoryInterface $matchRepository
    ) {}

    /**
     * Bloquea a un usuario
     *
     * @param int $blockerId ID del usuario que bloquea
     * @param int $blockedId ID del usuario a bloquear
     * @param string $type Tipo de bloqueo
     * @param string|null $reason Razón del bloqueo
     * @param int|null $durationHours Duración en horas (null = permanente)
     * @param array $metadata Metadatos adicionales
     * @return array Resultado del bloqueo
     * @throws BlockValidationException
     * @throws SelfBlockException
     * @throws DuplicateBlockException
     */
    public function blockUser(
        int $blockerId,
        int $blockedId,
        string $type = self::BLOCK_TYPES['COMPLETE'],
        ?string $reason = null,
        ?int $durationHours = null,
        array $metadata = []
    ): array {
        try {
            Log::info('Iniciando proceso de bloqueo', [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'type' => $type
            ]);

            // Validaciones básicas
            $this->validateBlockRequest($blockerId, $blockedId, $type, $reason);

            // Verificar bloqueo existente
            if ($this->isUserBlocked($blockerId, $blockedId, $type)) {
                throw new DuplicateBlockException('El usuario ya está bloqueado con este tipo de bloqueo');
            }

            DB::beginTransaction();

            // Crear el bloqueo
            $blockData = [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'type' => $type,
                'reason' => $reason,
                'status' => self::BLOCK_STATUSES['ACTIVE'],
                'expires_at' => $durationHours ? Carbon::now()->addHours($durationHours) : null,
                'metadata' => array_merge($metadata, [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => Carbon::now()->toISOString()
                ])
            ];

            $block = $this->reportRepository->createBlock($blockData);

            // Aplicar consecuencias del bloqueo
            $consequences = $this->applyBlockConsequences($blockerId, $blockedId, $type);

            // Verificar si debería ser bloqueo mutuo
            if ($this->shouldCreateMutualBlock($blockerId, $blockedId)) {
                $this->createMutualBlock($blockedId, $blockerId, $type, 'automatic_mutual');
            }

            // Disparar evento de usuario bloqueado
            Event::dispatch(new UserBlocked(
                $block['id'],
                $blockerId,
                $blockedId,
                $type,
                $reason,
                $durationHours
            ));

            // Actualizar estadísticas
            $this->updateBlockStatistics($blockerId, $blockedId, $type);

            // Limpiar caches relevantes
            $this->clearBlockCache($blockerId, $blockedId);

            DB::commit();

            Log::info('Usuario bloqueado exitosamente', [
                'block_id' => $block['id'],
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId
            ]);

            return [
                'success' => true,
                'block_id' => $block['id'],
                'type' => $type,
                'is_temporary' => !is_null($durationHours),
                'expires_at' => $durationHours ? Carbon::now()->addHours($durationHours)->toISOString() : null,
                'consequences' => $consequences,
                'mutual_block_created' => isset($consequences['mutual_block']),
                'reference_number' => $this->generateBlockReference($block['id'])
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al bloquear usuario', [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Desbloquea a un usuario
     *
     * @param int $blockerId ID del usuario que desbloquea
     * @param int $blockedId ID del usuario a desbloquear
     * @param string|null $type Tipo específico de bloqueo a remover
     * @param string|null $reason Razón del desbloqueo
     * @return array Resultado del desbloqueo
     * @throws BlockNotFoundException
     */
    public function unblockUser(
        int $blockerId,
        int $blockedId,
        ?string $type = null,
        ?string $reason = null
    ): array {
        try {
            // Buscar bloqueos activos
            $blocks = $this->getActiveBlocks($blockerId, $blockedId, $type);

            if (empty($blocks)) {
                throw new BlockNotFoundException('No se encontraron bloqueos activos para este usuario');
            }

            DB::beginTransaction();

            $unblockedCount = 0;
            $unblockedTypes = [];

            foreach ($blocks as $block) {
                // Marcar bloqueo como levantado
                $this->reportRepository->updateBlock($block['id'], [
                    'status' => self::BLOCK_STATUSES['LIFTED'],
                    'lifted_at' => Carbon::now(),
                    'lift_reason' => $reason
                ]);

                // Revertir consecuencias del bloqueo
                $this->revertBlockConsequences($blockerId, $blockedId, $block['type']);

                $unblockedCount++;
                $unblockedTypes[] = $block['type'];

                Log::info('Bloqueo removido', [
                    'block_id' => $block['id'],
                    'blocker_id' => $blockerId,
                    'blocked_id' => $blockedId,
                    'type' => $block['type']
                ]);
            }

            // Limpiar caches
            $this->clearBlockCache($blockerId, $blockedId);

            // Actualizar estadísticas
            $this->updateUnblockStatistics($blockerId, $blockedId, $unblockedTypes);

            DB::commit();

            return [
                'success' => true,
                'unblocked_count' => $unblockedCount,
                'unblocked_types' => $unblockedTypes,
                'complete_unblock' => in_array(self::BLOCK_TYPES['COMPLETE'], $unblockedTypes),
                'unblocked_at' => Carbon::now()->toISOString()
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al desbloquear usuario', [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verifica si un usuario está bloqueado por otro
     *
     * @param int $checkerId ID del usuario que verifica
     * @param int $targetId ID del usuario objetivo
     * @param string|null $type Tipo específico de bloqueo
     * @return bool True si está bloqueado
     */
    public function isUserBlocked(int $checkerId, int $targetId, ?string $type = null): bool
    {
        $cacheKey = "user_blocked_{$checkerId}_{$targetId}_" . ($type ?? 'any');
        
        return Cache::remember($cacheKey, 300, function () use ($checkerId, $targetId, $type) {
            return $this->reportRepository->isUserBlocked($checkerId, $targetId, $type);
        });
    }

    /**
     * Verifica si existe bloqueo mutuo entre usuarios
     *
     * @param int $userId1 ID del primer usuario
     * @param int $userId2 ID del segundo usuario
     * @return array Información sobre bloqueos mutuos
     */
    public function checkMutualBlock(int $userId1, int $userId2): array
    {
        $cacheKey = "mutual_block_{$userId1}_{$userId2}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId1, $userId2) {
            $block1to2 = $this->reportRepository->getActiveBlock($userId1, $userId2);
            $block2to1 = $this->reportRepository->getActiveBlock($userId2, $userId1);
            
            return [
                'has_mutual_block' => !is_null($block1to2) && !is_null($block2to1),
                'block_1_to_2' => $block1to2,
                'block_2_to_1' => $block2to1,
                'effective_block' => $this->determineEffectiveBlock($block1to2, $block2to1)
            ];
        });
    }

    /**
     * Obtiene la lista de usuarios bloqueados por un usuario
     *
     * @param int $userId ID del usuario
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getBlockedUsers(
        int $userId,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $filters['blocker_id'] = $userId;
        $filters['status'] = self::BLOCK_STATUSES['ACTIVE'];
        
        return $this->reportRepository->getBlocks($filters, $perPage);
    }

    /**
     * Obtiene la lista de usuarios que han bloqueado a un usuario
     *
     * @param int $userId ID del usuario
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getBlockingUsers(
        int $userId,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $filters['blocked_id'] = $userId;
        $filters['status'] = self::BLOCK_STATUSES['ACTIVE'];
        
        return $this->reportRepository->getBlocks($filters, $perPage);
    }

    /**
     * Procesa bloqueos temporales expirados
     *
     * @return array Resultado del procesamiento
     */
    public function processExpiredBlocks(): array
    {
        $expiredBlocks = $this->reportRepository->getExpiredBlocks();
        $processedCount = 0;
        
        foreach ($expiredBlocks as $block) {
            try {
                DB::beginTransaction();
                
                // Marcar como expirado
                $this->reportRepository->updateBlock($block['id'], [
                    'status' => self::BLOCK_STATUSES['EXPIRED'],
                    'expired_at' => Carbon::now()
                ]);
                
                // Revertir consecuencias si es necesario
                $this->revertBlockConsequences(
                    $block['blocker_id'],
                    $block['blocked_id'],
                    $block['type']
                );
                
                // Limpiar cache
                $this->clearBlockCache($block['blocker_id'], $block['blocked_id']);
                
                $processedCount++;
                DB::commit();
                
                Log::info('Bloqueo temporal expirado procesado', [
                    'block_id' => $block['id']
                ]);
                
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error procesando bloqueo expirado', [
                    'block_id' => $block['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'processed_count' => $processedCount,
            'total_expired' => count($expiredBlocks),
            'processed_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Obtiene estadísticas de bloqueos para un usuario
     *
     * @param int $userId ID del usuario
     * @return array Estadísticas de bloqueos
     */
    public function getUserBlockStatistics(int $userId): array
    {
        $cacheKey = "user_block_stats_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $stats = $this->reportRepository->getUserBlockStatistics($userId);
            
            return [
                'user_id' => $userId,
                'total_blocks_made' => $stats['blocks_made'] ?? 0,
                'total_blocks_received' => $stats['blocks_received'] ?? 0,
                'active_blocks_made' => $stats['active_blocks_made'] ?? 0,
                'active_blocks_received' => $stats['active_blocks_received'] ?? 0,
                'blocks_by_type' => $stats['blocks_by_type'] ?? [],
                'blocks_by_reason' => $stats['blocks_by_reason'] ?? [],
                'mutual_blocks' => $stats['mutual_blocks'] ?? 0,
                'temporary_blocks' => $stats['temporary_blocks'] ?? 0,
                'block_frequency' => $this->calculateBlockFrequency($stats),
                'risk_score' => $this->calculateBlockRiskScore($stats),
                'last_block_date' => $stats['last_block_date'] ?? null,
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Crea un bloqueo masivo de usuarios
     *
     * @param int $blockerId ID del usuario que bloquea
     * @param array $userIds IDs de usuarios a bloquear
     * @param string $type Tipo de bloqueo
     * @param string $reason Razón del bloqueo masivo
     * @return array Resultado del bloqueo masivo
     */
    public function massBlock(
        int $blockerId,
        array $userIds,
        string $type = self::BLOCK_TYPES['COMPLETE'],
        string $reason = self::BLOCK_REASONS['SPAM']
    ): array {
        $results = [
            'successful_blocks' => 0,
            'failed_blocks' => 0,
            'already_blocked' => 0,
            'errors' => []
        ];

        foreach ($userIds as $blockedId) {
            try {
                if ($this->isUserBlocked($blockerId, $blockedId, $type)) {
                    $results['already_blocked']++;
                    continue;
                }

                $this->blockUser($blockerId, $blockedId, $type, $reason);
                $results['successful_blocks']++;

            } catch (Exception $e) {
                $results['failed_blocks']++;
                $results['errors'][] = [
                    'user_id' => $blockedId,
                    'error' => $e->getMessage()
                ];

                Log::error('Error en bloqueo masivo', [
                    'blocker_id' => $blockerId,
                    'blocked_id' => $blockedId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bloqueo masivo completado', [
            'blocker_id' => $blockerId,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Obtiene recomendaciones de bloqueo basadas en patrones
     *
     * @param int $userId ID del usuario
     * @return array Recomendaciones de bloqueo
     */
    public function getBlockRecommendations(int $userId): array
    {
        $cacheKey = "block_recommendations_{$userId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            // Usuarios con reportes múltiples
            $reportedUsers = $this->reportRepository->getFrequentlyReportedUsers($userId);
            
            // Usuarios con patrones sospechosos
            $suspiciousUsers = $this->identifySuspiciousUsers($userId);
            
            // Usuarios que han bloqueado usuarios similares
            $similarBlockers = $this->findSimilarBlockers($userId);
            
            return [
                'user_id' => $userId,
                'recommended_blocks' => $this->generateBlockRecommendations(
                    $reportedUsers,
                    $suspiciousUsers,
                    $similarBlockers
                ),
                'confidence_scores' => $this->calculateRecommendationConfidence($reportedUsers, $suspiciousUsers),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Validaciones para una solicitud de bloqueo
     */
    private function validateBlockRequest(int $blockerId, int $blockedId, string $type, ?string $reason): void
    {
        // Verificar que los usuarios existan
        if (!$this->userRepository->existsById($blockerId) || !$this->userRepository->existsById($blockedId)) {
            throw new BlockValidationException('Uno o ambos usuarios no existen');
        }

        // Verificar que no sea un auto-bloqueo
        if ($blockerId === $blockedId) {
            throw new SelfBlockException('No puedes bloquearte a ti mismo');
        }

        // Validar tipo de bloqueo
        if (!in_array($type, self::BLOCK_TYPES)) {
            throw new BlockValidationException("Tipo de bloqueo inválido: {$type}");
        }

        // Validar razón si se proporciona
        if ($reason && !in_array($reason, self::BLOCK_REASONS)) {
            throw new BlockValidationException("Razón de bloqueo inválida: {$reason}");
        }
    }

    /**
     * Aplica las consecuencias de un bloqueo
     */
    private function applyBlockConsequences(int $blockerId, int $blockedId, string $type): array
    {
        $consequences = [];

        switch ($type) {
            case self::BLOCK_TYPES['COMPLETE']:
                $consequences = $this->applyCompleteBlockConsequences($blockerId, $blockedId);
                break;
                
            case self::BLOCK_TYPES['MESSAGING']:
                $consequences = $this->applyMessagingBlockConsequences($blockerId, $blockedId);
                break;
                
            case self::BLOCK_TYPES['MATCHING']:
                $consequences = $this->applyMatchingBlockConsequences($blockerId, $blockedId);
                break;
                
            default:
                $consequences = ['type' => $type, 'applied' => true];
        }

        return $consequences;
    }

    /**
     * Aplica consecuencias de bloqueo completo
     */
    private function applyCompleteBlockConsequences(int $blockerId, int $blockedId): array
    {
        $consequences = [];
        
        // Eliminar matches existentes
        $removedMatches = $this->matchRepository->removeUserMatches($blockerId, $blockedId);
        $consequences['removed_matches'] = $removedMatches;
        
        // Ocultar conversaciones
        $hiddenConversations = $this->messageRepository->hideConversations($blockerId, $blockedId);
        $consequences['hidden_conversations'] = $hiddenConversations;
        
        // Eliminar de listas de favoritos
        $consequences['removed_from_favorites'] = true;
        
        // Evitar aparición en búsquedas
        $consequences['search_visibility'] = false;
        
        return $consequences;
    }

    /**
     * Obtiene bloqueos activos entre usuarios
     */
    private function getActiveBlocks(int $blockerId, int $blockedId, ?string $type = null): array
    {
        $filters = [
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
            'status' => self::BLOCK_STATUSES['ACTIVE']
        ];
        
        if ($type) {
            $filters['type'] = $type;
        }
        
        return $this->reportRepository->getBlocksList($filters);
    }

    /**
     * Determina si debería crearse un bloqueo mutuo automático
     */
    private function shouldCreateMutualBlock(int $blockerId, int $blockedId): bool
    {
        // Verificar si el usuario bloqueado ha reportado al bloqueador recientemente
        $recentReports = $this->reportRepository->getRecentReports(
            $blockedId,
            $blockerId,
            Carbon::now()->subDays(7)
        );
        
        return count($recentReports) > 0;
    }

    /**
     * Crea un bloqueo mutuo automático
     */
    private function createMutualBlock(int $blockerId, int $blockedId, string $type, string $reason): array
    {
        return $this->blockUser($blockerId, $blockedId, $type, $reason, null, [
            'automatic' => true,
            'mutual_block' => true
        ]);
    }

    /**
     * Actualiza estadísticas de bloqueos
     */
    private function updateBlockStatistics(int $blockerId, int $blockedId, string $type): void
    {
        // Estadísticas del usuario que bloquea
        $blockerStats = Cache::get("user_block_stats_{$blockerId}", []);
        $blockerStats['total_blocks_made'] = ($blockerStats['total_blocks_made'] ?? 0) + 1;
        $blockerStats['last_block_date'] = Carbon::now()->toISOString();
        Cache::put("user_block_stats_{$blockerId}", $blockerStats, 86400);
        
        // Estadísticas del usuario bloqueado
        $blockedStats = Cache::get("user_block_stats_{$blockedId}", []);
        $blockedStats['total_blocks_received'] = ($blockedStats['total_blocks_received'] ?? 0) + 1;
        Cache::put("user_block_stats_{$blockedId}", $blockedStats, 86400);
    }

    /**
     * Limpia caches relacionados con bloqueos
     */
    private function clearBlockCache(int $blockerId, int $blockedId): void
    {
        $patterns = [
            "user_blocked_{$blockerId}_{$blockedId}_*",
            "user_blocked_{$blockedId}_{$blockerId}_*",
            "mutual_block_{$blockerId}_{$blockedId}",
            "mutual_block_{$blockedId}_{$blockerId}",
            "user_block_stats_{$blockerId}",
            "user_block_stats_{$blockedId}",
            "block_recommendations_{$blockerId}",
            "block_recommendations_{$blockedId}"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Genera referencia única para el bloqueo
     */
    private function generateBlockReference(int $blockId): string
    {
        return 'BLK-' . str_pad((string)$blockId, 6, '0', STR_PAD_LEFT) . '-' . 
               strtoupper(substr(md5((string)$blockId . time()), 0, 4));
    }

    /**
     * Revierte las consecuencias de un bloqueo
     */
    private function revertBlockConsequences(int $blockerId, int $blockedId, string $type): void
    {
        switch ($type) {
            case self::BLOCK_TYPES['COMPLETE']:
                $this->revertCompleteBlockConsequences($blockerId, $blockedId);
                break;
                
            case self::BLOCK_TYPES['MESSAGING']:
                $this->messageRepository->restoreConversations($blockerId, $blockedId);
                break;
                
            case self::BLOCK_TYPES['MATCHING']:
                // Las consecuencias de matching generalmente no se revierten automáticamente
                break;
        }
    }

    /**
     * Calcula la frecuencia de bloqueos de un usuario
     */
    private function calculateBlockFrequency(array $stats): float
    {
        $totalBlocks = ($stats['blocks_made'] ?? 0) + ($stats['blocks_received'] ?? 0);
        $daysActive = 30; // Asumimos 30 días de actividad para el cálculo
        
        return $totalBlocks > 0 ? round($totalBlocks / $daysActive, 2) : 0.0;
    }

    /**
     * Calcula el score de riesgo basado en bloqueos
     */
    private function calculateBlockRiskScore(array $stats): int
    {
        $blocksReceived = $stats['blocks_received'] ?? 0;
        $blocksMade = $stats['blocks_made'] ?? 0;
        
        // Score base por bloqueos recibidos (más peso)
        $score = $blocksReceived * 15;
        
        // Penalty por bloqueos hechos (menos peso)
        $score += $blocksMade * 5;
        
        // Bonus por bloqueos mutuos (indica problemas serios)
        $score += ($stats['mutual_blocks'] ?? 0) * 25;
        
        return min($score, 100); // Máximo 100
    }
}