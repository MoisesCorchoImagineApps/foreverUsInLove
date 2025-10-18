<?php

declare(strict_types=1);

namespace App\Domain\Profile\Repositories;

use App\Models\User\Profile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * ProfileRepositoryInterface
 * 
 * Interfaz que define el contrato para operaciones de repositorio de perfiles
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Implementa el patrón Repository para abstraer las operaciones de acceso a datos
 * del modelo Profile, proporcionando una API limpia y consistente para:
 * - Operaciones CRUD de perfiles de usuario
 * - Búsquedas y filtros específicos de perfiles
 * - Gestión de estados y visibility de perfiles
 * - Análisis de completitud y calidad
 * - Operaciones de matching y compatibilidad
 * - Métricas y analytics de perfiles
 * - Gestión de preferencias y configuraciones
 * - Operaciones de moderación y seguridad
 * 
 * Esta interfaz sigue los principios de:
 * - Dependency Inversion (SOLID)
 * - Clean Architecture
 * - Domain-Driven Design
 * - Repository Pattern
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
interface ProfileRepositoryInterface
{
    // ========================================
    // OPERACIONES CRUD BÁSICAS
    // ========================================

    /**
     * Encuentra un perfil por su ID
     *
     * @param int $id ID del perfil
     * @param array $with Relaciones a cargar eager loading
     * @return Profile|null Perfil encontrado o null
     */
    public function findById(int $id, array $with = []): ?Profile;

    /**
     * Encuentra un perfil por ID de usuario
     *
     * @param int $userId ID del usuario
     * @param array $with Relaciones a cargar
     * @return Profile|null Perfil encontrado o null
     */
    public function findByUserId(int $userId, array $with = []): ?Profile;

    /**
     * Encuentra un perfil por UUID
     *
     * @param string $uuid UUID del perfil
     * @param array $with Relaciones a cargar
     * @return Profile|null Perfil encontrado o null
     */
    public function findByUuid(string $uuid, array $with = []): ?Profile;

    /**
     * Crea un nuevo perfil
     *
     * @param array $data Datos del perfil
     * @return Profile Perfil creado
     * @throws \Exception Si hay error en la creación
     */
    public function create(array $data): Profile;

    /**
     * Actualiza un perfil existente
     *
     * @param int $id ID del perfil
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * @throws \Exception Si hay error en la actualización
     */
    public function update(int $id, array $data): bool;

    /**
     * Elimina un perfil (soft delete)
     *
     * @param int $id ID del perfil
     * @return bool True si se eliminó correctamente
     * @throws \Exception Si hay error en la eliminación
     */
    public function delete(int $id): bool;

    /**
     * Elimina permanentemente un perfil (hard delete)
     *
     * @param int $id ID del perfil
     * @return bool True si se eliminó correctamente
     * @throws \Exception Si hay error en la eliminación
     */
    public function forceDelete(int $id): bool;

    /**
     * Restaura un perfil eliminado con soft delete
     *
     * @param int $id ID del perfil
     * @return bool True si se restauró correctamente
     */
    public function restore(int $id): bool;

    // ========================================
    // BÚSQUEDAS Y FILTROS ESPECÍFICOS
    // ========================================

    /**
     * Obtiene perfiles con paginación
     *
     * @param int $perPage Elementos por página
     * @param array $filters Filtros a aplicar
     * @param array $with Relaciones a cargar
     * @return LengthAwarePaginator Perfiles paginados
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator;

    /**
     * Busca perfiles por criterios específicos
     *
     * @param array $criteria Criterios de búsqueda
     * @param array $with Relaciones a cargar
     * @param int|null $limit Límite de resultados
     * @return Collection Colección de perfiles
     */
    public function search(array $criteria, array $with = [], ?int $limit = null): Collection;

    /**
     * Encuentra perfiles por rango de edad
     *
     * @param int $minAge Edad mínima
     * @param int $maxAge Edad máxima
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Perfiles encontrados
     */
    public function findByAgeRange(int $minAge, int $maxAge, array $additionalFilters = []): Collection;

    /**
     * Encuentra perfiles por género
     *
     * @param string $gender Género a buscar
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Perfiles encontrados
     */
    public function findByGender(string $gender, array $additionalFilters = []): Collection;

