<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InvalidPaymentDataException
 * 
 * Excepción lanzada cuando los datos de pago son inválidos.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class InvalidPaymentDataException extends Exception
{
    public function __construct(string $message = 'Invalid payment data', int $code = 400, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
