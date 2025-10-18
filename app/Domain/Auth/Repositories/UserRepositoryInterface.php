<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * UserRepositoryInterface
 * 
 * Interfaz que define el contrato para operaciones de repositorio de usuarios
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Implementa el patrón Repository para abstraer las operaciones de acceso a datos
 * del modelo User, proporcionando una API limpia y consistente para:
 * - Operaciones CRUD básicas de usuarios
 * - Búsquedas y filtros complejos
 * - Gestión de autenticación y autorización
 * - Operaciones de verificación y validación
 * - Análisis y métricas de usuarios
 * - Operaciones de cleanup y mantenimiento
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
interface UserRepositoryInterface
{
    // ========================================
    // OPERACIONES CRUD BÁSICAS
    // ========================================

    /**
     * Encuentra un usuario por su ID
     *
     * @param int $id ID del usuario
     * @param array $with Relaciones a cargar eager loading
     * @return User|null Usuario encontrado o null
     */
    public function findById(int $id, array $with = []): ?User;

    /**
     * Encuentra un usuario por su email
     *
     * @param string $email Email del usuario
     * @param array $with Relaciones a cargar
     * @return User|null Usuario encontrado o null
     */
    public function findByEmail(string $email, array $with = []): ?User;

    /**
     * Encuentra un usuario por su username
     *
     * @param string $username Username del usuario
     * @param array $with Relaciones a cargar
     * @return User|null Usuario encontrado o null
     */
    public function findByUsername(string $username, array $with = []): ?User;

    /**
     * Encuentra un usuario por su número de teléfono
     *
     * @param string $phone Número de teléfono
     * @param array $with Relaciones a cargar
     * @return User|null Usuario encontrado o null
     */
    public function findByPhone(string $phone, array $with = []): ?User;

    /**
     * Crea un nuevo usuario
     *
     * @param array $data Datos del usuario
     * @return User Usuario creado
     * @throws \Exception Si hay error en la creación
     */
    public function create(array $data): User;

    /**
     * Actualiza un usuario existente
     *
     * @param int $id ID del usuario
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * @throws \Exception Si hay error en la actualización
     */
    public function update(int $id, array $data): bool;

    /**
     * Elimina un usuario (soft delete)
     *
     * @param int $id ID del usuario
     * @return bool True si se eliminó correctamente
     * @throws \Exception Si hay error en la eliminación
     */
    public function delete(int $id): bool;

    /**
     * Elimina permanentemente un usuario (hard delete)
     *
     * @param int $id ID del usuario
     * @return bool True si se eliminó correctamente
     * @throws \Exception Si hay error en la eliminación
     */
    public function forceDelete(int $id): bool;

    /**
     * Restaura un usuario eliminado con soft delete
     *
     * @param int $id ID del usuario
     * @return bool True si se restauró correctamente
     */
    public function restore(int $id): bool;

    // ========================================
    // OPERACIONES DE BÚSQUEDA Y FILTRADO
    // ========================================

    /**
     * Obtiene todos los usuarios con paginación
     *
     * @param int $perPage Elementos por página
     * @param array $filters Filtros a aplicar
     * @param array $with Relaciones a cargar
     * @return LengthAwarePaginator Usuarios paginados
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator;

    /**
     * Busca usuarios por criterios específicos
     *
     * @param array $criteria Criterios de búsqueda
     * @param array $with Relaciones a cargar
     * @param int|null $limit Límite de resultados
     * @return Collection Colección de usuarios
     */
    public function search(array $criteria, array $with = [], ?int $limit = null): Collection;

    /**
     * Busca usuarios por edad
     *
     * @param int $minAge Edad mínima
     * @param int $maxAge Edad máxima
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Usuarios encontrados
     */
    public function findByAgeRange(int $minAge, int $maxAge, array $additionalFilters = []): Collection;

    /**
     * Busca usuarios por ubicación
     *
     * @param string $country País
     * @param string|null $city Ciudad (opcional)
     * @param float|null $radius Radio en kilómetros (opcional)
     * @param array $coordinates Coordenadas [lat, lon] para búsqueda por radio
     * @return Collection Usuarios encontrados
     */
    public function findByLocation(
        string $country, 
        ?string $city = null, 
        ?float $radius = null, 
        array $coordinates = []
    ): Collection;

