<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * Call Limit Exceeded Exception
 * 
 * Excepción lanzada cuando se exceden los límites de llamadas de video
 * (número máximo de participantes, duración máxima, etc.).
 * 
 * @package App\Domain\Chat\Exceptions
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 */
class CallLimitExceededException extends Exception
{
    /**
     * Constructor
     *
     * @param string $message Mensaje de error
     * @param int $code Código de error
     * @param Exception|null $previous Excepción anterior
     */
    public function __construct(string $message = 'Call limit exceeded', int $code = 429, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