    /**
     * Encuentra perfiles por ubicación
     *
     * @param string $location Ubicación base
     * @param float $radiusKm Radio en kilómetros (opcional)
     * @param array $coordinates Coordenadas [lat, lon] para búsqueda por radio
     * @return Collection Perfiles encontrados
     */
    public function findByLocation(string $location, ?float $radiusKm = null, array $coordinates = []): Collection;

    /**
     * Encuentra perfiles por estado específico
     *
     * @param string $status Estado del perfil
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Perfiles encontrados
     */
    public function findByStatus(string $status, array $additionalFilters = []): Collection;

    /**
     * Encuentra perfiles activos para matching
     *
     * @param array $filters Filtros de búsqueda
     * @param int|null $excludeUserId Usuario a excluir
     * @return Collection Perfiles activos
     */
    public function findActiveForMatching(array $filters = [], ?int $excludeUserId = null): Collection;

    /**
     * Encuentra perfiles por nivel de completitud
     *
     * @param int $minCompleteness Porcentaje mínimo de completitud
     * @param int $maxCompleteness Porcentaje máximo de completitud
     * @return Collection Perfiles encontrados
     */
    public function findByCompleteness(int $minCompleteness, int $maxCompleteness = 100): Collection;

    // ========================================
    // GESTIÓN DE ESTADO Y VISIBILITY
    // ========================================

    /**
     * Cambia el estado de un perfil
     *
     * @param int $profileId ID del perfil
     * @param string $newStatus Nuevo estado
     * @param string|null $reason Razón del cambio (opcional)
     * @return bool True si se actualizó correctamente
     */
    public function changeStatus(int $profileId, string $newStatus, ?string $reason = null): bool;

    /**
     * Pausa temporalmente un perfil
     *
     * @param int $profileId ID del perfil
     * @param Carbon|null $resumeAt Fecha de reanudación automática
     * @param string|null $reason Razón de la pausa
     * @return bool True si se pausó correctamente
     */
    public function pauseProfile(int $profileId, ?Carbon $resumeAt = null, ?string $reason = null): bool;

    /**
     * Reanuda un perfil pausado
     *
     * @param int $profileId ID del perfil
     * @return bool True si se reanudó correctamente
     */
    public function resumeProfile(int $profileId): bool;

    /**
     * Actualiza la visibilidad del perfil en matching
     *
     * @param int $profileId ID del perfil
     * @param bool $visible True para hacer visible, false para ocultar
     * @return bool True si se actualizó correctamente
     */
    public function updateMatchingVisibility(int $profileId, bool $visible): bool;

    /**
     * Obtiene perfiles que requieren reanudación automática
     *
     * @param Carbon|null $beforeDate Fecha límite para reanudación
     * @return Collection Perfiles a reanudar
     */
    public function getProfilesForAutoResume(?Carbon $beforeDate = null): Collection;

    // ========================================
    // ANÁLISIS DE COMPLETITUD Y CALIDAD
    // ========================================

    /**
     * Calcula y actualiza el porcentaje de completitud del perfil
     *
     * @param int $profileId ID del perfil
     * @return int Porcentaje de completitud calculado
     */
    public function calculateAndUpdateCompleteness(int $profileId): int;

    /**
     * Actualiza el puntaje de calidad del perfil
     *
     * @param int $profileId ID del perfil
     * @param float $score Nuevo puntaje de calidad
     * @return bool True si se actualizó correctamente
     */
    public function updateQualityScore(int $profileId, float $score): bool;

    /**
     * Obtiene perfiles incompletos que requieren acción
     *
     * @param int $maxCompleteness Porcentaje máximo de completitud
     * @param Carbon|null $createdBefore Creados antes de esta fecha
     * @return Collection Perfiles incompletos
     */
    public function getIncompleteProfiles(int $maxCompleteness = 80, ?Carbon $createdBefore = null): Collection;

    /**
     * Obtiene perfiles de alta calidad
     *
     * @param float $minScore Puntaje mínimo de calidad
     * @param array $additionalCriteria Criterios adicionales
     * @return Collection Perfiles de alta calidad
     */
    public function getHighQualityProfiles(float $minScore = 8.0, array $additionalCriteria = []): Collection;

    // ========================================
    // OPERACIONES DE MATCHING Y COMPATIBILIDAD
    // ========================================

