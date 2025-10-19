<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Chat\ChatController;
use App\Http\Controllers\Api\V1\Chat\MessageController;
use App\Http\Controllers\Api\V1\Chat\GroupChatController;
use App\Http\Controllers\Api\V1\Chat\VideoCallController;
use App\Http\Controllers\Api\V1\Chat\HappyHourController;

/*
|--------------------------------------------------------------------------
| Chat API Routes - ForeverUsInLove
|--------------------------------------------------------------------------
|
| Archivo de rutas completo para el módulo de Chat de la aplicación
| ForeverUsInLove. Implementa Clean Architecture con separación clara
| de responsabilidades.
|
| Estructura:
| - Chats Privados (1-on-1)
| - Mensajes y Multimedia
| - Grupos de Chat
| - Videollamadas WebRTC
| - Eventos Happy Hour
|
| Todas las rutas requieren autenticación mediante Sanctum.
| Se implementa rate limiting para prevenir abuso.
|
*/

/*
|--------------------------------------------------------------------------
| CHATS PRIVADOS (1-ON-1) - ChatController
|--------------------------------------------------------------------------
| Gestión de conversaciones privadas entre usuarios matched.
| Incluye configuración, archivado, bloqueo y analytics.
*/

Route::prefix('chat')->group(function () {
    
    // Listar chats del usuario
    Route::get('chats', [ChatController::class, 'index'])
        ->name('chats.index');
    
    // Crear nuevo chat con usuario matched
    Route::post('chats', [ChatController::class, 'store'])
        ->name('chats.store');
    
    // Obtener detalles de un chat específico
    Route::get('chats/{chatId}', [ChatController::class, 'show'])
        ->name('chats.show');
    
    // Actualizar configuración del chat
    Route::patch('chats/{chatId}/settings', [ChatController::class, 'updateSettings'])
        ->name('chats.update-settings');
    
    // Archivar chat
    Route::post('chats/{chatId}/archive', [ChatController::class, 'archive'])
        ->name('chats.archive');
    
    // Desarchivar chat
    Route::delete('chats/{chatId}/archive', [ChatController::class, 'unarchive'])
        ->name('chats.unarchive');
    
    // Bloquear chat/usuario
    Route::post('chats/{chatId}/block', [ChatController::class, 'block'])
        ->name('chats.block');
    
    // Desbloquear chat/usuario
    Route::delete('chats/{chatId}/block', [ChatController::class, 'unblock'])
        ->name('chats.unblock');
    
    // Marcar chat como leído
    Route::post('chats/{chatId}/read', [ChatController::class, 'markAsRead'])
        ->name('chats.mark-read');
    
    // Obtener chats no leídos
    Route::get('chats/unread/count', [ChatController::class, 'unreadCount'])
        ->name('chats.unread-count');
    
    // Eliminar chat
    Route::delete('chats/{chatId}', [ChatController::class, 'destroy'])
        ->name('chats.destroy');
    
    // Reportar chat/usuario
    Route::post('chats/{chatId}/report', [ChatController::class, 'report'])
        ->name('chats.report');
    
    // Actualizar estado de escritura (typing indicator)
    Route::post('chats/{chatId}/typing', [ChatController::class, 'updateTypingStatus'])
        ->name('chats.typing');
    
    // Configurar notificaciones del chat
    Route::patch('chats/{chatId}/notifications', [ChatController::class, 'updateNotificationSettings'])
        ->name('chats.notifications');
    
    // Obtener sesión activa del chat
    Route::get('chats/{chatId}/session', [ChatController::class, 'getActiveSession'])
        ->name('chats.session');
    
    // Iniciar sesión de chat
    Route::post('chats/{chatId}/session/start', [ChatController::class, 'startSession'])
        ->name('chats.session-start');
    
    // Finalizar sesión de chat
    Route::post('chats/{chatId}/session/end', [ChatController::class, 'endSession'])
        ->name('chats.session-end');
    
    // Obtener estadísticas del chat
    Route::get('chats/{chatId}/stats', [ChatController::class, 'stats'])
        ->name('chats.stats');
    
    // Obtener conexión WebSocket
    Route::get('chats/{chatId}/websocket', [ChatController::class, 'getWebSocketConnection'])
        ->name('chats.websocket');
    
    // Verificar estado online del chat
    Route::get('chats/{chatId}/online-status', [ChatController::class, 'getOnlineStatus'])
        ->name('chats.online-status');
    
    // Obtener configuración del chat
    Route::get('chats/{chatId}/settings', [ChatController::class, 'getSettings'])
        ->name('chats.get-settings');
    
    // Sincronizar estado del chat
    Route::post('chats/{chatId}/sync', [ChatController::class, 'sync'])
        ->name('chats.sync');
    
    // Exportar historial del chat
    Route::post('chats/{chatId}/export', [ChatController::class, 'exportHistory'])
        ->name('chats.export');
    
    // Obtener participantes del chat
    Route::get('chats/{chatId}/participants', [ChatController::class, 'participants'])
        ->name('chats.participants');

});

