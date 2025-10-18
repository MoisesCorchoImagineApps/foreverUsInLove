<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PaymentNotFoundException
 * 
 * Excepción lanzada cuando no se encuentra un pago específico.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class PaymentNotFoundException extends Exception
{
    public function __construct(string $message = 'Payment not found', int $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
