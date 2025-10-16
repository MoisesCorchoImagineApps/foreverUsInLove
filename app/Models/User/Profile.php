<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Domain\Profile\Events\ProfileCreated;
use App\Domain\Profile\Events\ProfilePaused;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Profile Model - Perfil de usuario de ForeverUsInLove
 * 
 * Maneja toda la información del perfil del usuario para matching y visualización.
 * Incluye datos demográficos, preferencias, biografía y métricas de completitud.
 * 
 * @property int $id
 * @property int $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $display_name Nombre para mostrar públicamente
 * @property string|null $bio
 * @property int|null $age
 * @property Carbon|null $date_of_birth
 * @property string|null $gender Valores: male, female, non_binary, other, prefer_not_to_say
 * @property string|null $gender_identity
 * @property string|null $sexual_orientation Valores: straight, gay, lesbian, bisexual, pansexual, asexual, other
 * @property string|null $relationship_status Valores: single, divorced, widowed, separated
 * @property string|null $looking_for Valores: relationship, friendship, casual, marriage
 * @property string|null $location
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property int|null $height_cm Altura en centímetros
 * @property string|null $body_type Valores: slim, athletic, average, curvy, heavyset
 * @property string|null $ethnicity
 * @property string|null $religion
 * @property string|null $education Valores: high_school, some_college, bachelors, masters, phd, trade_school
 * @property string|null $occupation
 * @property string|null $company
 * @property string|null $school
 * @property int|null $income_range
 * @property bool $has_children
 * @property bool $wants_children
 * @property string|null $smoking Valores: never, occasionally, regularly, trying_to_quit
 * @property string|null $drinking Valores: never, socially, regularly, prefer_not_to_say
 * @property array|null $languages Array de idiomas hablados
 * @property array|null $interests Array de intereses y hobbies
 * @property array|null $hobbies
 * @property array|null $music_preferences
 * @property array|null $movie_preferences
 * @property array|null $book_preferences
 * @property string|null $personality_type MBTI o similar
 * @property array|null $values Array de valores importantes
 * @property string|null $zodiac_sign
 * @property string|null $instagram_handle
 * @property string|null $spotify_connected
 * @property string|null $tagline Frase corta destacada
 * @property string $status Valores: active, paused, incomplete, under_review
 * @property int $completeness_percentage 0-100
 * @property int $profile_views_count
 * @property int $profile_likes_count
 * @property Carbon|null $last_updated_at
 * @property Carbon|null $paused_at
 * @property string|null $pause_reason
 * @property bool $is_verified Badge de verificación
 * @property bool $show_age
 * @property bool $show_distance
 * @property int $visibility_radius_km Radio de visibilidad en km
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * 
 * @property-read User $user
 * @property-read string|null $full_name
 * @property-read bool $is_complete
 * @property-read bool $is_paused
 * 
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder paused()
 * @method static \Illuminate\Database\Eloquent\Builder complete()
 * @method static \Illuminate\Database\Eloquent\Builder verified()
 * @method static \Illuminate\Database\Eloquent\Builder inLocation(float $lat, float $lng, int $radiusKm)
 * @method static \Illuminate\Database\Eloquent\Builder byAge(int $minAge, int $maxAge)
 * @method static \Illuminate\Database\Eloquent\Builder byGender(string $gender)
 * @method static \Illuminate\Database\Eloquent\Builder lookingFor(string $lookingFor)
 */