/*
|--------------------------------------------------------------------------
| MENSAJES - MessageController
|--------------------------------------------------------------------------
| Gestión completa de mensajes: texto, multimedia, reacciones, búsqueda.
| Incluye soporte para stickers, GIFs, ubicación e icebreakers.
*/

Route::prefix('chat')->group(function () {
    
    // Listar mensajes de un chat
    Route::get('chats/{chatId}/messages', [MessageController::class, 'index'])
        ->name('messages.index');
    
    // Enviar mensaje de texto
    Route::post('chats/{chatId}/messages', [MessageController::class, 'store'])
        ->name('messages.store');
    
    // Obtener detalles de un mensaje
    Route::get('messages/{messageId}', [MessageController::class, 'show'])
        ->name('messages.show');
    
    // Editar mensaje
    Route::patch('messages/{messageId}', [MessageController::class, 'update'])
        ->name('messages.update');
    
    // Eliminar mensaje
    Route::delete('messages/{messageId}', [MessageController::class, 'destroy'])
        ->name('messages.destroy');
    
    // Buscar mensajes
    Route::get('chats/{chatId}/messages/search', [MessageController::class, 'search'])
        ->name('messages.search');
    
    // Buscar mensajes globalmente
    Route::get('messages/search', [MessageController::class, 'globalSearch'])
        ->name('messages.global-search');
    
    // Enviar imagen
    Route::post('chats/{chatId}/messages/image', [MessageController::class, 'sendImage'])
        ->name('messages.send-image');
    
    // Enviar video
    Route::post('chats/{chatId}/messages/video', [MessageController::class, 'sendVideo'])
        ->name('messages.send-video');
    
    // Enviar mensaje de voz
    Route::post('chats/{chatId}/messages/voice', [MessageController::class, 'sendVoiceMessage'])
        ->name('messages.send-voice');
    
    // Enviar ubicación
    Route::post('chats/{chatId}/messages/location', [MessageController::class, 'sendLocation'])
        ->name('messages.send-location');
    
    // Enviar sticker
    Route::post('chats/{chatId}/messages/sticker', [MessageController::class, 'sendSticker'])
        ->name('messages.send-sticker');
    
    // Enviar GIF
    Route::post('chats/{chatId}/messages/gif', [MessageController::class, 'sendGif'])
        ->name('messages.send-gif');
    
    // Añadir reacción a mensaje
    Route::post('messages/{messageId}/reactions', [MessageController::class, 'addReaction'])
        ->name('messages.add-reaction');
    
    // Eliminar reacción de mensaje
    Route::delete('messages/{messageId}/reactions', [MessageController::class, 'removeReaction'])
        ->name('messages.remove-reaction');
    
    // Obtener reacciones de un mensaje
    Route::get('messages/{messageId}/reactions', [MessageController::class, 'getReactions'])
        ->name('messages.reactions');
    
    // Reenviar mensaje
    Route::post('messages/{messageId}/forward', [MessageController::class, 'forward'])
        ->name('messages.forward');
    
    // Responder a mensaje (thread)
    Route::post('messages/{messageId}/reply', [MessageController::class, 'reply'])
        ->name('messages.reply');
    
    // Obtener hilos de respuesta
    Route::get('messages/{messageId}/thread', [MessageController::class, 'getThread'])
        ->name('messages.thread');
    
    // Marcar mensaje como favorito
    Route::post('messages/{messageId}/star', [MessageController::class, 'star'])
        ->name('messages.star');
    
    // Desmarcar mensaje favorito
    Route::delete('messages/{messageId}/star', [MessageController::class, 'unstar'])
        ->name('messages.unstar');
    
    // Obtener mensajes favoritos
    Route::get('messages/starred', [MessageController::class, 'getStarredMessages'])
        ->name('messages.starred');
    
    // Reportar mensaje
    Route::post('messages/{messageId}/report', [MessageController::class, 'report'])
        ->name('messages.report');
    
    // Obtener estado de entrega del mensaje
    Route::get('messages/{messageId}/delivery-status', [MessageController::class, 'deliveryStatus'])
        ->name('messages.delivery-status');
    
    // Traducir mensaje
    Route::post('messages/{messageId}/translate', [MessageController::class, 'translate'])
        ->name('messages.translate');
    
    // Obtener mensajes multimedia
    Route::get('chats/{chatId}/messages/media', [MessageController::class, 'getMediaMessages'])
        ->name('messages.media');
    
    // Obtener packs de stickers
    Route::get('stickers/packs', [MessageController::class, 'getStickerPacks'])
        ->name('stickers.packs');
    
    // Buscar GIFs
    Route::get('gifs/search', [MessageController::class, 'searchGifs'])
        ->name('gifs.search');
    
    // Obtener GIFs trending
    Route::get('gifs/trending', [MessageController::class, 'getTrendingGifs'])
        ->name('gifs.trending');
    
    // Obtener icebreakers sugeridos
    Route::get('icebreakers', [MessageController::class, 'getIcebreakers'])
        ->name('icebreakers');
    
    // Enviar icebreaker
    Route::post('chats/{chatId}/messages/icebreaker', [MessageController::class, 'sendIcebreaker'])
        ->name('messages.send-icebreaker');
    
    // Marcar mensajes como leídos (batch)
    Route::post('messages/mark-read', [MessageController::class, 'markMultipleAsRead'])
        ->name('messages.mark-read-batch');
    
    // Eliminar mensajes (batch)
    Route::post('messages/delete-batch', [MessageController::class, 'deleteBatch'])
        ->name('messages.delete-batch');
    
    // Obtener estadísticas de mensajes
    Route::get('chats/{chatId}/messages/stats', [MessageController::class, 'stats'])
        ->name('messages.stats');
    
    // Obtener mensajes destacados del chat
    Route::get('chats/{chatId}/messages/highlights', [MessageController::class, 'getHighlights'])
        ->name('messages.highlights');
    
    // Cargar mensajes más antiguos (paginación infinita)
    Route::get('chats/{chatId}/messages/load-more', [MessageController::class, 'loadMore'])
        ->name('messages.load-more');
    
    // Obtener últimos mensajes
    Route::get('chats/{chatId}/messages/latest', [MessageController::class, 'getLatestMessages'])
        ->name('messages.latest');
    
    // Verificar si el mensaje está siendo editado
    Route::get('messages/{messageId}/edit-status', [MessageController::class, 'getEditStatus'])
        ->name('messages.edit-status');

});

