<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Domain\System\Events\PagePublished;
use App\Domain\System\Events\PageUnpublished;

/**
 * Class Page
 *
 * Eloquent Model for CMS static pages and dynamic content.
 * Manages pages like Terms, Privacy, About, Help, FAQ, Blog posts, Landing pages.
 *
 * @package App\Models\System
 * 
 * @property int $id
 * @property string $page_id Unique page identifier (UUID)
 * @property string $slug URL-friendly slug
 * @property string $title Page title
 * @property string $content Page content (HTML/Markdown)
 * @property string|null $excerpt Short excerpt/summary
 * @property string|null $meta_description SEO meta description
 * @property string|null $meta_keywords SEO meta keywords
 * @property array|null $meta_tags Additional meta tags
 * @property string $status Page status (draft, published, scheduled, archived)
 * @property string $template Template to use for rendering
 * @property string $language Language code (en, es, fr, etc.)
 * @property int $version Version number
 * @property string $page_type Type of page (terms, privacy, about, help, faq, landing, blog, custom)
 * @property bool $is_featured Whether page is featured
 * @property bool $is_searchable Whether page is searchable
 * @property int|null $parent_id Parent page for hierarchical structure
 * @property int $display_order Order for navigation display
 * @property int|null $author_id Admin who created the page
 * @property int|null $updated_by_admin_id Admin who last updated
 * @property \Illuminate\Support\Carbon|null $published_at When page was published
 * @property \Illuminate\Support\Carbon|null $scheduled_at When page is scheduled to publish
 * @property array|null $settings Page-specific settings
 * @property array|null $components Page components/blocks
 * @property int $views_count Number of views
 * @property \Illuminate\Support\Carbon|null $last_viewed_at Last view timestamp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\System\Admin|null $author
 * @property-read \App\Models\System\Admin|null $updatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\System\Page[] $children
 * @property-read \App\Models\System\Page|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\System\Page[] $versions
 * @property-read string $full_url
 * @property-read bool $is_published
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Page published()
 * @method static \Illuminate\Database\Eloquent\Builder|Page draft()
 * @method static \Illuminate\Database\Eloquent\Builder|Page scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder|Page bySlug(string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder|Page byLanguage(string $language)
 * @method static \Illuminate\Database\Eloquent\Builder|Page byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder|Page featured()
 * @method static \Illuminate\Database\Eloquent\Builder|Page searchable()
 * @method static \Illuminate\Database\Eloquent\Builder|Page search(string $query)
 */
