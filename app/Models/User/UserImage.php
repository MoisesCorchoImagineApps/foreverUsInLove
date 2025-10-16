<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Domain\Profile\Events\PhotoUploaded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * UserImage Model - Fotos de perfil de usuario
 * 
 * Maneja las imágenes del perfil de usuario con soporte para múltiples fotos,
 * ordenamiento, foto principal y moderación de contenido.
 * 
 * @property int $id
 * @property int $user_id
 * @property string $filename Nombre del archivo original
 * @property string $path Ruta del archivo en storage
 * @property string $url URL pública de la imagen
 * @property string|null $thumbnail_path Ruta de la miniatura
 * @property string|null $thumbnail_url URL pública de la miniatura
 * @property bool $is_primary Indica si es la foto principal
 * @property int $order Orden de visualización (0-based)
 * @property string $status Valores: active, pending_moderation, rejected, hidden
 * @property string|null $rejection_reason Razón del rechazo por moderación
 * @property int $width Ancho de la imagen en píxeles
 * @property int $height Alto de la imagen en píxeles
 * @property int $size_bytes Tamaño del archivo en bytes
 * @property string $mime_type Tipo MIME de la imagen
 * @property array|null $metadata Metadata adicional (EXIF, etc.)
 * @property array|null $moderation_results Resultados de moderación automática
 * @property float|null $moderation_score Score de contenido apropiado (0-1)
 * @property Carbon|null $moderated_at
 * @property int|null $moderated_by_user_id ID del moderador
 * @property int $views_count Contador de vistas
 * @property int $likes_count Contador de likes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * 
 * @property-read User $user
 * @property-read bool $is_approved
 * @property-read bool $is_pending
 * @property-read bool $is_rejected
 * @property-read string $size_formatted
 * 
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder pending()
 * @method static \Illuminate\Database\Eloquent\Builder approved()
 * @method static \Illuminate\Database\Eloquent\Builder primary()
 * @method static \Illuminate\Database\Eloquent\Builder ordered()
 */
class UserImage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_images';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'url',
        'thumbnail_path',
        'thumbnail_url',
        'is_primary',
        'order',
        'status',
        'rejection_reason',
        'width',
        'height',
        'size_bytes',
        'mime_type',
        'metadata',
        'moderation_results',
        'moderation_score',
        'views_count',
        'likes_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'order' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'moderation_results' => 'array',
        'moderation_score' => 'float',
        'moderated_at' => 'datetime',
        'moderated_by_user_id' => 'integer',
        'views_count' => 'integer',
        'likes_count' => 'integer',
    ];

    /**
     * The event map for the model.
     */
    protected $dispatchesEvents = [
        // 'created' => PhotoUploaded::class,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (UserImage $image) {
            if ($image->status === null) {
                $image->status = 'pending_moderation';
            }

            $image->views_count = 0;
            $image->likes_count = 0;

            // If this is the first image, make it primary
            if ($image->user->images()->count() === 0) {
                $image->is_primary = true;
                $image->order = 0;
            } else {
                $image->is_primary = $image->is_primary ?? false;
                
                // Set order to the end if not specified
                if ($image->order === null) {
                    $image->order = $image->user->images()->max('order') + 1;
                }
            }
        });

        static::saved(function (UserImage $image) {
            // Update profile completeness when images change
            $image->user->profile?->updateCompletenessPercentage();
        });

        static::deleting(function (UserImage $image) {
            // If deleting primary image, set another as primary
            if ($image->is_primary) {
                $nextImage = $image->user->images()
                    ->where('id', '!=', $image->id)
                    ->where('status', 'active')
                    ->orderBy('order')
                    ->first();

                if ($nextImage) {
                    $nextImage->update(['is_primary' => true]);
                }
            }

            // Delete files from storage
            if (Storage::exists($image->path)) {
                Storage::delete($image->path);
            }
            if ($image->thumbnail_path && Storage::exists($image->thumbnail_path)) {
                Storage::delete($image->thumbnail_path);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to filter active images.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter pending moderation images.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending_moderation');
    }

    /**
     * Scope to filter approved images.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter primary images.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to order images by their order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Check if image is approved.
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if image is pending moderation.
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending_moderation';
    }

    /**
     * Check if image was rejected.
     */
    public function getIsRejectedAttribute(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get formatted file size.
     */
    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Get aspect ratio.
     */
    public function getAspectRatioAttribute(): ?float
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        return round($this->width / $this->height, 2);
    }

    /**
     * Check if image is landscape.
     */
    public function getIsLandscapeAttribute(): bool
    {
        return $this->width > $this->height;
    }

    /**
     * Check if image is portrait.
     */
    public function getIsPortraitAttribute(): bool
    {
        return $this->height > $this->width;
    }

    /**
     * Check if image is square.
     */
    public function getIsSquareAttribute(): bool
    {
        return $this->width === $this->height;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set this image as the primary photo.
     */
    public function setAsPrimary(): void
    {
        // Remove primary flag from other images
        $this->user->images()
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * Reorder images for the user.
     */
    public static function reorderForUser(User $user, array $imageIds): void
    {
        foreach ($imageIds as $order => $imageId) {
            $user->images()
                ->where('id', $imageId)
                ->update(['order' => $order]);
        }
    }

    /**
     * Approve the image after moderation.
     */
    public function approve(?int $moderatorId = null): void
    {
        $this->update([
            'status' => 'active',
            'moderated_at' => now(),
            'moderated_by_user_id' => $moderatorId,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject the image after moderation.
     */
    public function reject(string $reason, ?int $moderatorId = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'moderated_at' => now(),
            'moderated_by_user_id' => $moderatorId,
        ]);
    }

    /**
     * Hide the image (user action or auto-moderation).
     */
    public function hide(?string $reason = null): void
    {
        $this->update([
            'status' => 'hidden',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Unhide the image.
     */
    public function unhide(): void
    {
        $this->update([
            'status' => 'active',
            'rejection_reason' => null,
        ]);
    }

    /**
     * Store moderation results from AI/ML service.
     */
    public function storeModerationResults(array $results, float $score): void
    {
        $this->update([
            'moderation_results' => $results,
            'moderation_score' => $score,
            'moderated_at' => now(),
        ]);

        // Auto-approve if score is high enough
        if ($score >= 0.9) {
            $this->update(['status' => 'active']);
        } elseif ($score < 0.5) {
            $this->update([
                'status' => 'rejected',
                'rejection_reason' => 'Automatic moderation: Content policy violation detected',
            ]);
        }
    }

    /**
     * Increment views counter.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Increment likes counter.
     */
    public function incrementLikes(): void
    {
        $this->increment('likes_count');
    }

    /**
     * Decrement likes counter.
     */
    public function decrementLikes(): void
    {
        $this->decrement('likes_count');
    }

    /**
     * Get full URL for the image.
     */
    public function getFullUrl(): string
    {
        return $this->url ?: Storage::url($this->path);
    }

    /**
     * Get full URL for the thumbnail.
     */
    public function getThumbnailUrl(): string
    {
        return $this->thumbnail_url ?: ($this->thumbnail_path ? Storage::url($this->thumbnail_path) : $this->getFullUrl());
    }

    /**
     * Check if image needs moderation.
     */
    public function needsModeration(): bool
    {
        return $this->status === 'pending_moderation' && $this->moderated_at === null;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get validation rules for image upload.
     */
    public static function uploadRules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:10240', // 10MB
                'dimensions:min_width=400,min_height=400,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * Get validation rules for reordering.
     */
    public static function reorderRules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['required', 'integer', 'exists:user_images,id'],
        ];
    }
}