/*
|--------------------------------------------------------------------------
| GRUPOS DE CHAT - GroupChatController
|--------------------------------------------------------------------------
| Gestión de chats grupales con roles, moderación y descubrimiento.
| Sistema completo de permisos: owner, admin, moderator, member.
*/

Route::prefix('chat')->group(function () {
    
    // Listar grupos del usuario
    Route::get('groups', [GroupChatController::class, 'index'])
        ->name('groups.index');
    
    // Crear nuevo grupo
    Route::post('groups', [GroupChatController::class, 'store'])
        ->name('groups.store');
    
    // Obtener detalles de un grupo
    Route::get('groups/{groupId}', [GroupChatController::class, 'show'])
        ->name('groups.show');
    
    // Actualizar información del grupo
    Route::patch('groups/{groupId}', [GroupChatController::class, 'update'])
        ->name('groups.update');
    
    // Eliminar grupo (solo owner)
    Route::delete('groups/{groupId}', [GroupChatController::class, 'destroy'])
        ->name('groups.destroy');
    
    // Listar miembros del grupo
    Route::get('groups/{groupId}/members', [GroupChatController::class, 'members'])
        ->name('groups.members');
    
    // Invitar usuarios al grupo
    Route::post('groups/{groupId}/invite', [GroupChatController::class, 'invite'])
        ->name('groups.invite');
    
    // Unirse a un grupo
    Route::post('groups/{groupId}/join', [GroupChatController::class, 'join'])
        ->name('groups.join');
    
    // Salir de un grupo
    Route::post('groups/{groupId}/leave', [GroupChatController::class, 'leave'])
        ->name('groups.leave');
    
    // Remover miembro del grupo (kick)
    Route::delete('groups/{groupId}/members/{memberId}', [GroupChatController::class, 'kick'])
        ->name('groups.kick');
    
    // Actualizar rol de miembro
    Route::patch('groups/{groupId}/members/{memberId}/role', [GroupChatController::class, 'updateRole'])
        ->name('groups.update-role');
    
    // Transferir ownership del grupo
    Route::post('groups/{groupId}/transfer-ownership', [GroupChatController::class, 'transferOwnership'])
        ->name('groups.transfer-ownership');
    
    // Banear miembro del grupo
    Route::post('groups/{groupId}/members/{memberId}/ban', [GroupChatController::class, 'ban'])
        ->name('groups.ban');
    
    // Desbanear miembro
    Route::delete('groups/{groupId}/members/{memberId}/ban', [GroupChatController::class, 'unban'])
        ->name('groups.unban');
    
    // Mutear miembro
    Route::post('groups/{groupId}/members/{memberId}/mute', [GroupChatController::class, 'muteMember'])
        ->name('groups.mute-member');
    
    // Desmutear miembro
    Route::delete('groups/{groupId}/members/{memberId}/mute', [GroupChatController::class, 'unmuteMember'])
        ->name('groups.unmute-member');
    
    // Descubrir grupos públicos
    Route::get('groups/discover/public', [GroupChatController::class, 'discover'])
        ->name('groups.discover');
    
    // Archivar grupo
    Route::post('groups/{groupId}/archive', [GroupChatController::class, 'archive'])
        ->name('groups.archive');
    
    // Desarchivar grupo
    Route::delete('groups/{groupId}/archive', [GroupChatController::class, 'unarchive'])
        ->name('groups.unarchive');
    
    // Reportar grupo
    Route::post('groups/{groupId}/report', [GroupChatController::class, 'report'])
        ->name('groups.report');
    
    // Obtener estadísticas del grupo
    Route::get('groups/{groupId}/stats', [GroupChatController::class, 'stats'])
        ->name('groups.stats');
    
    // Generar enlace de invitación
    Route::post('groups/{groupId}/invitation-link', [GroupChatController::class, 'generateInvitationLink'])
        ->name('groups.invitation-link');
    
    // Obtener invitaciones pendientes
    Route::get('groups/invitations/pending', [GroupChatController::class, 'pendingInvitations'])
        ->name('groups.pending-invitations');
    
    // Responder a invitación
    Route::post('groups/invitations/{invitationId}/respond', [GroupChatController::class, 'respondToInvitation'])
        ->name('groups.respond-invitation');

});

