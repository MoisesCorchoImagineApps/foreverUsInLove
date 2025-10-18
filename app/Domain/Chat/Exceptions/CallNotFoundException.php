<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * Call Not Found Exception
 * 
 * Excepción lanzada cuando no se encuentra una llamada de video específica.
 * 
 * @package App\Domain\Chat\Exceptions
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 */
class CallNotFoundException extends Exception
{
    /**
     * Constructor
     *
     * @param string $message Mensaje de error
     * @param int $code Código de error
     * @param Exception|null $previous Excepción anterior
     */
    public function __construct(string $message = 'Call not found', int $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
