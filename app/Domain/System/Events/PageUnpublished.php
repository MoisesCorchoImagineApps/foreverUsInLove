<?php

declare(strict_types=1);

namespace App\Domain\System\Events;

use App\Models\System\Page;
use App\Models\System\Admin;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * PageUnpublished Event
 * 
 * Evento disparado cuando una página del CMS es despublicada exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la despublicación de páginas para:
 * - Invalidar caché de páginas
 * - Notificar a administradores
 * - Registrar métricas de contenido
 * - Actualizar índices de búsqueda
 * - Remover contenido de sitemaps
 * - Cancelar campañas de marketing activas
 * - Registrar auditoría de cambios
 * - Notificar a usuarios suscritos (si aplica)
 * - Ejecutar procesos de limpieza
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class PageUnpublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Página que fue despublicada
     */
    public readonly Page $page;
    
    /**
     * Administrador que despublicó la página
     */
    public readonly ?Admin $unpublishedBy;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Método de despublicación (manual, bulk, automatic)
     */
    public readonly string $unpublishMethod;
    
    /**
     * Razón de la despublicación
     */
    public readonly string $unpublishReason;
    
    /**
     * Contexto adicional de la despublicación
     */
    public readonly array $unpublishContext;
    
    /**
     * Si la página tenía tráfico activo
     */
    public readonly bool $hadActiveTraffic;
    
    /**
     * Estadísticas de la página antes de despublicar
     */
    public readonly array $pageStats;
    
    /**
     * Si se debe mantener como borrador
     */
    public readonly bool $keepAsDraft;

    /**
     * Create a new event instance.
     *
     * @param Page $page Página que fue despublicada
     * @param Admin|null $unpublishedBy Administrador que ejecutó la despublicación
     * @param string $unpublishMethod Método usado (manual, bulk, automatic, api)
     * @param string $unpublishReason Razón específica de la despublicación
     * @param array $unpublishContext Contexto adicional de la despublicación
     * @param bool $hadActiveTraffic Si la página tenía tráfico activo
     * @param array $pageStats Estadísticas de la página antes de despublicar
     * @param bool $keepAsDraft Si se debe mantener como borrador
     */
    public function __construct(
        Page $page,
        ?Admin $unpublishedBy = null,
        string $unpublishMethod = 'manual',
        string $unpublishReason = 'content_update',
        array $unpublishContext = [],
        bool $hadActiveTraffic = false,
        array $pageStats = [],
        bool $keepAsDraft = true
    ) {
        $this->page = $page;
        $this->unpublishedBy = $unpublishedBy;
        $this->timestamp = now();
        $this->unpublishMethod = $unpublishMethod;
        $this->unpublishReason = $unpublishReason;
        $this->unpublishContext = $unpublishContext;
        $this->hadActiveTraffic = $hadActiveTraffic;
        $this->pageStats = $pageStats;
        $this->keepAsDraft = $keepAsDraft;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal administrativo para notificaciones de contenido
            new PrivateChannel('admin.content-updates'),
            
            // Canal para métricas de contenido
            new PrivateChannel('analytics.content-unpublished'),
            
            // Canal para sistemas de caché
            new PrivateChannel('system.cache-invalidation'),
            
            // Canal para notificaciones de SEO/sitemap
            new PrivateChannel('seo.content-updates'),
            
            // Canal para sistemas de limpieza
            new PrivateChannel('system.content-cleanup'),
            
            // Canal específico del tipo de página
            new PrivateChannel("content.{$this->page->page_type}.unpublished")
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'page_id' => $this->page->id,
            'page_uuid' => $this->page->page_id,
            'slug' => $this->page->slug,
            'title' => $this->page->title,
            'page_type' => $this->page->page_type,
            'language' => $this->page->language,
            'template' => $this->page->template,
            'version' => $this->page->version,
            'published_at' => $this->page->published_at?->toISOString(),
            'url' => $this->page->full_url,
            
            // Información del administrador
            'unpublished_by' => $this->unpublishedBy ? [
                'id' => $this->unpublishedBy->id,
                'name' => $this->unpublishedBy->name,
                'email' => $this->unpublishedBy->email
            ] : null,
            
            // Contexto de la despublicación
            'unpublish_method' => $this->unpublishMethod,
            'unpublish_reason' => $this->unpublishReason,
            'had_active_traffic' => $this->hadActiveTraffic,
            'keep_as_draft' => $this->keepAsDraft,
            
            // Estadísticas de la página
            'page_stats' => $this->pageStats,
            
            // Métricas de contenido
            'content_metrics' => [
                'word_count' => str_word_count(strip_tags($this->page->content)),
                'character_count' => strlen($this->page->content),
                'has_images' => !empty($this->page->components['images'] ?? []),
                'has_videos' => !empty($this->page->components['videos'] ?? []),
                'is_featured' => $this->page->is_featured,
                'is_searchable' => $this->page->is_searchable,
                'views_count' => $this->page->views_count,
                'last_viewed_at' => $this->page->last_viewed_at?->toISOString()
            ],
            
            // Datos para SEO
            'seo_data' => [
                'meta_description' => $this->page->meta_description,
                'meta_keywords' => $this->page->meta_keywords,
                'has_excerpt' => !empty($this->page->excerpt)
            ],
            
            // Timestamp del evento
            'event_timestamp' => $this->timestamp->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'page.unpublished';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        // Solo hacer broadcast si las notificaciones de contenido están habilitadas
        return config('broadcasting.enabled', false) && 
               config('app.content_notifications', true);
    }

    /**
     * Get comprehensive event metadata for logging and analytics
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'page_unpublished',
            'event_version' => '1.0',
            'page_id' => $this->page->id,
            'page_uuid' => $this->page->page_id,
            'page_type' => $this->page->page_type,
            'slug' => $this->page->slug,
            'language' => $this->page->language,
            'version' => $this->page->version,
            'unpublished_by_admin_id' => $this->unpublishedBy?->id,
            'unpublish_method' => $this->unpublishMethod,
            'unpublish_reason' => $this->unpublishReason,
            'had_active_traffic' => $this->hadActiveTraffic,
            'keep_as_draft' => $this->keepAsDraft,
            'timestamp' => $this->timestamp->toISOString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'session_id' => session()->getId(),
            
            // Contexto adicional
            'unpublish_context' => $this->unpublishContext,
            'page_stats' => $this->pageStats,
            
            // Métricas de contenido
            'content_stats' => [
                'word_count' => str_word_count(strip_tags($this->page->content)),
                'character_count' => strlen($this->page->content),
                'has_media' => !empty($this->page->components),
                'is_featured' => $this->page->is_featured,
                'views_count' => $this->page->views_count
            ]
        ];
    }

    /**
     * Get the page URL for notifications
     *
     * @return string
     */
    public function getPageUrl(): string
    {
        return $this->page->full_url;
    }

    /**
     * Get a summary of the unpublished page
     *
     * @return array
     */
    public function getPageSummary(): array
    {
        return [
            'title' => $this->page->title,
            'excerpt' => $this->page->excerpt,
            'page_type' => $this->page->page_type,
            'language' => $this->page->language,
            'url' => $this->page->full_url,
            'is_featured' => $this->page->is_featured,
            'views_count' => $this->page->views_count,
            'published_at' => $this->page->published_at?->toISOString(),
            'unpublish_reason' => $this->unpublishReason,
            'keep_as_draft' => $this->keepAsDraft
        ];
    }

    /**
     * Check if this unpublish action requires immediate attention
     *
     * @return bool
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->hadActiveTraffic || 
               $this->page->is_featured || 
               in_array($this->unpublishReason, ['content_violation', 'legal_issue', 'security_concern']);
    }

    /**
     * Get cleanup actions that should be performed
     *
     * @return array
     */
    public function getCleanupActions(): array
    {
        $actions = [
            'invalidate_cache',
            'update_search_index',
            'update_sitemap'
        ];

        if ($this->hadActiveTraffic) {
            $actions[] = 'notify_subscribers';
            $actions[] = 'update_analytics';
        }

        if ($this->page->is_featured) {
            $actions[] = 'update_featured_content';
        }

        if (in_array($this->unpublishReason, ['content_violation', 'legal_issue'])) {
            $actions[] = 'audit_content';
            $actions[] = 'notify_legal_team';
        }

        return $actions;
    }
}