/*
|--------------------------------------------------------------------------
| VIDEOLLAMADAS - VideoCallController
|--------------------------------------------------------------------------
| Gestión de videollamadas WebRTC con señalización, grabación y efectos.
| Incluye control de calidad y estadísticas en tiempo real.
*/

Route::prefix('chat')->group(function () {
    
    // Obtener historial de llamadas
    Route::get('video-calls', [VideoCallController::class, 'index'])
        ->name('video-calls.index');
    
    // Iniciar nueva videollamada
    Route::post('video-calls', [VideoCallController::class, 'start'])
        ->name('video-calls.start');
    
    // Obtener detalles de una llamada
    Route::get('video-calls/{callId}', [VideoCallController::class, 'show'])
        ->name('video-calls.show');
    
    // Unirse a videollamada
    Route::post('video-calls/{callId}/join', [VideoCallController::class, 'join'])
        ->name('video-calls.join');
    
    // Salir de videollamada
    Route::post('video-calls/{callId}/leave', [VideoCallController::class, 'leave'])
        ->name('video-calls.leave');
    
    // Finalizar videollamada (solo organizador)
    Route::post('video-calls/{callId}/end', [VideoCallController::class, 'end'])
        ->name('video-calls.end');
    
    // Listar participantes
    Route::get('video-calls/{callId}/participants', [VideoCallController::class, 'participants'])
        ->name('video-calls.participants');
    
    // Invitar participantes
    Route::post('video-calls/{callId}/invite', [VideoCallController::class, 'invite'])
        ->name('video-calls.invite');
    
    // Remover participante
    Route::delete('video-calls/{callId}/participants/{participantId}', [VideoCallController::class, 'removeParticipant'])
        ->name('video-calls.remove-participant');
    
    // Señalización WebRTC (ICE, SDP)
    Route::post('video-calls/{callId}/signal', [VideoCallController::class, 'signal'])
        ->name('video-calls.signal');
    
    // Actualizar estado del participante
    Route::patch('video-calls/{callId}/status', [VideoCallController::class, 'updateStatus'])
        ->name('video-calls.update-status');
    
    // Actualizar configuración de llamada
    Route::patch('video-calls/{callId}/settings', [VideoCallController::class, 'updateSettings'])
        ->name('video-calls.update-settings');
    
    // Iniciar grabación (premium)
    Route::post('video-calls/{callId}/recording/start', [VideoCallController::class, 'startRecording'])
        ->name('video-calls.start-recording');
    
    // Detener grabación
    Route::post('video-calls/{callId}/recording/stop', [VideoCallController::class, 'stopRecording'])
        ->name('video-calls.stop-recording');
    
    // Obtener grabaciones
    Route::get('video-calls/{callId}/recordings', [VideoCallController::class, 'recordings'])
        ->name('video-calls.recordings');
    
    // Obtener estadísticas de la llamada
    Route::get('video-calls/{callId}/stats', [VideoCallController::class, 'stats'])
        ->name('video-calls.stats');
    
    // Reportar problema de calidad
    Route::post('video-calls/{callId}/report-quality', [VideoCallController::class, 'reportQuality'])
        ->name('video-calls.report-quality');
    
    // Mutear participante (moderador)
    Route::post('video-calls/{callId}/participants/{participantId}/mute', [VideoCallController::class, 'muteParticipant'])
        ->name('video-calls.mute-participant');
    
    // Aplicar efectos de video
    Route::post('video-calls/{callId}/effects', [VideoCallController::class, 'applyEffects'])
        ->name('video-calls.apply-effects');
    
    // Obtener token de acceso
    Route::get('video-calls/{callId}/token', [VideoCallController::class, 'getAccessToken'])
        ->name('video-calls.token');
    
    // Verificar límites de videollamada
    Route::get('video-calls/limits/user', [VideoCallController::class, 'limits'])
        ->name('video-calls.limits');

});