    /**
     * Busca usuarios por género
     *
     * @param string $gender Género a buscar
     * @param array $additionalFilters Filtros adicionales
     * @return Collection Usuarios encontrados
     */
    public function findByGender(string $gender, array $additionalFilters = []): Collection;

    /**
     * Busca usuarios verificados
     *
     * @param bool $emailVerified Email verificado
     * @param bool $phoneVerified Teléfono verificado
     * @param bool $profileVerified Perfil verificado
     * @return Collection Usuarios verificados
     */
    public function findVerifiedUsers(
        bool $emailVerified = true, 
        bool $phoneVerified = false, 
        bool $profileVerified = false
    ): Collection;

    /**
     * Busca usuarios activos en un período
     *
     * @param Carbon $since Fecha desde
     * @param Carbon|null $until Fecha hasta (opcional)
     * @return Collection Usuarios activos
     */
    public function findActiveUsers(Carbon $since, ?Carbon $until = null): Collection;

    // ========================================
    // OPERACIONES DE VERIFICACIÓN
    // ========================================

    /**
     * Verifica si un email está disponible
     *
     * @param string $email Email a verificar
     * @param int|null $excludeUserId ID de usuario a excluir de la búsqueda
     * @return bool True si está disponible
     */
    public function isEmailAvailable(string $email, ?int $excludeUserId = null): bool;

    /**
     * Verifica si un username está disponible
     *
     * @param string $username Username a verificar
     * @param int|null $excludeUserId ID de usuario a excluir de la búsqueda
     * @return bool True si está disponible
     */
    public function isUsernameAvailable(string $username, ?int $excludeUserId = null): bool;

    /**
     * Verifica si un teléfono está disponible
     *
     * @param string $phone Teléfono a verificar
     * @param int|null $excludeUserId ID de usuario a excluir de la búsqueda
     * @return bool True si está disponible
     */
    public function isPhoneAvailable(string $phone, ?int $excludeUserId = null): bool;

    /**
     * Marca el email como verificado
     *
     * @param int $userId ID del usuario
     * @param Carbon|null $verifiedAt Fecha de verificación (opcional, usa now())
     * @return bool True si se actualizó correctamente
     */
    public function markEmailAsVerified(int $userId, ?Carbon $verifiedAt = null): bool;

    /**
     * Marca el teléfono como verificado
     *
     * @param int $userId ID del usuario
     * @param Carbon|null $verifiedAt Fecha de verificación (opcional, usa now())
     * @return bool True si se actualizó correctamente
     */
    public function markPhoneAsVerified(int $userId, ?Carbon $verifiedAt = null): bool;

    /**
     * Actualiza la última actividad del usuario
     *
     * @param int $userId ID del usuario
     * @param Carbon|null $lastActivity Fecha de actividad (opcional, usa now())
     * @return bool True si se actualizó correctamente
     */
    public function updateLastActivity(int $userId, ?Carbon $lastActivity = null): bool;

    /**
     * Actualiza el último login del usuario
     *
     * @param int $userId ID del usuario
     * @param Carbon|null $lastLogin Fecha de login (opcional, usa now())
     * @param string|null $ipAddress IP del login
     * @param string|null $userAgent User agent del login
     * @return bool True si se actualizó correctamente
     */
    public function updateLastLogin(
        int $userId, 
        ?Carbon $lastLogin = null, 
        ?string $ipAddress = null, 
        ?string $userAgent = null
    ): bool;

    // ========================================
    // OPERACIONES DE AUTENTICACIÓN
    // ========================================

    /**
     * Encuentra un usuario por email y verifica su contraseña
     *
     * @param string $email Email del usuario
     * @param string $password Contraseña a verificar
     * @return User|null Usuario si las credenciales son válidas
     */
    public function findByCredentials(string $email, string $password): ?User;

    /**
     * Actualiza la contraseña de un usuario
     *
     * @param int $userId ID del usuario
     * @param string $newPassword Nueva contraseña (será hasheada)
     * @return bool True si se actualizó correctamente
     */
    public function updatePassword(int $userId, string $newPassword): bool;

    /**
     * Incrementa el contador de intentos de login fallidos
     *
     * @param string $email Email del usuario
     * @return int Número total de intentos fallidos
     */
    public function incrementFailedLoginAttempts(string $email): int;

    /**
     * Resetea el contador de intentos de login fallidos
     *
     * @param string $email Email del usuario
     * @return bool True si se reseteó correctamente
     */
    public function resetFailedLoginAttempts(string $email): bool;

