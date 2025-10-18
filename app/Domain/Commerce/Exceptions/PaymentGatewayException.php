<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PaymentGatewayException
 * 
 * Excepción lanzada cuando ocurre un error en la pasarela de pago.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class PaymentGatewayException extends Exception
{
    public function __construct(string $message = 'Payment gateway error', int $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
