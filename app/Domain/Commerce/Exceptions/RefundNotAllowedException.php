<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * RefundNotAllowedException
 * 
 * Excepción lanzada cuando un reembolso no está permitido.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class RefundNotAllowedException extends Exception
{
    public function __construct(string $message = 'Refund not allowed', int $code = 400, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
