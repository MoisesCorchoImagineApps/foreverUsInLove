<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Throwable;

/**
 * UserCleanupService - Servicio para limpieza de usuarios inactivos
 * 
 * Maneja la desactivación de usuarios inactivos, eliminación de usuarios no verificados
 * y limpieza de sesiones expiradas siguiendo Clean Architecture.
 */
class UserCleanupService
{
    /**
     * Desactiva un usuario inactivo y limpia sus datos asociados
     *
     * @param User $user Usuario a desactivar
     * @return void
     * @throws Throwable
     */
    public function deactivateInactiveUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            try {
                // Actualizar estado del usuario
                $user->update([
                    'status' => 'inactive',
                    'last_active_at' => now(),
                ]);

                // Limpiar tokens de autenticación
                $this->cleanupUserTokens($user);

                // Limpiar datos de sesión
                $this->cleanupUserSessions($user);

                // Limpiar datos temporales
                $this->cleanupTemporaryData($user);

                Log::info("User deactivated successfully", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deactivated_at' => now(),
                ]);

            } catch (Throwable $e) {
                Log::error("Failed to deactivate user", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Elimina un usuario no verificado y todos sus datos
     *
     * @param User $user Usuario no verificado a eliminar
     * @return void
     * @throws Throwable
     */
    public function deleteUnverifiedUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            try {
                // Verificar que el usuario no tenga datos importantes
                if ($this->hasImportantData($user)) {
                    throw new \Exception("User has important data and cannot be deleted");
                }

                // Eliminar archivos del usuario
                $this->deleteUserFiles($user);

                // Eliminar datos relacionados
                $this->deleteRelatedData($user);

                // Eliminar el usuario
                $user->forceDelete();

                Log::info("Unverified user deleted successfully", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deleted_at' => now(),
                ]);

            } catch (Throwable $e) {
                Log::error("Failed to delete unverified user", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Limpia sesiones expiradas del sistema
     *
     * @return int Número de sesiones limpiadas
     */
    public function cleanupExpiredSessions(): int
    {
        try {
            $expiredSessions = DB::table('sessions')
                ->where('last_activity', '<', now()->subMinutes(config('session.lifetime', 120)))
                ->delete();

            Log::info("Expired sessions cleaned up", [
                'sessions_cleaned' => $expiredSessions,
                'cleaned_at' => now(),
            ]);

            return $expiredSessions;

        } catch (Throwable $e) {
            Log::error("Failed to cleanup expired sessions", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cuenta las sesiones expiradas sin eliminarlas
     *
     * @return int Número de sesiones expiradas
     */
    public function countExpiredSessions(): int
    {
        try {
            return DB::table('sessions')
                ->where('last_activity', '<', now()->subMinutes(config('session.lifetime', 120)))
                ->count();

        } catch (Throwable $e) {
            Log::error("Failed to count expired sessions", [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Limpia tokens de autenticación del usuario
     *
     * @param User $user
     * @return void
     */
    private function cleanupUserTokens(User $user): void
    {
        try {
            // Limpiar tokens de Sanctum
            $user->tokens()->delete();

            // Limpiar tokens de Passport si están configurados
            if (config('auth.guards.api.driver') === 'passport') {
                DB::table('oauth_access_tokens')
                    ->where('user_id', $user->id)
                    ->delete();

                DB::table('oauth_refresh_tokens')
                    ->where('user_id', $user->id)
                    ->delete();
            }

        } catch (Throwable $e) {
            Log::warning("Failed to cleanup user tokens", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Limpia sesiones del usuario
     *
     * @param User $user
     * @return void
     */
    private function cleanupUserSessions(User $user): void
    {
        try {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

        } catch (Throwable $e) {
            Log::warning("Failed to cleanup user sessions", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Limpia datos temporales del usuario
     *
     * @param User $user
     * @return void
     */
    private function cleanupTemporaryData(User $user): void
    {
        try {
            // Limpiar códigos de verificación expirados
            $user->update([
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
                'password_reset_code' => null,
                'password_reset_code_expires_at' => null,
                'password_reset_token' => null,
                'password_reset_token_expires_at' => null,
            ]);

            // Limpiar datos de rate limiting
            $user->update([
                'rate_limit_hits' => 0,
                'rate_limit_reset_at' => null,
                'login_attempts' => 0,
                'locked_until' => null,
            ]);

        } catch (Throwable $e) {
            Log::warning("Failed to cleanup temporary data", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verifica si el usuario tiene datos importantes que impidan su eliminación
     *
     * @param User $user
     * @return bool
     */
    private function hasImportantData(User $user): bool
    {
        try {
            // Verificar si tiene suscripciones activas usando consulta directa
            $hasActiveSubscriptions = DB::table('subscriptions')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('current_period_end', '>=', now())
                ->exists();

            if ($hasActiveSubscriptions) {
                return true;
            }

            // Verificar si tiene órdenes recientes usando consulta directa
            $hasRecentOrders = DB::table('orders')
                ->where('user_id', $user->id)
                ->where('created_at', '>', now()->subDays(30))
                ->exists();

            if ($hasRecentOrders) {
                return true;
            }

            // Verificar si tiene matches activos usando consulta directa
            $hasActiveMatches = DB::table('matches')
                ->where(function ($query) use ($user) {
                    $query->where('user1_id', $user->id)
                          ->orWhere('user2_id', $user->id);
                })
                ->where('status', 'active')
                ->exists();

            if ($hasActiveMatches) {
                return true;
            }

            // Verificar si tiene chats activos usando consulta directa
            $hasActiveChats = DB::table('chats')
                ->where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveChats) {
                return true;
            }

            return false;

        } catch (Throwable $e) {
            Log::warning("Failed to check important data", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return true; // Por seguridad, asumir que tiene datos importantes
        }
    }

    /**
     * Elimina archivos del usuario
     *
     * @param User $user
     * @return void
     */
    private function deleteUserFiles(User $user): void
    {
        try {
            // Eliminar imágenes del perfil usando la relación existente
            if ($user->images) {
                foreach ($user->images as $image) {
                    if ($image->file_path && Storage::exists($image->file_path)) {
                        Storage::delete($image->file_path);
                    }
                }
            }

            // Eliminar archivos del perfil usando la relación existente
            if ($user->profile) {
                $profile = $user->profile;
                
                if ($profile->profile_image && Storage::exists($profile->profile_image)) {
                    Storage::delete($profile->profile_image);
                }
                
                if ($profile->cover_image && Storage::exists($profile->cover_image)) {
                    Storage::delete($profile->cover_image);
                }
            }

        } catch (Throwable $e) {
            Log::warning("Failed to delete user files", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Elimina datos relacionados del usuario
     *
     * @param User $user
     * @return void
     */
    private function deleteRelatedData(User $user): void
    {
        try {
            // Eliminar datos relacionados en orden específico para evitar problemas de foreign key
            $tablesToClean = [
                'user_images',
                'user_settings', 
                'personality_tests',
                'notifications',
                'device_tokens',
            ];

            foreach ($tablesToClean as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            // Eliminar perfil si existe
            if ($user->profile) {
                $user->profile->delete();
            }

        } catch (Throwable $e) {
            Log::warning("Failed to delete related data", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
