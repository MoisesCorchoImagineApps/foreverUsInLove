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
 * PagePublished Event
 * 
 * Evento disparado cuando una página del CMS es publicada exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la publicación de páginas para:
 * - Invalidar caché de páginas
 * - Notificar a administradores
 * - Registrar métricas de contenido
 * - Actualizar índices de búsqueda
 * - Enviar notificaciones push sobre nuevo contenido
 * - Activar campañas de marketing
 * - Generar sitemaps actualizados
 * - Registrar auditoría de cambios
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class PagePublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Página que fue publicada
     */
    public readonly Page $page;
    
    /**
     * Administrador que publicó la página
     */
    public readonly ?Admin $publishedBy;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Método de publicación (manual, scheduled, bulk)
     */
    public readonly string $publishMethod;
    
    /**
     * Contexto adicional de la publicación
     */
    public readonly array $publishContext;
    
    /**
     * Si la página fue programada previamente
     */
    public readonly bool $wasScheduled;
    
    /**
     * Versión anterior de la página (si aplica)
     */
    public readonly ?int $previousVersion;
    
    /**
     * Cambios principales realizados
     */
    public readonly ?array $majorChanges;

    /**
     * Create a new event instance.
     *
     * @param Page $page Página que fue publicada
     * @param Admin|null $publishedBy Administrador que ejecutó la publicación
     * @param string $publishMethod Método usado (manual, scheduled, bulk, api)
     * @param array $publishContext Contexto adicional de la publicación
     * @param bool $wasScheduled Si la página estaba programada previamente
     * @param int|null $previousVersion Versión anterior de la página
     * @param array|null $majorChanges Cambios principales realizados
     */
    public function __construct(
        Page $page,
        ?Admin $publishedBy = null,
        string $publishMethod = 'manual',
        array $publishContext = [],
        bool $wasScheduled = false,
        ?int $previousVersion = null,
        ?array $majorChanges = null
    ) {
        $this->page = $page;
        $this->publishedBy = $publishedBy;
        $this->timestamp = now();
        $this->publishMethod = $publishMethod;
        $this->publishContext = $publishContext;
        $this->wasScheduled = $wasScheduled;
        $this->previousVersion = $previousVersion;
        $this->majorChanges = $majorChanges;
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
            new PrivateChannel('analytics.content-published'),
            
            // Canal para sistemas de caché
            new PrivateChannel('system.cache-invalidation'),
            
            // Canal para notificaciones de SEO/sitemap
            new PrivateChannel('seo.content-updates'),
            
            // Canal específico del tipo de página
            new PrivateChannel("content.{$this->page->page_type}.published")
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
            'published_by' => $this->publishedBy ? [
                'id' => $this->publishedBy->id,
                'name' => $this->publishedBy->name,
                'email' => $this->publishedBy->email
            ] : null,
            
            // Contexto de la publicación
            'publish_method' => $this->publishMethod,
            'was_scheduled' => $this->wasScheduled,
            'previous_version' => $this->previousVersion,
            'major_changes' => $this->majorChanges,
            
            // Métricas de contenido
            'content_metrics' => [
                'word_count' => str_word_count(strip_tags($this->page->content)),
                'character_count' => strlen($this->page->content),
                'has_images' => !empty($this->page->components['images'] ?? []),
                'has_videos' => !empty($this->page->components['videos'] ?? []),
                'is_featured' => $this->page->is_featured,
                'is_searchable' => $this->page->is_searchable
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
        return 'page.published';
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
            'event_type' => 'page_published',
            'event_version' => '1.0',
            'page_id' => $this->page->id,
            'page_uuid' => $this->page->page_id,
            'page_type' => $this->page->page_type,
            'slug' => $this->page->slug,
            'language' => $this->page->language,
            'version' => $this->page->version,
            'published_by_admin_id' => $this->publishedBy?->id,
            'publish_method' => $this->publishMethod,
            'was_scheduled' => $this->wasScheduled,
            'previous_version' => $this->previousVersion,
            'timestamp' => $this->timestamp->toISOString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'session_id' => session()->getId(),
            
            // Contexto adicional
            'publish_context' => $this->publishContext,
            'major_changes' => $this->majorChanges,
            
            // Métricas de contenido
            'content_stats' => [
                'word_count' => str_word_count(strip_tags($this->page->content)),
                'character_count' => strlen($this->page->content),
                'has_media' => !empty($this->page->components),
                'is_featured' => $this->page->is_featured
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
     * Get a summary of the published page
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
            'published_at' => $this->page->published_at?->toISOString()
        ];
    }
}