    /**
     * Obtiene el número de intentos de login fallidos
     *
     * @param string $email Email del usuario
     * @return int Número de intentos fallidos
     */
    public function getFailedLoginAttempts(string $email): int;

    /**
     * Bloquea temporalmente un usuario
     *
     * @param int $userId ID del usuario
     * @param Carbon $lockedUntil Fecha hasta la cual estará bloqueado
     * @param string $reason Razón del bloqueo
     * @return bool True si se bloqueó correctamente
     */
    public function lockUser(int $userId, Carbon $lockedUntil, string $reason = ''): bool;

    /**
     * Desbloquea un usuario
     *
     * @param int $userId ID del usuario
     * @return bool True si se desbloqueó correctamente
     */
    public function unlockUser(int $userId): bool;

    /**
     * Verifica si un usuario existe por ID
     *
     * @param int $userId ID del usuario
     * @return bool True si existe
     */
    public function existsById(int $userId): bool;

    // ========================================
    // OPERACIONES DE PERFIL Y PREFERENCIAS
    // ========================================

    /**
     * Actualiza las preferencias de matching de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $preferences Preferencias de matching
     * @return bool True si se actualizó correctamente
     */
    public function updateMatchingPreferences(int $userId, array $preferences): bool;

    /**
     * Actualiza las preferencias de privacidad de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $privacySettings Configuración de privacidad
     * @return bool True si se actualizó correctamente
     */
    public function updatePrivacySettings(int $userId, array $privacySettings): bool;

    /**
     * Actualiza las preferencias de notificación de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $notificationSettings Configuración de notificaciones
     * @return bool True si se actualizó correctamente
     */
    public function updateNotificationSettings(int $userId, array $notificationSettings): bool;

    /**
     * Calcula y actualiza el porcentaje de completitud del perfil
     *
     * @param int $userId ID del usuario
     * @return int Porcentaje de completitud (0-100)
     */
    public function calculateProfileCompletion(int $userId): int;

    // ========================================
    // OPERACIONES DE SUSCRIPCIÓN Y TIPO DE CUENTA
    // ========================================

    /**
     * Actualiza el tipo de suscripción del usuario
     *
     * @param int $userId ID del usuario
     * @param string $subscriptionType Tipo de suscripción (free, premium, gold, platinum)
     * @param Carbon|null $expiresAt Fecha de expiración (opcional)
     * @return bool True si se actualizó correctamente
     */
    public function updateSubscriptionType(int $userId, string $subscriptionType, ?Carbon $expiresAt = null): bool;

    /**
     * Obtiene usuarios con suscripciones activas
     *
     * @param string|null $subscriptionType Tipo específico (opcional)
     * @return Collection Usuarios con suscripción activa
     */
    public function getActiveSubscribers(?string $subscriptionType = null): Collection;

    /**
     * Obtiene usuarios con suscripciones próximas a expirar
     *
     * @param int $daysBeforeExpiration Días antes de la expiración
     * @return Collection Usuarios con suscripción próxima a expirar
     */
    public function getExpiringSubscriptions(int $daysBeforeExpiration = 7): Collection;

    // ========================================
    // ANÁLISIS Y MÉTRICAS
    // ========================================

    /**
     * Cuenta el total de usuarios registrados
     *
     * @param array $filters Filtros opcionales
     * @return int Total de usuarios
     */
    public function countTotal(array $filters = []): int;

    /**
     * Cuenta usuarios activos en un período
     *
     * @param Carbon $since Fecha desde
     * @param Carbon|null $until Fecha hasta (opcional)
     * @return int Número de usuarios activos
     */
    public function countActiveUsers(Carbon $since, ?Carbon $until = null): int;

    /**
     * Cuenta nuevos registros en un período
     *
     * @param Carbon $since Fecha desde
     * @param Carbon|null $until Fecha hasta (opcional)
     * @return int Número de nuevos registros
     */
    public function countNewRegistrations(Carbon $since, ?Carbon $until = null): int;

    /**
     * Obtiene estadísticas demográficas
     *
     * @param array $filters Filtros opcionales
     * @return array Estadísticas por edad, género, ubicación
     */
    public function getDemographicStats(array $filters = []): array;

