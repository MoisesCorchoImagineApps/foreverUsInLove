<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * Unauthorized Call Access Exception
 * 
 * Excepción lanzada cuando un usuario intenta acceder a una llamada
 * de video sin los permisos necesarios.
 * 
 * @package App\Domain\Chat\Exceptions
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 */
class UnauthorizedCallAccessException extends Exception
{
    /**
     * Constructor
     *
     * @param string $message Mensaje de error
     * @param int $code Código de error
     * @param Exception|null $previous Excepción anterior
     */
    public function __construct(string $message = 'Unauthorized access to call', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