    /**
     * Encuentra perfiles compatibles para un usuario específico
     *
     * @param int $userId ID del usuario base
     * @param array $preferences Preferencias de matching
     * @param int $limit Límite de resultados
     * @return Collection Perfiles compatibles
     */
    public function findCompatibleProfiles(int $userId, array $preferences = [], int $limit = 50): Collection;

    /**
     * Obtiene perfiles similares basados en características
     *
     * @param int $profileId ID del perfil base
     * @param array $similarityFactors Factores de similitud a considerar
     * @param int $limit Límite de resultados
     * @return Collection Perfiles similares
     */
    public function findSimilarProfiles(int $profileId, array $similarityFactors = [], int $limit = 20): Collection;

    /**
     * Actualiza métricas de matching del perfil
     *
     * @param int $profileId ID del perfil
     * @param array $metrics Métricas a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function updateMatchingMetrics(int $profileId, array $metrics): bool;

    /**
     * Obtiene perfiles por puntaje de compatibilidad
     *
     * @param int $userId ID del usuario base
     * @param float $minCompatibility Compatibilidad mínima
     * @param int $limit Límite de resultados
     * @return Collection Perfiles compatibles ordenados por puntaje
     */
    public function getProfilesByCompatibilityScore(int $userId, float $minCompatibility = 0.7, int $limit = 100): Collection;

    // ========================================
    // ANÁLISIS Y MÉTRICAS
    // ========================================

    /**
     * Cuenta el total de perfiles por criterios
     *
     * @param array $filters Filtros opcionales
     * @return int Total de perfiles
     */
    public function countTotal(array $filters = []): int;

    /**
     * Cuenta perfiles activos
     *
     * @param Carbon|null $activeSince Activos desde esta fecha
     * @return int Número de perfiles activos
     */
    public function countActiveProfiles(?Carbon $activeSince = null): int;

    /**
     * Obtiene estadísticas demográficas de perfiles
     *
     * @param array $filters Filtros opcionales
     * @return array Estadísticas por edad, género, ubicación
     */
    public function getDemographicStats(array $filters = []): array;

    /**
     * Obtiene métricas de completitud de perfiles
     *
     * @param array $groupBy Campos para agrupar estadísticas
     * @return array Métricas de completitud
     */
    public function getCompletenessMetrics(array $groupBy = []): array;

    /**
     * Obtiene estadísticas de calidad de perfiles
     *
     * @param Carbon|null $since Desde esta fecha
     * @return array Estadísticas de calidad
     */
    public function getQualityStats(?Carbon $since = null): array;

    /**
     * Obtiene tendencias de crecimiento de perfiles
     *
     * @param Carbon $startDate Fecha de inicio
     * @param Carbon $endDate Fecha de fin
     * @param string $interval Intervalo (day, week, month)
     * @return array Datos de tendencias
     */
    public function getGrowthTrends(Carbon $startDate, Carbon $endDate, string $interval = 'day'): array;

    // ========================================
    // OPERACIONES DE MODERACIÓN
    // ========================================

    /**
     * Obtiene perfiles pendientes de moderación
     *
     * @param array $filters Filtros de moderación
     * @param int $limit Límite de resultados
     * @return Collection Perfiles pendientes
     */
    public function getPendingModerationProfiles(array $filters = [], int $limit = 50): Collection;

    /**
     * Marca un perfil como revisado por moderación
     *
     * @param int $profileId ID del perfil
     * @param string $decision Decisión de moderación (approved/rejected)
     * @param string|null $reason Razón de la decisión
     * @param int|null $moderatorId ID del moderador
     * @return bool True si se actualizó correctamente
     */
    public function markAsModerated(int $profileId, string $decision, ?string $reason = null, ?int $moderatorId = null): bool;

    /**
     * Obtiene perfiles reportados por usuarios
     *
     * @param array $filters Filtros de reportes
     * @param int $limit Límite de resultados
     * @return Collection Perfiles reportados
     */
    public function getReportedProfiles(array $filters = [], int $limit = 50): Collection;

    /**
     * Suspende temporalmente un perfil
     *
     * @param int $profileId ID del perfil
     * @param Carbon $suspendUntil Fecha hasta la cual suspender
     * @param string $reason Razón de la suspensión
     * @param int|null $moderatorId ID del moderador
     * @return bool True si se suspendió correctamente
     */
    public function suspendProfile(int $profileId, Carbon $suspendUntil, string $reason, ?int $moderatorId = null): bool;

