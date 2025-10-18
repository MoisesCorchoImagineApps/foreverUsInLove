<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * Video Call Exception
 * 
 * Excepción específica para errores relacionados con llamadas de video
 * en el sistema ForeverUsInLove.
 * 
 * @package App\Domain\Chat\Exceptions
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 */
class VideoCallException extends Exception
{
    /**
     * Constructor
     *
     * @param string $message Mensaje de error
     * @param int $code Código de error
     * @param Exception|null $previous Excepción anterior
     */
    public function __construct(string $message = 'Video call error occurred', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