class Page extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pages';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'page_id',
        'slug',
        'title',
        'content',
        'excerpt',
        'meta_description',
        'meta_keywords',
        'meta_tags',
        'status',
        'template',
        'language',
        'version',
        'page_type',
        'is_featured',
        'is_searchable',
        'parent_id',
        'display_order',
        'author_id',
        'updated_by_admin_id',
        'published_at',
        'scheduled_at',
        'settings',
        'components',
        'views_count',
        'last_viewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_tags' => 'array',
        'is_featured' => 'boolean',
        'is_searchable' => 'boolean',
        'parent_id' => 'integer',
        'display_order' => 'integer',
        'author_id' => 'integer',
        'updated_by_admin_id' => 'integer',
        'version' => 'integer',
        'settings' => 'array',
        'components' => 'array',
        'views_count' => 'integer',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'full_url',
        'is_published',
    ];

    // ==================== CONSTANTS ====================

    /**
     * Page statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_REVIEW = 'review';

    /**
     * Page types
     */
    const TYPE_TERMS = 'terms';
    const TYPE_PRIVACY = 'privacy';
    const TYPE_ABOUT = 'about';
    const TYPE_HELP = 'help';
    const TYPE_FAQ = 'faq';
    const TYPE_LANDING = 'landing';
    const TYPE_BLOG = 'blog';
    const TYPE_NEWS = 'news';
    const TYPE_CUSTOM = 'custom';

    /**
     * Templates
     */
    const TEMPLATE_DEFAULT = 'default';
    const TEMPLATE_LEGAL = 'legal';
    const TEMPLATE_MARKETING = 'marketing';
    const TEMPLATE_HELP_CENTER = 'help_center';
    const TEMPLATE_BLOG = 'blog';
    const TEMPLATE_LANDING = 'landing';
    const TEMPLATE_MINIMAL = 'minimal';

    /**
     * Cache configuration
     */
    const CACHE_PREFIX = 'page:';
    const CACHE_TTL = 3600; // 1 hour
    const CACHE_TAG = 'pages';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the admin who created this page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    /**
     * Get the admin who last updated this page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    /**
     * Get the parent page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * Get the child pages.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')
                    ->orderBy('display_order');
    }

    /**
     * Get all versions of this page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function versions()
    {
        return $this->hasMany(Page::class, 'page_id', 'page_id')
                    ->where('id', '!=', $this->id)
                    ->orderByDesc('version');
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope a query to only include published pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
                    ->where(function ($q) {
                        $q->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                    });
    }

    /**
     * Scope a query to only include draft pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope a query to only include scheduled pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now());
    }

    /**
     * Scope a query to filter by slug.
     *
     * @param Builder $query
     * @param string $slug
     * @return Builder
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope a query to filter by language.
     *
     * @param Builder $query
     * @param string $language
     * @return Builder
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Scope a query to filter by page type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('page_type', $type);
    }

    /**
     * Scope a query to only include featured pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include searchable pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('is_searchable', true);
    }

    /**
     * Scope a query to search pages by title, content, or excerpt.
     *
     * @param Builder $query
     * @param string $searchQuery
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $searchQuery): Builder
    {
        return $query->where(function ($q) use ($searchQuery) {
            $q->where('title', 'LIKE', "%{$searchQuery}%")
              ->orWhere('content', 'LIKE', "%{$searchQuery}%")
              ->orWhere('excerpt', 'LIKE', "%{$searchQuery}%")
              ->orWhere('meta_description', 'LIKE', "%{$searchQuery}%");
        });
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Get the full URL for this page.
     *
     * @return string
     */
    public function getFullUrlAttribute(): string
    {
        $baseUrl = config('app.url');
        $locale = $this->language !== config('app.locale') ? "/{$this->language}" : '';
        
        return "{$baseUrl}{$locale}/{$this->slug}";
    }

    /**
     * Check if page is published.
     *
     * @return bool
     */
    public function getIsPublishedAttribute(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at <= now());
    }

    /**
     * Set the slug attribute with auto-generation.
     *
     * @param string|null $value
     * @return void
     */
    public function setSlugAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['slug'] = Str::slug($value);
        } elseif ($this->title) {
            $this->attributes['slug'] = Str::slug($this->title);
        }
    }

    /**
     * Set the page_id with UUID generation.
     *
     * @param string|null $value
     * @return void
     */
    public function setPageIdAttribute(?string $value): void
    {
        $this->attributes['page_id'] = $value ?? (string) Str::uuid();
    }

    // ==================== STATIC METHODS ====================

    /**
     * Get a published page by slug with caching.
     *
     * @param string $slug
     * @param string|null $language
     * @return Page|null
     */
    public static function getBySlug(string $slug, ?string $language = null): ?Page
    {
        $language = $language ?? config('app.locale');
        $cacheKey = self::CACHE_PREFIX . "slug:{$slug}:lang:{$language}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($slug, $language) {
            return self::published()
                      ->bySlug($slug)
                      ->byLanguage($language)
                      ->first();
        });
    }

    /**
     * Get all published pages of a specific type.
     *
     * @param string $type
     * @param string|null $language
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByType(string $type, ?string $language = null)
    {
        $language = $language ?? config('app.locale');
        $cacheKey = self::CACHE_PREFIX . "type:{$type}:lang:{$language}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($type, $language) {
            return self::published()
                      ->byType($type)
                      ->byLanguage($language)
                      ->orderBy('display_order')
                      ->get();
        });
    }

    /**
     * Clear page cache.
     *
     * @param string|null $slug
     * @return void
     */
    public static function clearCache(?string $slug = null): void
    {
        if ($slug) {
            $languages = config('app.supported_languages', ['en']);
            foreach ($languages as $language) {
                Cache::tags([self::CACHE_TAG])->forget(self::CACHE_PREFIX . "slug:{$slug}:lang:{$language}");
            }
        } else {
            Cache::tags([self::CACHE_TAG])->flush();
        }
    }

    // ==================== INSTANCE METHODS ====================

    /**
     * Publish the page.
     *
     * @param int|null $adminId
     * @return bool
     */
    public function publish(?int $adminId = null): bool
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = now();
        
        if ($adminId) {
            $this->updated_by_admin_id = $adminId;
        }

        $published = $this->save();

        if ($published) {
            self::clearCache($this->slug);
            event(new PagePublished($this));
        }

        return $published;
    }

    /**
     * Unpublish the page.
     *
     * @param int|null $adminId
     * @return bool
     */
    public function unpublish(?int $adminId = null): bool
    {
        $this->status = self::STATUS_DRAFT;
        
        if ($adminId) {
            $this->updated_by_admin_id = $adminId;
        }

        $unpublished = $this->save();

        if ($unpublished) {
            self::clearCache($this->slug);
            event(new PageUnpublished($this));
        }

        return $unpublished;
    }

    /**
     * Schedule page for publishing.
     *
     * @param \Carbon\Carbon $scheduledAt
     * @param int|null $adminId
     * @return bool
     */
    public function schedule(\Carbon\Carbon $scheduledAt, ?int $adminId = null): bool
    {
        $this->status = self::STATUS_SCHEDULED;
        $this->scheduled_at = $scheduledAt;
        
        if ($adminId) {
            $this->updated_by_admin_id = $adminId;
        }

        return $this->save();
    }

    /**
     * Create a new version of this page.
     *
     * @param array $changes
     * @param int|null $adminId
     * @return Page
     */
    public function createVersion(array $changes = [], ?int $adminId = null): Page
    {
        $newVersion = $this->replicate();
        $newVersion->version = $this->version + 1;
        $newVersion->status = self::STATUS_DRAFT;
        $newVersion->published_at = null;
        
        if ($adminId) {
            $newVersion->updated_by_admin_id = $adminId;
        }

        // Apply changes
        foreach ($changes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $newVersion->$key = $value;
            }
        }

        $newVersion->save();

        return $newVersion;
    }

    /**
     * Increment page views.
     *
     * @return bool
     */
    public function incrementViews(): bool
    {
        $this->views_count++;
        $this->last_viewed_at = now();
        
        return $this->save();
    }

    /**
     * Get breadcrumb trail for this page.
     *
     * @return array
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $page = $this;

        while ($page) {
            array_unshift($breadcrumbs, [
                'title' => $page->title,
                'url' => $page->full_url,
                'is_current' => $page->id === $this->id,
            ]);

            $page = $page->parent;
        }

        return $breadcrumbs;
    }

    /**
     * Get the previous published page.
     *
     * @return Page|null
     */
    public function previous(): ?Page
    {
        return self::published()
                  ->byType($this->page_type)
                  ->byLanguage($this->language)
                  ->where('published_at', '<', $this->published_at)
                  ->orderByDesc('published_at')
                  ->first();
    }

    /**
     * Get the next published page.
     *
     * @return Page|null
     */
    public function next(): ?Page
    {
        return self::published()
                  ->byType($this->page_type)
                  ->byLanguage($this->language)
                  ->where('published_at', '>', $this->published_at)
                  ->orderBy('published_at')
                  ->first();
    }

    /**
     * Check if page has translations.
     *
     * @return bool
     */
    public function hasTranslations(): bool
    {
        return self::where('page_id', $this->page_id)
                  ->where('language', '!=', $this->language)
                  ->exists();
    }

    /**
     * Get all translations of this page.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTranslations()
    {
        return self::where('page_id', $this->page_id)
                  ->where('language', '!=', $this->language)
                  ->where('version', $this->version)
                  ->get();
    }

    /**
     * Get translation for specific language.
     *
     * @param string $language
     * @return Page|null
     */
    public function getTranslation(string $language): ?Page
    {
        return self::where('page_id', $this->page_id)
                  ->where('language', $language)
                  ->where('version', $this->version)
                  ->first();
    }

    /**
     * Convert page to SEO-friendly array.
     *
     * @return array
     */
    public function toSeoArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->meta_description ?? $this->excerpt,
            'keywords' => $this->meta_keywords,
            'url' => $this->full_url,
            'image' => $this->settings['featured_image'] ?? null,
            'type' => 'article',
            'published_time' => $this->published_at?->toIso8601String(),
            'modified_time' => $this->updated_at?->toIso8601String(),
            'author' => $this->author?->name,
            'language' => $this->language,
        ];
    }

    // ==================== MODEL EVENTS ====================

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate page_id and slug
        static::creating(function ($page) {
            if (!$page->page_id) {
                $page->page_id = (string) Str::uuid();
            }

            if (!$page->slug && $page->title) {
                $page->slug = Str::slug($page->title);
            }

            // Set default version
            if (!$page->version) {
                $page->version = 1;
            }

            // Set default language
            if (!$page->language) {
                $page->language = config('app.locale', 'en');
            }

            // Set default display order
            if ($page->display_order === null) {
                $page->display_order = self::where('page_type', $page->page_type)
                                          ->where('language', $page->language)
                                          ->max('display_order') + 1;
            }
        });

        // Clear cache after saving
        static::saved(function ($page) {
            self::clearCache($page->slug);
        });

        // Clear cache after deleting
        static::deleted(function ($page) {
            self::clearCache($page->slug);
        });
    }
}