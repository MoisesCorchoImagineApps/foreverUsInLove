<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * CoinPackageNotFoundException
 * 
 * Exception thrown when a coin package is not found in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinPackageNotFoundException extends Exception
{
    public function __construct(string $message = "Coin package not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