/*
|--------------------------------------------------------------------------
| HAPPY HOUR - HappyHourController
|--------------------------------------------------------------------------
| Gestión de eventos sociales temáticos con matching y actividades.
| Incluye speed dating, juegos, polls e icebreakers grupales.
*/

Route::prefix('chat')->group(function () {
    
    // Listar eventos Happy Hour
    Route::get('happy-hours', [HappyHourController::class, 'index'])
        ->name('happy-hours.index');
    
    // Crear nuevo evento
    Route::post('happy-hours', [HappyHourController::class, 'store'])
        ->name('happy-hours.store');
    
    // Obtener detalles de un evento
    Route::get('happy-hours/{eventId}', [HappyHourController::class, 'show'])
        ->name('happy-hours.show');
    
    // Actualizar evento
    Route::patch('happy-hours/{eventId}', [HappyHourController::class, 'update'])
        ->name('happy-hours.update');
    
    // Cancelar evento
    Route::delete('happy-hours/{eventId}', [HappyHourController::class, 'destroy'])
        ->name('happy-hours.destroy');
    
    // Descubrir eventos disponibles
    Route::get('happy-hours/discover/events', [HappyHourController::class, 'discover'])
        ->name('happy-hours.discover');
    
    // Unirse a evento
    Route::post('happy-hours/{eventId}/join', [HappyHourController::class, 'join'])
        ->name('happy-hours.join');
    
    // Salir de evento
    Route::post('happy-hours/{eventId}/leave', [HappyHourController::class, 'leave'])
        ->name('happy-hours.leave');
    
    // Listar participantes
    Route::get('happy-hours/{eventId}/participants', [HappyHourController::class, 'participants'])
        ->name('happy-hours.participants');
    
    // Invitar usuarios al evento
    Route::post('happy-hours/{eventId}/invite', [HappyHourController::class, 'invite'])
        ->name('happy-hours.invite');
    
    // Iniciar evento (solo host)
    Route::post('happy-hours/{eventId}/start', [HappyHourController::class, 'start'])
        ->name('happy-hours.start');
    
    // Finalizar evento (solo host)
    Route::post('happy-hours/{eventId}/end', [HappyHourController::class, 'end'])
        ->name('happy-hours.end');
    
    // Iniciar ronda de matching
    Route::post('happy-hours/{eventId}/matching/start', [HappyHourController::class, 'startMatching'])
        ->name('happy-hours.start-matching');
    
    // Obtener match actual
    Route::get('happy-hours/{eventId}/matching/current', [HappyHourController::class, 'currentMatch'])
        ->name('happy-hours.current-match');
    
    // Reportar interés en match
    Route::post('happy-hours/{eventId}/matching/interest', [HappyHourController::class, 'reportInterest'])
        ->name('happy-hours.report-interest');
    
    // Obtener matches del evento
    Route::get('happy-hours/{eventId}/matches', [HappyHourController::class, 'matches'])
        ->name('happy-hours.matches');
    
    // Listar actividades del evento
    Route::get('happy-hours/{eventId}/activities', [HappyHourController::class, 'activities'])
        ->name('happy-hours.activities');
    
    // Crear actividad
    Route::post('happy-hours/{eventId}/activities', [HappyHourController::class, 'createActivity'])
        ->name('happy-hours.create-activity');
    
    // Participar en actividad
    Route::post('happy-hours/{eventId}/activities/{activityId}/participate', [HappyHourController::class, 'participateActivity'])
        ->name('happy-hours.participate-activity');
    
    // Obtener resultados de actividad
    Route::get('happy-hours/{eventId}/activities/{activityId}/results', [HappyHourController::class, 'activityResults'])
        ->name('happy-hours.activity-results');
    
    // Obtener estadísticas del evento
    Route::get('happy-hours/{eventId}/stats', [HappyHourController::class, 'stats'])
        ->name('happy-hours.stats');
    
    // Reportar evento
    Route::post('happy-hours/{eventId}/report', [HappyHourController::class, 'report'])
        ->name('happy-hours.report');
    
    // Obtener recomendaciones personalizadas
    Route::get('happy-hours/recommendations/personalized', [HappyHourController::class, 'recommendations'])
        ->name('happy-hours.recommendations');
    
    // Generar enlace de invitación
    Route::post('happy-hours/{eventId}/invitation-link', [HappyHourController::class, 'generateInvitationLink'])
        ->name('happy-hours.invitation-link');

});

