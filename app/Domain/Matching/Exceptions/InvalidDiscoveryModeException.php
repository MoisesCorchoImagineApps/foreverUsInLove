<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InvalidDiscoveryModeException
 * 
 * Excepción que se lanza cuando se intenta usar un modo de discovery
 * no válido o no disponible para el usuario actual.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class InvalidDiscoveryModeException extends Exception
{
    /**
     * Constructor de la excepción
     *
     * @param string $message Mensaje de error
     * @param int $code Código de error
     * @param Exception|null $previous Excepción anterior
     */
    public function __construct(string $message = 'Invalid discovery mode', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