    /**
     * Obtiene métricas de engagement de usuarios
     *
     * @param Carbon $since Fecha desde
     * @param Carbon|null $until Fecha hasta (opcional)
     * @return array Métricas de engagement
     */
    public function getEngagementMetrics(Carbon $since, ?Carbon $until = null): array;

    /**
     * Obtiene estadísticas de verificación
     *
     * @return array Estadísticas de usuarios verificados
     */
    public function getVerificationStats(): array;

    // ========================================
    // OPERACIONES DE BATCH Y MANTENIMIENTO
    // ========================================

    /**
     * Actualiza múltiples usuarios con los mismos datos
     *
     * @param array $userIds IDs de usuarios
     * @param array $data Datos a actualizar
     * @return int Número de usuarios actualizados
     */
    public function batchUpdate(array $userIds, array $data): int;

    /**
     * Elimina usuarios inactivos
     *
     * @param Carbon $inactiveSince Fecha desde la cual se considera inactivo
     * @param bool $dryRun Solo contar, no eliminar
     * @return int Número de usuarios eliminados/contados
     */
    public function deleteInactiveUsers(Carbon $inactiveSince, bool $dryRun = true): int;

    /**
     * Obtiene usuarios para limpieza de datos
     *
     * @param array $criteria Criterios de limpieza
     * @return Collection Usuarios que cumplen los criterios
     */
    public function getUsersForCleanup(array $criteria): Collection;

    /**
     * Anonymiza datos de un usuario para cumplimiento GDPR
     *
     * @param int $userId ID del usuario
     * @param array $fieldsToAnonymize Campos a anonimizar
     * @return bool True si se anonimizó correctamente
     */
    public function anonymizeUserData(int $userId, array $fieldsToAnonymize = []): bool;

    // ========================================
    // OPERACIONES DE IMPORTACIÓN/EXPORTACIÓN
    // ========================================

    /**
     * Exporta datos de usuario para cumplimiento GDPR
     *
     * @param int $userId ID del usuario
     * @param array $includeRelations Relaciones a incluir en la exportación
     * @return array Datos del usuario en formato exportable
     */
    public function exportUserData(int $userId, array $includeRelations = []): array;

    /**
     * Importa usuarios desde un array de datos
     *
     * @param array $usersData Array de datos de usuarios
     * @param array $options Opciones de importación
     * @return array Resultado de la importación (exitosos, fallidos)
     */
    public function importUsers(array $usersData, array $options = []): array;

    // ========================================
    // OPERACIONES DE CACHE Y PERFORMANCE
    // ========================================

    /**
     * Obtiene un usuario desde cache o base de datos
     *
     * @param int $userId ID del usuario
     * @param int $cacheMinutes Minutos de cache
     * @return User|null Usuario encontrado
     */
    public function findWithCache(int $userId, int $cacheMinutes = 60): ?User;

    /**
     * Invalida el cache de un usuario
     *
     * @param int $userId ID del usuario
     * @return bool True si se invalidó correctamente
     */
    public function invalidateCache(int $userId): bool;

    /**
     * Precarga datos relacionados para una colección de usuarios
     *
     * @param Collection $users Colección de usuarios
     * @param array $relations Relaciones a precargar
     * @return Collection Usuarios con relaciones precargadas
     */
    public function eagerLoadRelations(Collection $users, array $relations): Collection;

    // ========================================
    // OPERACIONES DE BÚSQUEDA AVANZADA
    // ========================================

    /**
     * Busca usuarios compatibles para matching
     *
     * @param int $userId ID del usuario base
     * @param array $preferences Preferencias de matching
     * @param int $limit Límite de resultados
     * @return Collection Usuarios compatibles
     */
    public function findCompatibleUsers(int $userId, array $preferences = [], int $limit = 50): Collection;

    /**
     * Busca usuarios por texto libre (nombre, bio, intereses)
     *
     * @param string $searchTerm Término de búsqueda
     * @param array $filters Filtros adicionales
     * @param int $limit Límite de resultados
     * @return Collection Usuarios encontrados
     */
    public function searchByText(string $searchTerm, array $filters = [], int $limit = 50): Collection;

    /**
     * Obtiene sugerencias de username basadas en nombre y email
     *
     * @param string $name Nombre del usuario
     * @param string $email Email del usuario
     * @param int $maxSuggestions Máximo número de sugerencias
     * @return array Array de usernames sugeridos
     */
    public function suggestUsernames(string $name, string $email, int $maxSuggestions = 5): array;
}