    // ========================================
    // GESTIÓN DE ACTIVIDAD Y ENGAGEMENT
    // ========================================

    /**
     * Actualiza la última actividad del perfil
     *
     * @param int $profileId ID del perfil
     * @param Carbon|null $lastActivity Fecha de actividad (opcional, usa now())
     * @return bool True si se actualizó correctamente
     */
    public function updateLastActivity(int $profileId, ?Carbon $lastActivity = null): bool;

    /**
     * Incrementa contador de visualizaciones del perfil
     *
     * @param int $profileId ID del perfil
     * @param int|null $viewerUserId ID del usuario que visualiza (opcional)
     * @return bool True si se incrementó correctamente
     */
    public function incrementProfileViews(int $profileId, ?int $viewerUserId = null): bool;

    /**
     * Registra interacción con el perfil
     *
     * @param int $profileId ID del perfil
     * @param string $interactionType Tipo de interacción (like, pass, match, etc.)
     * @param int|null $interactorUserId ID del usuario que interactúa
     * @param array $metadata Metadatos adicionales
     * @return bool True si se registró correctamente
     */
    public function recordInteraction(int $profileId, string $interactionType, ?int $interactorUserId = null, array $metadata = []): bool;

    /**
     * Obtiene métricas de engagement del perfil
     *
     * @param int $profileId ID del perfil
     * @param Carbon|null $since Desde esta fecha
     * @return array Métricas de engagement
     */
    public function getEngagementMetrics(int $profileId, ?Carbon $since = null): array;

    // ========================================
    // OPERACIONES DE PREFERENCIAS
    // ========================================

    /**
     * Actualiza las preferencias de matching del perfil
     *
     * @param int $profileId ID del perfil
     * @param array $preferences Preferencias de matching
     * @return bool True si se actualizó correctamente
     */
    public function updateMatchingPreferences(int $profileId, array $preferences): bool;

    /**
     * Actualiza las preferencias de privacidad del perfil
     *
     * @param int $profileId ID del perfil
     * @param array $privacySettings Configuración de privacidad
     * @return bool True si se actualizó correctamente
     */
    public function updatePrivacySettings(int $profileId, array $privacySettings): bool;

    /**
     * Actualiza las preferencias de notificación del perfil
     *
     * @param int $profileId ID del perfil
     * @param array $notificationSettings Configuración de notificaciones
     * @return bool True si se actualizó correctamente
     */
    public function updateNotificationSettings(int $profileId, array $notificationSettings): bool;

    // ========================================
    // OPERACIONES DE BATCH Y MANTENIMIENTO
    // ========================================

    /**
     * Actualiza múltiples perfiles con los mismos datos
     *
     * @param array $profileIds IDs de perfiles
     * @param array $data Datos a actualizar
     * @return int Número de perfiles actualizados
     */
    public function batchUpdate(array $profileIds, array $data): int;

    /**
     * Actualiza perfiles donde se cumple una condición
     *
     * @param array $where Condiciones WHERE
     * @param array $data Datos a actualizar
     * @return int Número de perfiles actualizados
     */
    public function updateWhere(array $where, array $data): int;

    /**
     * Obtiene perfiles inactivos para cleanup
     *
     * @param Carbon $inactiveSince Fecha desde la cual se considera inactivo
     * @param array $additionalCriteria Criterios adicionales
     * @return Collection Perfiles inactivos
     */
    public function getInactiveProfiles(Carbon $inactiveSince, array $additionalCriteria = []): Collection;

    /**
     * Limpia datos obsoletos de perfiles
     *
     * @param Carbon $olderThan Datos más antiguos que esta fecha
     * @param array $dataTypes Tipos de datos a limpiar
     * @return int Número de registros limpiados
     */
    public function cleanupObsoleteData(Carbon $olderThan, array $dataTypes = []): int;

    /**
     * Anonimiza datos de un perfil para cumplimiento GDPR
     *
     * @param int $profileId ID del perfil
     * @param array $fieldsToAnonymize Campos a anonimizar
     * @return bool True si se anonimizó correctamente
     */
    public function anonymizeProfileData(int $profileId, array $fieldsToAnonymize = []): bool;

