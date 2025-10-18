<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * TransactionType Value Object
 * 
 * Representa el tipo de transacción en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de los tipos de transacción en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * diferentes tipos de transacciones de monedas virtuales, pagos y operaciones comerciales.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta tipos de transacción válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos TransactionType con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y transacciones
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class TransactionType implements JsonSerializable
{
    /**
     * Tipos de transacción soportados
     */
    public const CREDIT = 'credit'; // Crédito - Aumenta el balance
    public const DEBIT = 'debit'; // Débito - Disminuye el balance
    public const REFUND = 'refund'; // Reembolso - Devuelve dinero
    public const TRANSFER = 'transfer'; // Transferencia - Entre usuarios
    public const REWARD = 'reward'; // Recompensa - Bonificación por actividad
    public const BONUS = 'bonus'; // Bono - Bonificación especial
    public const ADJUSTMENT = 'adjustment'; // Ajuste - Corrección manual
    public const EXPIRATION = 'expiration'; // Expiración - Pérdida por tiempo
    public const PENALTY = 'penalty'; // Penalización - Multa por violación
    public const PROMOTION = 'promotion'; // Promoción - Descuento especial

    /**
     * Todos los tipos de transacción válidos
     */
    private const VALID_TYPES = [
        self::CREDIT,
        self::DEBIT,
        self::REFUND,
        self::TRANSFER,
        self::REWARD,
        self::BONUS,
        self::ADJUSTMENT,
        self::EXPIRATION,
        self::PENALTY,
        self::PROMOTION,
    ];

    /**
     * Información de los tipos de transacción (tipo, descripción, afecta balance)
     */
    private const TYPE_INFO = [
        self::CREDIT => [
            'description' => 'Crédito de monedas',
            'affects_balance' => true,
            'balance_change' => 'positive',
            'category' => 'income'
        ],
        self::DEBIT => [
            'description' => 'Débito de monedas',
            'affects_balance' => true,
            'balance_change' => 'negative',
            'category' => 'expense'
        ],
        self::REFUND => [
            'description' => 'Reembolso de monedas',
            'affects_balance' => true,
            'balance_change' => 'positive',
            'category' => 'refund'
        ],
        self::TRANSFER => [
            'description' => 'Transferencia entre usuarios',
            'affects_balance' => true,
            'balance_change' => 'variable',
            'category' => 'transfer'
        ],
        self::REWARD => [
            'description' => 'Recompensa por actividad',
            'affects_balance' => true,
            'balance_change' => 'positive',
            'category' => 'reward'
        ],
        self::BONUS => [
            'description' => 'Bono especial',
            'affects_balance' => true,
            'balance_change' => 'positive',
            'category' => 'bonus'
        ],
        self::ADJUSTMENT => [
            'description' => 'Ajuste manual del balance',
            'affects_balance' => true,
            'balance_change' => 'variable',
            'category' => 'adjustment'
        ],
        self::EXPIRATION => [
            'description' => 'Expiración de monedas',
            'affects_balance' => true,
            'balance_change' => 'negative',
            'category' => 'expiration'
        ],
        self::PENALTY => [
            'description' => 'Penalización por violación',
            'affects_balance' => true,
            'balance_change' => 'negative',
            'category' => 'penalty'
        ],
        self::PROMOTION => [
            'description' => 'Promoción especial',
            'affects_balance' => true,
            'balance_change' => 'positive',
            'category' => 'promotion'
        ],
    ];

    /**
     * El tipo de transacción
     */
    private readonly string $type;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Factory method para crear TransactionType desde string
     *
     * @param string $type Tipo de transacción
     * @return self Nueva instancia de TransactionType
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    public static function fromString(string $type): self
    {
        self::validate($type);
        return new self($type);
    }

    /**
     * Factory methods para cada tipo específico
     */
    public static function credit(): self
    {
        return new self(self::CREDIT);
    }

    public static function debit(): self
    {
        return new self(self::DEBIT);
    }

    public static function refund(): self
    {
        return new self(self::REFUND);
    }

    public static function transfer(): self
    {
        return new self(self::TRANSFER);
    }

    public static function reward(): self
    {
        return new self(self::REWARD);
    }

    public static function bonus(): self
    {
        return new self(self::BONUS);
    }

    public static function adjustment(): self
    {
        return new self(self::ADJUSTMENT);
    }

    public static function expiration(): self
    {
        return new self(self::EXPIRATION);
    }

    public static function penalty(): self
    {
        return new self(self::PENALTY);
    }

    public static function promotion(): self
    {
        return new self(self::PROMOTION);
    }

    /**
     * Obtiene el tipo de transacción
     *
     * @return string Tipo de transacción
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Obtiene el tipo de transacción (alias para getType)
     *
     * @return string Tipo de transacción
     */
    public function toString(): string
    {
        return $this->type;
    }

    /**
     * Obtiene el tipo de transacción (alias para getType)
     *
     * @return string Tipo de transacción
     */
    public function value(): string
    {
        return $this->type;
    }

    /**
     * Obtiene la descripción del tipo de transacción
     *
     * @return string Descripción del tipo
     */
    public function getDescription(): string
    {
        return self::TYPE_INFO[$this->type]['description'];
    }

    /**
     * Verifica si este tipo de transacción afecta el balance
     *
     * @return bool True si afecta el balance
     */
    public function affectsBalance(): bool
    {
        return self::TYPE_INFO[$this->type]['affects_balance'];
    }

    /**
     * Obtiene el cambio en el balance que produce este tipo
     *
     * @return string Cambio en el balance (positive, negative, variable)
     */
    public function getBalanceChange(): string
    {
        return self::TYPE_INFO[$this->type]['balance_change'];
    }

    /**
     * Obtiene la categoría del tipo de transacción
     *
     * @return string Categoría del tipo
     */
    public function getCategory(): string
    {
        return self::TYPE_INFO[$this->type]['category'];
    }

    /**
     * Verifica si este TransactionType es igual a otro
     *
     * @param self $other Otro TransactionType para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->type === $other->type;
    }

    /**
     * Verifica si este TransactionType es un crédito
     *
     * @return bool True si es crédito
     */
    public function isCredit(): bool
    {
        return $this->type === self::CREDIT;
    }

    /**
     * Verifica si este TransactionType es un débito
     *
     * @return bool True si es débito
     */
    public function isDebit(): bool
    {
        return $this->type === self::DEBIT;
    }

    /**
     * Verifica si este TransactionType es un reembolso
     *
     * @return bool True si es reembolso
     */
    public function isRefund(): bool
    {
        return $this->type === self::REFUND;
    }

    /**
     * Verifica si este TransactionType es una transferencia
     *
     * @return bool True si es transferencia
     */
    public function isTransfer(): bool
    {
        return $this->type === self::TRANSFER;
    }

    /**
     * Verifica si este TransactionType es una recompensa
     *
     * @return bool True si es recompensa
     */
    public function isReward(): bool
    {
        return $this->type === self::REWARD;
    }

    /**
     * Verifica si este TransactionType es un bono
     *
     * @return bool True si es bono
     */
    public function isBonus(): bool
    {
        return $this->type === self::BONUS;
    }

    /**
     * Verifica si este TransactionType es un ajuste
     *
     * @return bool True si es ajuste
     */
    public function isAdjustment(): bool
    {
        return $this->type === self::ADJUSTMENT;
    }

    /**
     * Verifica si este TransactionType es una expiración
     *
     * @return bool True si es expiración
     */
    public function isExpiration(): bool
    {
        return $this->type === self::EXPIRATION;
    }

    /**
     * Verifica si este TransactionType es una penalización
     *
     * @return bool True si es penalización
     */
    public function isPenalty(): bool
    {
        return $this->type === self::PENALTY;
    }

    /**
     * Verifica si este TransactionType es una promoción
     *
     * @return bool True si es promoción
     */
    public function isPromotion(): bool
    {
        return $this->type === self::PROMOTION;
    }

    /**
     * Verifica si este TransactionType aumenta el balance
     *
     * @return bool True si aumenta el balance
     */
    public function increasesBalance(): bool
    {
        return $this->getBalanceChange() === 'positive';
    }

    /**
     * Verifica si este TransactionType disminuye el balance
     *
     * @return bool True si disminuye el balance
     */
    public function decreasesBalance(): bool
    {
        return $this->getBalanceChange() === 'negative';
    }

    /**
     * Verifica si este TransactionType puede variar el balance
     *
     * @return bool True si puede variar el balance
     */
    public function variesBalance(): bool
    {
        return $this->getBalanceChange() === 'variable';
    }

    /**
     * Verifica si este TransactionType es de ingreso
     *
     * @return bool True si es de ingreso
     */
    public function isIncome(): bool
    {
        return $this->getCategory() === 'income';
    }

    /**
     * Verifica si este TransactionType es de gasto
     *
     * @return bool True si es de gasto
     */
    public function isExpense(): bool
    {
        return $this->getCategory() === 'expense';
    }

    /**
     * Verifica si este TransactionType es de reembolso
     *
     * @return bool True si es de reembolso
     */
    public function isRefundCategory(): bool
    {
        return $this->getCategory() === 'refund';
    }

    /**
     * Verifica si este TransactionType es de transferencia
     *
     * @return bool True si es de transferencia
     */
    public function isTransferCategory(): bool
    {
        return $this->getCategory() === 'transfer';
    }

    /**
     * Verifica si este TransactionType es de recompensa
     *
     * @return bool True si es de recompensa
     */
    public function isRewardCategory(): bool
    {
        return $this->getCategory() === 'reward';
    }

    /**
     * Verifica si este TransactionType es de bono
     *
     * @return bool True si es de bono
     */
    public function isBonusCategory(): bool
    {
        return $this->getCategory() === 'bonus';
    }

    /**
     * Verifica si este TransactionType es de ajuste
     *
     * @return bool True si es de ajuste
     */
    public function isAdjustmentCategory(): bool
    {
        return $this->getCategory() === 'adjustment';
    }

    /**
     * Verifica si este TransactionType es de expiración
     *
     * @return bool True si es de expiración
     */
    public function isExpirationCategory(): bool
    {
        return $this->getCategory() === 'expiration';
    }

    /**
     * Verifica si este TransactionType es de penalización
     *
     * @return bool True si es de penalización
     */
    public function isPenaltyCategory(): bool
    {
        return $this->getCategory() === 'penalty';
    }

    /**
     * Verifica si este TransactionType es de promoción
     *
     * @return bool True si es de promoción
     */
    public function isPromotionCategory(): bool
    {
        return $this->getCategory() === 'promotion';
    }

    /**
     * Obtiene la prioridad de procesamiento del tipo de transacción
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        // Prioridades basadas en la importancia del tipo de transacción
        switch ($this->type) {
            case self::REFUND:
                return 1; // Máxima prioridad para reembolsos
            case self::PENALTY:
                return 1; // Alta prioridad para penalizaciones
            case self::ADJUSTMENT:
                return 2; // Alta prioridad para ajustes manuales
            case self::CREDIT:
                return 2; // Alta prioridad para créditos
            case self::DEBIT:
                return 3; // Prioridad media para débitos
            case self::TRANSFER:
                return 3; // Prioridad media para transferencias
            case self::REWARD:
                return 4; // Prioridad media-baja para recompensas
            case self::BONUS:
                return 4; // Prioridad media-baja para bonos
            case self::PROMOTION:
                return 4; // Prioridad media-baja para promociones
            case self::EXPIRATION:
                return 5; // Baja prioridad para expiraciones
            default:
                return 3; // Prioridad media por defecto
        }
    }

    /**
     * Verifica si este TransactionType requiere aprobación manual
     *
     * @return bool True si requiere aprobación
     */
    public function requiresManualApproval(): bool
    {
        return in_array($this->type, [
            self::ADJUSTMENT,
            self::PENALTY,
            self::REFUND
        ]);
    }

    /**
     * Verifica si este TransactionType es reversible
     *
     * @return bool True si es reversible
     */
    public function isReversible(): bool
    {
        return in_array($this->type, [
            self::CREDIT,
            self::DEBIT,
            self::TRANSFER,
            self::REWARD,
            self::BONUS,
            self::PROMOTION
        ]);
    }

    /**
     * Verifica si este TransactionType genera notificaciones
     *
     * @return bool True si genera notificaciones
     */
    public function generatesNotifications(): bool
    {
        return in_array($this->type, [
            self::CREDIT,
            self::DEBIT,
            self::REFUND,
            self::TRANSFER,
            self::REWARD,
            self::BONUS,
            self::PROMOTION,
            self::PENALTY
        ]);
    }

    /**
     * Convierte el TransactionType a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'affects_balance' => $this->affectsBalance(),
            'balance_change' => $this->getBalanceChange(),
            'processing_priority' => $this->getProcessingPriority(),
            'requires_manual_approval' => $this->requiresManualApproval(),
            'is_reversible' => $this->isReversible(),
            'generates_notifications' => $this->generatesNotifications(),
        ];
    }

    /**
     * Representación en string del TransactionType
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Serialización para JSON
     *
     * @return string Valor para JSON
     */
    public function jsonSerialize(): string
    {
        return $this->type;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'balance_change' => $this->getBalanceChange(),
        ];
    }

    /**
     * Valida que el tipo de transacción sea válido
     *
     * @param string $type Tipo a validar
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    private static function validate(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES)) {
            throw new InvalidArgumentException(
                "TransactionType debe ser uno de los tipos válidos: " . implode(', ', self::VALID_TYPES) . 
                ", se recibió: {$type}"
            );
        }
    }

    /**
     * Verifica si un valor es un TransactionType válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validate($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Obtiene todos los tipos de transacción válidos
     *
     * @return array Lista de tipos válidos
     */
    public static function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Crea una TransactionType desde un valor mixto
     *
     * @param mixed $value Valor del tipo de transacción
     * @return self Nueva instancia de TransactionType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if ($value instanceof self) {
            return $value;
        }

        throw new InvalidArgumentException(
            'TransactionType solo puede crearse desde string o instancia de TransactionType, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene los tipos de transacción por categoría
     *
     * @param string $category Categoría a filtrar
     * @return array Tipos de la categoría
     */
    public static function getByCategory(string $category): array
    {
        $types = [];
        
        foreach (self::VALID_TYPES as $type) {
            $transactionType = new self($type);
            if ($transactionType->getCategory() === $category) {
                $types[] = $transactionType;
            }
        }
        
        return $types;
    }

    /**
     * Obtiene los tipos de transacción que afectan el balance
     *
     * @return array Tipos que afectan el balance
     */
    public static function getBalanceAffectingTypes(): array
    {
        $types = [];
        
        foreach (self::VALID_TYPES as $type) {
            $transactionType = new self($type);
            if ($transactionType->affectsBalance()) {
                $types[] = $transactionType;
            }
        }
        
        return $types;
    }

    /**
     * Obtiene los tipos de transacción que aumentan el balance
     *
     * @return array Tipos que aumentan el balance
     */
    public static function getBalanceIncreasingTypes(): array
    {
        $types = [];
        
        foreach (self::VALID_TYPES as $type) {
            $transactionType = new self($type);
            if ($transactionType->increasesBalance()) {
                $types[] = $transactionType;
            }
        }
        
        return $types;
    }

    /**
     * Obtiene los tipos de transacción que disminuyen el balance
     *
     * @return array Tipos que disminuyen el balance
     */
    public static function getBalanceDecreasingTypes(): array
    {
        $types = [];
        
        foreach (self::VALID_TYPES as $type) {
            $transactionType = new self($type);
            if ($transactionType->decreasesBalance()) {
                $types[] = $transactionType;
            }
        }
        
        return $types;
    }
}