class Profile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'profiles';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'display_name',
        'bio',
        'age',
        'date_of_birth',
        'gender',
        'gender_identity',
        'sexual_orientation',
        'relationship_status',
        'looking_for',
        'location',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'height_cm',
        'body_type',
        'ethnicity',
        'religion',
        'education',
        'occupation',
        'company',
        'school',
        'income_range',
        'has_children',
        'wants_children',
        'smoking',
        'drinking',
        'languages',
        'interests',
        'hobbies',
        'music_preferences',
        'movie_preferences',
        'book_preferences',
        'personality_type',
        'values',
        'zodiac_sign',
        'instagram_handle',
        'spotify_connected',
        'tagline',
        'status',
        'is_verified',
        'show_age',
        'show_distance',
        'visibility_radius_km',
        'completeness_percentage',
        'profile_views_count',
        'profile_likes_count',
        'last_updated_at',
        'paused_at',
        'pause_reason'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'age' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'height_cm' => 'integer',
        'income_range' => 'integer',
        'has_children' => 'boolean',
        'wants_children' => 'boolean',
        'languages' => 'array',
        'interests' => 'array',
        'hobbies' => 'array',
        'music_preferences' => 'array',
        'movie_preferences' => 'array',
        'book_preferences' => 'array',
        'values' => 'array',
        'completeness_percentage' => 'integer',
        'profile_views_count' => 'integer',
        'profile_likes_count' => 'integer',
        'last_updated_at' => 'datetime',
        'paused_at' => 'datetime',
        'is_verified' => 'boolean',
        'show_age' => 'boolean',
        'show_distance' => 'boolean',
        'visibility_radius_km' => 'integer',
    ];

    /**
     * The event map for the model.
     */
    protected $dispatchesEvents = [
        'created' => ProfileCreated::class,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Profile $profile) {
            $profile->status = $profile->status ?? 'incomplete';
            $profile->completeness_percentage = 0;
            $profile->profile_views_count = 0;
            $profile->profile_likes_count = 0;
            $profile->visibility_radius_km = $profile->visibility_radius_km ?? 50;
            $profile->show_age = $profile->show_age ?? true;
            $profile->show_distance = $profile->show_distance ?? true;
        });

        static::saved(function (Profile $profile) {
            $profile->updateCompletenessPercentage();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the profile.
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
     * Scope to filter active profiles.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter paused profiles.
     */
    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope to filter complete profiles.
     */
    public function scopeComplete($query)
    {
        return $query->where('completeness_percentage', '>=', 80);
    }

    /**
     * Scope to filter verified profiles.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to filter profiles by location radius.
     */
    public function scopeInLocation($query, float $lat, float $lng, int $radiusKm)
    {
        return $query->whereRaw(
            "ST_Distance_Sphere(
                point(longitude, latitude),
                point(?, ?)
            ) <= ?",
            [$lng, $lat, $radiusKm * 1000] // Convert km to meters
        );
    }

    /**
     * Scope to filter profiles by age range.
     */
    public function scopeByAge($query, int $minAge, int $maxAge)
    {
        return $query->whereBetween('age', [$minAge, $maxAge]);
    }

    /**
     * Scope to filter profiles by gender.
     */
    public function scopeByGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Scope to filter profiles by what they're looking for.
     */
    public function scopeLookingFor($query, string $lookingFor)
    {
        return $query->where('looking_for', $lookingFor);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): ?string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->display_name ?? $this->first_name ?? $this->user->username;
    }

    /**
     * Check if profile is complete.
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->completeness_percentage >= 80;
    }

    /**
     * Check if profile is paused.
     */
    public function getIsPausedAttribute(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Get height in feet and inches.
     */
    public function getHeightImperialAttribute(): ?string
    {
        if (!$this->height_cm) {
            return null;
        }

        $inches = $this->height_cm / 2.54;
        $feet = floor($inches / 12);
        $remainingInches = round($inches % 12);

        return "{$feet}'{$remainingInches}\"";
    }

    /**
     * Set date of birth and automatically calculate age.
     */
    public function setDateOfBirthAttribute($value): void
    {
        $this->attributes['date_of_birth'] = $value;

        if ($value) {
            $this->attributes['age'] = Carbon::parse($value)->age;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate and update the profile completeness percentage.
     */
    public function updateCompletenessPercentage(): void
    {
        $requiredFields = [
            'first_name' => 5,
            'bio' => 10,
            'date_of_birth' => 5,
            'gender' => 5,
            'location' => 10,
            'height_cm' => 5,
            'education' => 5,
            'occupation' => 5,
            'relationship_status' => 5,
            'looking_for' => 5,
            'interests' => 10,
            'languages' => 5,
        ];

        $optionalFields = [
            'body_type' => 3,
            'ethnicity' => 3,
            'religion' => 3,
            'smoking' => 2,
            'drinking' => 2,
            'has_children' => 2,
            'wants_children' => 2,
            'hobbies' => 5,
            'values' => 5,
            'tagline' => 3,
        ];

        $score = 0;
        $maxScore = array_sum($requiredFields) + array_sum($optionalFields);

        // Check required fields
        foreach ($requiredFields as $field => $points) {
            $value = $this->$field;

            if ($value !== null && $value !== '' && (!is_array($value) || count($value) > 0)) {
                $score += $points;
            }
        }

        // Check optional fields
        foreach ($optionalFields as $field => $points) {
            $value = $this->$field;

            if ($value !== null && $value !== '' && (!is_array($value) || count($value) > 0)) {
                $score += $points;
            }
        }

        // Check for profile photos
        $imageCount = $this->user->images()->count();
        if ($imageCount >= 1) $score += 5;
        if ($imageCount >= 3) $score += 5;
        if ($imageCount >= 5) $score += 5;
        $maxScore += 15;

        $percentage = round(($score / $maxScore) * 100);

        $this->updateQuietly([
            'completeness_percentage' => $percentage,
            'last_updated_at' => now(),
        ]);

        // Update status based on completeness
        if ($percentage >= 80 && $this->status === 'incomplete') {
            $this->updateQuietly(['status' => 'active']);
        }
    }

    /**
     * Pause the profile.
     */
    public function pause(?string $reason = null): void
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);

        event(new ProfilePaused($this->user, $this, $reason));
    }

    /**
     * Resume a paused profile.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'paused_at' => null,
            'pause_reason' => null,
        ]);
    }

    /**
     * Increment profile views counter.
     */
    public function incrementViews(): void
    {
        $this->increment('profile_views_count');
    }

    /**
     * Increment profile likes counter.
     */
    public function incrementLikes(): void
    {
        $this->increment('profile_likes_count');
    }

    /**
     * Calculate distance to another profile.
     */
    public function distanceTo(Profile $otherProfile): ?float
    {
        if (!$this->latitude || !$this->longitude || !$otherProfile->latitude || !$otherProfile->longitude) {
            return null;
        }

        // Haversine formula
        $earthRadius = 6371; // km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($otherProfile->latitude);
        $lonTo = deg2rad($otherProfile->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * $earthRadius, 2);
    }

    /**
     * Check if profile is visible to another user based on preferences.
     */
    public function isVisibleTo(Profile $otherProfile): bool
    {
        // Check if paused or inactive
        if ($this->status !== 'active') {
            return false;
        }

        // Check distance
        if ($this->latitude && $this->longitude) {
            $distance = $this->distanceTo($otherProfile);
            if ($distance && $distance > $this->visibility_radius_km) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate compatibility score with another profile (basic version).
     */
    public function compatibilityWith(Profile $otherProfile): int
    {
        $score = 0;
        $maxScore = 0;

        // Interests match (20 points)
        if ($this->interests && $otherProfile->interests) {
            $commonInterests = count(array_intersect($this->interests, $otherProfile->interests));
            $score += min($commonInterests * 4, 20);
        }
        $maxScore += 20;

        // Values match (20 points)
        if ($this->values && $otherProfile->values) {
            $commonValues = count(array_intersect($this->values, $otherProfile->values));
            $score += min($commonValues * 5, 20);
        }
        $maxScore += 20;

        // Education level similarity (10 points)
        if ($this->education && $otherProfile->education) {
            if ($this->education === $otherProfile->education) {
                $score += 10;
            }
        }
        $maxScore += 10;

        // Age compatibility (15 points)
        if ($this->age && $otherProfile->age) {
            $ageDiff = abs($this->age - $otherProfile->age);
            if ($ageDiff <= 5) {
                $score += 15 - ($ageDiff * 2);
            }
        }
        $maxScore += 15;

        // Looking for the same thing (15 points)
        if ($this->looking_for && $otherProfile->looking_for) {
            if ($this->looking_for === $otherProfile->looking_for) {
                $score += 15;
            }
        }
        $maxScore += 15;

        // Lifestyle compatibility (20 points)
        if ($this->smoking && $otherProfile->smoking) {
            if ($this->smoking === $otherProfile->smoking) {
                $score += 10;
            }
        }
        if ($this->drinking && $otherProfile->drinking) {
            if ($this->drinking === $otherProfile->drinking) {
                $score += 10;
            }
        }
        $maxScore += 20;

        return $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get validation rules for profile creation/update.
     */
    public static function validationRules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'display_name' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date', 'before:18 years ago'],
            'gender' => ['nullable', 'in:male,female,non_binary,other,prefer_not_to_say'],
            'sexual_orientation' => ['nullable', 'in:straight,gay,lesbian,bisexual,pansexual,asexual,other'],
            'relationship_status' => ['nullable', 'in:single,divorced,widowed,separated'],
            'looking_for' => ['nullable', 'in:relationship,friendship,casual,marriage'],
            'height_cm' => ['nullable', 'integer', 'between:100,250'],
            'body_type' => ['nullable', 'in:slim,athletic,average,curvy,heavyset'],
            'education' => ['nullable', 'in:high_school,some_college,bachelors,masters,phd,trade_school'],
            'smoking' => ['nullable', 'in:never,occasionally,regularly,trying_to_quit'],
            'drinking' => ['nullable', 'in:never,socially,regularly,prefer_not_to_say'],
            'has_children' => ['nullable', 'boolean'],
            'wants_children' => ['nullable', 'boolean'],
            'languages' => ['nullable', 'array'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:50'],
            'visibility_radius_km' => ['nullable', 'integer', 'between:1,500'],
        ];
    }
}