    // ========================================
    // OPERACIONES DE CACHÉ Y PERFORMANCE
    // ========================================

    /**
     * Obtiene un perfil desde caché o base de datos
     *
     * @param int $profileId ID del perfil
     * @param int $cacheMinutes Minutos de caché
     * @return Profile|null Perfil encontrado
     */
    public function findWithCache(int $profileId, int $cacheMinutes = 60): ?Profile;

    /**
     * Invalida el caché de un perfil
     *
     * @param int $profileId ID del perfil
     * @return bool True si se invalidó correctamente
     */
    public function invalidateCache(int $profileId): bool;

    /**
     * Precarga datos relacionados para una colección de perfiles
     *
     * @param Collection $profiles Colección de perfiles
     * @param array $relations Relaciones a precargar
     * @return Collection Perfiles con relaciones precargadas
     */
    public function eagerLoadRelations(Collection $profiles, array $relations): Collection;

    // ========================================
    // BÚSQUEDAS AVANZADAS Y ESPECÍFICAS
    // ========================================

    /**
     * Busca perfiles por texto libre en bio y otros campos
     *
     * @param string $searchTerm Término de búsqueda
     * @param array $searchFields Campos donde buscar
     * @param array $filters Filtros adicionales
     * @param int $limit Límite de resultados
     * @return Collection Perfiles encontrados
     */
    public function searchByText(string $searchTerm, array $searchFields = [], array $filters = [], int $limit = 50): Collection;

    /**
     * Encuentra perfiles por intereses específicos
     *
     * @param array $interests Lista de intereses
     * @param int $minMatches Mínimo número de coincidencias
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Perfiles encontrados
     */
    public function findByInterests(array $interests, int $minMatches = 1, array $additionalFilters = []): Collection;

    /**
     * Obtiene perfiles trending o populares
     *
     * @param string $timeframe Marco temporal (day, week, month)
     * @param int $limit Límite de resultados
     * @param array $criteria Criterios de popularidad
     * @return Collection Perfiles trending
     */
    public function getTrendingProfiles(string $timeframe = 'week', int $limit = 20, array $criteria = []): Collection;

    /**
     * Encuentra perfiles recién activos
     *
     * @param Carbon $activeSince Activos desde esta fecha
     * @param array $filters Filtros adicionales
     * @param int $limit Límite de resultados
     * @return Collection Perfiles recién activos
     */
    public function getRecentlyActiveProfiles(Carbon $activeSince, array $filters = [], int $limit = 50): Collection;

    /**
     * Obtiene perfiles por algoritmo de recomendación
     *
     * @param int $userId ID del usuario base
     * @param string $algorithm Algoritmo a usar
     * @param array $parameters Parámetros del algoritmo
     * @param int $limit Límite de resultados
     * @return Collection Perfiles recomendados
     */
    public function getRecommendedProfiles(int $userId, string $algorithm = 'default', array $parameters = [], int $limit = 20): Collection;

    // ========================================
    // OPERACIONES DE EXPORTACIÓN/IMPORTACIÓN
    // ========================================

    /**
     * Exporta datos de perfil para cumplimiento GDPR
     *
     * @param int $profileId ID del perfil
     * @param array $includeRelations Relaciones a incluir en la exportación
     * @return array Datos del perfil en formato exportable
     */
    public function exportProfileData(int $profileId, array $includeRelations = []): array;

    /**
     * Importa perfiles desde un array de datos
     *
     * @param array $profilesData Array de datos de perfiles
     * @param array $options Opciones de importación
     * @return array Resultado de la importación (exitosos, fallidos)
     */
    public function importProfiles(array $profilesData, array $options = []): array;

    // ========================================
    // OPERACIONES ESPECÍFICAS PARA FILTROS
    // ========================================

    /**
     * Encuentra presets de filtros guardados por un usuario
     *
     * @param int $userId ID del usuario
     * @return Collection Presets de filtros del usuario
     */
    public function findUserFilterPresets(int $userId): Collection;

    /**
     * Obtiene pool de candidatos para filtrado
     *
     * @param int $userId ID del usuario que busca
     * @param array $genderPreferences Preferencias de género
     * @param array $basicConstraints Restricciones básicas
     * @return Collection Pool de candidatos disponibles
     */
    public function findCandidatePool(int $userId, array $genderPreferences = [], array $basicConstraints = []): Collection;
}