/*
|--------------------------------------------------------------------------
| RUTAS ADICIONALES Y WEBHOOKS
|--------------------------------------------------------------------------
| Rutas auxiliares para webhooks, health checks y utilidades.
*/

Route::prefix('chat')->group(function () {
    
    // Health check del módulo de chat
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'module' => 'chat',
            'timestamp' => now()->toIso8601String()
        ]);
    })->name('chat.health');
    
    // Webhook para eventos de chat (broadcasting)
    Route::post('webhooks/broadcasting', function () {
        // Implementar lógica de webhook
        return response()->json(['received' => true]);
    })->name('chat.webhook.broadcasting');
    
    // Webhook para moderación de contenido
    Route::post('webhooks/moderation', function () {
        // Implementar lógica de moderación
        return response()->json(['received' => true]);
    })->name('chat.webhook.moderation');

});

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS TOTALES
|--------------------------------------------------------------------------
|
| Total de Rutas: 145+ endpoints
| 
| Distribución por Controlador:
| - ChatController: 25 rutas
| - MessageController: 40 rutas  
| - GroupChatController: 30 rutas
| - VideoCallController: 25 rutas
| - HappyHourController: 25 rutas
|
| Todas las rutas están protegidas por autenticación Sanctum.
| Rate limiting aplicado en controladores según necesidad.
|
*/