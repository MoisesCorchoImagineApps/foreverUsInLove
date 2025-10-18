<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * DateRange Value Object
 * 
 * Representa un rango de fechas en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de rangos de fechas en toda la aplicación.
 * 
 * Este Value Object es compartido entre todos los dominios (Matching, Profile, Chat, Commerce, etc.)
 * para mantener consistencia en el manejo de rangos de fechas a través de la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta rangos de fechas válidos (start <= end)
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DateRange con las mismas fechas son iguales
 * - Cross-domain: Utilizable en todos los dominios de la aplicación
 * - Date operations: Soporte para cálculos de duración y operaciones temporales
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class DateRange implements JsonSerializable
{
    /**
     * La fecha de inicio del rango
     */
    private readonly Carbon $startDate;

    /**
     * La fecha de fin del rango
     */
    private readonly Carbon $endDate;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(Carbon $startDate, Carbon $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Factory method para crear una nueva instancia de DateRange
     *
     * @param Carbon $startDate Fecha de inicio del rango
     * @param Carbon $endDate Fecha de fin del rango
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si las fechas no son válidas
     */
    public static function fromDates(Carbon $startDate, Carbon $endDate): self
    {
        self::validateDates($startDate, $endDate);
        return new self($startDate->copy(), $endDate->copy());
    }

    /**
     * Factory method para crear DateRange desde array
     *
     * @param array $dates Array con fechas ['start' => Carbon, 'end' => Carbon]
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si las fechas no son válidas
     */
    public static function fromArray(array $dates): self
    {
        if (!isset($dates['start']) || !isset($dates['end'])) {
            throw new InvalidArgumentException(
                'Array de fechas debe contener las claves "start" y "end"'
            );
        }

        $startDate = $dates['start'] instanceof Carbon ? $dates['start'] : Carbon::parse($dates['start']);
        $endDate = $dates['end'] instanceof Carbon ? $dates['end'] : Carbon::parse($dates['end']);

        return self::fromDates($startDate, $endDate);
    }

    /**
     * Factory method para crear DateRange desde strings de fecha
     *
     * @param string $startDate String de fecha de inicio
     * @param string $endDate String de fecha de fin
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si el formato no es válido
     */
    public static function fromStrings(string $startDate, string $endDate): self
    {
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            return self::fromDates($start, $end);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Formato de fecha inválido: {$e->getMessage()}"
            );
        }
    }

    /**
     * Factory method para crear DateRange desde CarbonInterface
     *
     * @param CarbonInterface $startDate Fecha de inicio
     * @param CarbonInterface $endDate Fecha de fin
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si las fechas no son válidas
     */
    public static function fromCarbonInterface(CarbonInterface $startDate, CarbonInterface $endDate): self
    {
        return self::fromDates(Carbon::instance($startDate), Carbon::instance($endDate));
    }

    /**
     * Factory method para crear DateRange desde modelo Eloquent
     *
     * @param \Illuminate\Database\Eloquent\Model $model Modelo con campos start_date y end_date
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si el modelo no tiene fechas válidas
     */
    public static function fromModel(\Illuminate\Database\Eloquent\Model $model): self
    {
        if (!$model->start_date || !$model->end_date) {
            throw new InvalidArgumentException(
                'El modelo debe tener campos start_date y end_date válidos'
            );
        }

        return self::fromDates(
            Carbon::parse($model->start_date),
            Carbon::parse($model->end_date)
        );
    }

    /**
     * Factory method para crear DateRange desde datos de filtro
     *
     * @param array $filterData Datos del filtro que contienen fechas
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si los datos no contienen fechas válidas
     */
    public static function fromFilterData(array $filterData): self
    {
        if (isset($filterData['date_range'])) {
            if ($filterData['date_range'] instanceof self) {
                return $filterData['date_range'];
            }
            if (is_array($filterData['date_range'])) {
                return self::fromArray($filterData['date_range']);
            }
        }

        if (isset($filterData['start_date']) && isset($filterData['end_date'])) {
            return self::fromDates(
                Carbon::parse($filterData['start_date']),
                Carbon::parse($filterData['end_date'])
            );
        }

        if (isset($filterData['from']) && isset($filterData['to'])) {
            return self::fromDates(
                Carbon::parse($filterData['from']),
                Carbon::parse($filterData['to'])
            );
        }

        throw new InvalidArgumentException(
            'Los datos del filtro deben contener información de rango de fechas válida'
        );
    }

    /**
     * Factory method para crear DateRange para la última semana
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de la última semana
     */
    public static function lastWeek(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->subWeek(),
            $reference
        );
    }

    /**
     * Factory method para crear DateRange para el último mes
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange del último mes
     */
    public static function lastMonth(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->subMonth(),
            $reference
        );
    }

    /**
     * Factory method para crear DateRange para los últimos 3 meses
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de los últimos 3 meses
     */
    public static function lastThreeMonths(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->subMonths(3),
            $reference
        );
    }

    /**
     * Factory method para crear DateRange para el último año
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange del último año
     */
    public static function lastYear(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->subYear(),
            $reference
        );
    }

    /**
     * Factory method para crear DateRange para hoy
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de hoy
     */
    public static function today(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->startOfDay(),
            $reference->copy()->endOfDay()
        );
    }

    /**
     * Factory method para crear DateRange para esta semana
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de esta semana
     */
    public static function thisWeek(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->startOfWeek(),
            $reference->copy()->endOfWeek()
        );
    }

    /**
     * Factory method para crear DateRange para este mes
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de este mes
     */
    public static function thisMonth(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->startOfMonth(),
            $reference->copy()->endOfMonth()
        );
    }

    /**
     * Factory method para crear DateRange para este año
     *
     * @param Carbon|null $referenceDate Fecha de referencia (por defecto ahora)
     * @return self DateRange de este año
     */
    public static function thisYear(?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        return self::fromDates(
            $reference->copy()->startOfYear(),
            $reference->copy()->endOfYear()
        );
    }

    /**
     * Obtiene la fecha de inicio
     *
     * @return Carbon Fecha de inicio del rango
     */
    public function getStartDate(): Carbon
    {
        return $this->startDate->copy();
    }

    /**
     * Obtiene la fecha de fin
     *
     * @return Carbon Fecha de fin del rango
     */
    public function getEndDate(): Carbon
    {
        return $this->endDate->copy();
    }

    /**
     * Obtiene las fechas como array
     *
     * @return array Array con fecha de inicio y fin
     */
    public function getDates(): array
    {
        return [
            'start' => $this->startDate->copy(),
            'end' => $this->endDate->copy()
        ];
    }

    /**
     * Obtiene las fechas como array con claves específicas
     *
     * @param string $startKey Clave para fecha de inicio (por defecto 'start_date')
     * @param string $endKey Clave para fecha de fin (por defecto 'end_date')
     * @return array Array con fechas
     */
    public function getDatesWithKeys(string $startKey = 'start_date', string $endKey = 'end_date'): array
    {
        return [
            $startKey => $this->startDate->copy(),
            $endKey => $this->endDate->copy()
        ];
    }

    /**
     * Verifica si este DateRange es igual a otro
     *
     * @param self $other Otro DateRange para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->startDate->equalTo($other->startDate) && 
               $this->endDate->equalTo($other->endDate);
    }

    /**
     * Verifica si una fecha está dentro de este rango
     *
     * @param Carbon $date Fecha a verificar
     * @param bool $inclusive Si incluir las fechas límite
     * @return bool True si la fecha está en el rango
     */
    public function contains(Carbon $date, bool $inclusive = true): bool
    {
        if ($inclusive) {
            return $date->between($this->startDate, $this->endDate);
        }
        
        return $date->isAfter($this->startDate) && $date->isBefore($this->endDate);
    }

    /**
     * Verifica si este rango se superpone con otro
     *
     * @param self $other Otro DateRange para comparar
     * @return bool True si se superponen
     */
    public function overlaps(self $other): bool
    {
        return $this->startDate->lte($other->endDate) && $this->endDate->gte($other->startDate);
    }

    /**
     * Verifica si este rango contiene completamente a otro
     *
     * @param self $other Otro DateRange para verificar
     * @return bool True si este rango contiene al otro
     */
    public function containsRange(self $other): bool
    {
        return $this->startDate->lte($other->startDate) && $this->endDate->gte($other->endDate);
    }

    /**
     * Verifica si este rango está contenido en otro
     *
     * @param self $other Otro DateRange para verificar
     * @return bool True si este rango está contenido en el otro
     */
    public function isContainedIn(self $other): bool
    {
        return $other->containsRange($this);
    }

    /**
     * Calcula la duración del rango en días
     *
     * @return int Duración en días
     */
    public function getDurationInDays(): int
    {
        return (int) $this->startDate->diffInDays($this->endDate);
    }

    /**
     * Calcula la duración del rango en horas
     *
     * @return int Duración en horas
     */
    public function getDurationInHours(): int
    {
        return (int) $this->startDate->diffInHours($this->endDate);
    }

    /**
     * Calcula la duración del rango en minutos
     *
     * @return int Duración en minutos
     */
    public function getDurationInMinutes(): int
    {
        return (int) $this->startDate->diffInMinutes($this->endDate);
    }

    /**
     * Calcula la duración del rango en segundos
     *
     * @return int Duración en segundos
     */
    public function getDurationInSeconds(): int
    {
        return (int) $this->startDate->diffInSeconds($this->endDate);
    }

    /**
     * Obtiene la duración como string legible
     *
     * @return string Duración formateada
     */
    public function getDurationForHumans(): string
    {
        return $this->startDate->diffForHumans($this->endDate, true);
    }

    /**
     * Verifica si este rango es de un solo día
     *
     * @return bool True si es de un solo día
     */
    public function isSingleDay(): bool
    {
        return $this->startDate->isSameDay($this->endDate);
    }

    /**
     * Verifica si este rango es de una sola semana
     *
     * @return bool True si es de una sola semana
     */
    public function isSingleWeek(): bool
    {
        return $this->startDate->isSameWeek($this->endDate);
    }

    /**
     * Verifica si este rango es de un solo mes
     *
     * @return bool True si es de un solo mes
     */
    public function isSingleMonth(): bool
    {
        return $this->startDate->isSameMonth($this->endDate);
    }

    /**
     * Verifica si este rango es de un solo año
     *
     * @return bool True si es de un solo año
     */
    public function isSingleYear(): bool
    {
        return $this->startDate->isSameYear($this->endDate);
    }

    /**
     * Verifica si este rango incluye el fin de semana
     *
     * @return bool True si incluye fin de semana
     */
    public function includesWeekend(): bool
    {
        $current = $this->startDate->copy();
        
        while ($current->lte($this->endDate)) {
            if ($current->isWeekend()) {
                return true;
            }
            $current->addDay();
        }
        
        return false;
    }

    /**
     * Verifica si este rango incluye días festivos
     *
     * @param array $holidays Array de fechas de días festivos
     * @return bool True si incluye días festivos
     */
    public function includesHolidays(array $holidays = []): bool
    {
        if (empty($holidays)) {
            // Días festivos básicos (pueden ser expandidos)
            $holidays = [
                '01-01', // Año Nuevo
                '12-25', // Navidad
                '12-31', // Nochevieja
            ];
        }

        $current = $this->startDate->copy();
        
        while ($current->lte($this->endDate)) {
            $dateString = $current->format('m-d');
            if (in_array($dateString, $holidays)) {
                return true;
            }
            $current->addDay();
        }
        
        return false;
    }

    /**
     * Obtiene el día de la semana de inicio
     *
     * @return string Día de la semana
     */
    public function getStartDayOfWeek(): string
    {
        return $this->startDate->dayName;
    }

    /**
     * Obtiene el día de la semana de fin
     *
     * @return string Día de la semana
     */
    public function getEndDayOfWeek(): string
    {
        return $this->endDate->dayName;
    }

    /**
     * Obtiene la estación del año para la fecha de inicio
     *
     * @return string Estación del año
     */
    public function getStartSeason(): string
    {
        return $this->getSeason($this->startDate);
    }

    /**
     * Obtiene la estación del año para la fecha de fin
     *
     * @return string Estación del año
     */
    public function getEndSeason(): string
    {
        return $this->getSeason($this->endDate);
    }

    /**
     * Verifica si este rango cruza estaciones del año
     *
     * @return bool True si cruza estaciones
     */
    public function crossesSeasons(): bool
    {
        return $this->getStartSeason() !== $this->getEndSeason();
    }

    /**
     * Obtiene el tipo de rango temporal
     *
     * @return string Tipo de rango (short, medium, long, very_long)
     */
    public function getRangeType(): string
    {
        $days = $this->getDurationInDays();
        
        if ($days <= 1) return 'short';
        if ($days <= 7) return 'medium';
        if ($days <= 30) return 'long';
        return 'very_long';
    }

    /**
     * Convierte el DateRange a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->toISOString(),
            'end_date' => $this->endDate->toISOString(),
            'duration_days' => $this->getDurationInDays(),
            'duration_hours' => $this->getDurationInHours(),
            'duration_minutes' => $this->getDurationInMinutes(),
            'is_single_day' => $this->isSingleDay(),
            'is_single_week' => $this->isSingleWeek(),
            'is_single_month' => $this->isSingleMonth(),
            'is_single_year' => $this->isSingleYear(),
            'includes_weekend' => $this->includesWeekend(),
            'start_day_of_week' => $this->getStartDayOfWeek(),
            'end_day_of_week' => $this->getEndDayOfWeek(),
            'start_season' => $this->getStartSeason(),
            'end_season' => $this->getEndSeason(),
            'crosses_seasons' => $this->crossesSeasons(),
            'range_type' => $this->getRangeType(),
            'duration_for_humans' => $this->getDurationForHumans(),
        ];
    }

    /**
     * Representación en string del DateRange
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->startDate->format('Y-m-d') . ' to ' . $this->endDate->format('Y-m-d');
    }

    /**
     * Serialización para JSON
     *
     * @return array Datos para JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->startDate->toISOString(),
            'end' => $this->endDate->toISOString()
        ];
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'start_date' => $this->startDate->toISOString(),
            'end_date' => $this->endDate->toISOString(),
            'duration_days' => $this->getDurationInDays(),
            'range_type' => $this->getRangeType(),
            'is_single_day' => $this->isSingleDay(),
        ];
    }

    /**
     * Valida que las fechas sean válidas
     *
     * @param Carbon $startDate Fecha de inicio a validar
     * @param Carbon $endDate Fecha de fin a validar
     * @throws InvalidArgumentException Si las fechas no son válidas
     */
    private static function validateDates(Carbon $startDate, Carbon $endDate): void
    {
        if ($startDate->isAfter($endDate)) {
            throw new InvalidArgumentException(
                "La fecha de inicio debe ser anterior o igual a la fecha de fin. " .
                "Inicio: {$startDate->toISOString()}, Fin: {$endDate->toISOString()}"
            );
        }

        // Verificar que no sean fechas futuras extremas (más de 100 años en el futuro)
        $maxFutureDate = Carbon::now()->addYears(100);
        if ($startDate->isAfter($maxFutureDate) || $endDate->isAfter($maxFutureDate)) {
            throw new InvalidArgumentException(
                "Las fechas no pueden ser más de 100 años en el futuro"
            );
        }

        // Verificar que no sean fechas pasadas extremas (más de 100 años en el pasado)
        $maxPastDate = Carbon::now()->subYears(100);
        if ($startDate->isBefore($maxPastDate) || $endDate->isBefore($maxPastDate)) {
            throw new InvalidArgumentException(
                "Las fechas no pueden ser más de 100 años en el pasado"
            );
        }
    }

    /**
     * Obtiene la estación del año para una fecha
     *
     * @param Carbon $date Fecha para obtener la estación
     * @return string Estación del año
     */
    private function getSeason(Carbon $date): string
    {
        $month = $date->month;
        
        if (in_array($month, [12, 1, 2])) return 'winter';
        if (in_array($month, [3, 4, 5])) return 'spring';
        if (in_array($month, [6, 7, 8])) return 'summer';
        return 'autumn';
    }

    /**
     * Verifica si un valor es un DateRange válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if ($value instanceof self) {
                return true;
            }

            if (is_array($value) && isset($value['start']) && isset($value['end'])) {
                $startDate = $value['start'] instanceof Carbon ? $value['start'] : Carbon::parse($value['start']);
                $endDate = $value['end'] instanceof Carbon ? $value['end'] : Carbon::parse($value['end']);
                self::validateDates($startDate, $endDate);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un DateRange desde un valor mixto
     *
     * @param mixed $value Valor del rango de fechas
     * @return self Nueva instancia de DateRange
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            return self::fromModel($value);
        }

        throw new InvalidArgumentException(
            'DateRange solo puede crearse desde array, modelo o instancia de DateRange, se recibió: ' . gettype($value)
        );
    }

    /**
     * Crea un DateRange aleatorio para testing
     *
     * @param int $minDays Duración mínima en días
     * @param int $maxDays Duración máxima en días
     * @param Carbon|null $referenceDate Fecha de referencia
     * @return self Nueva instancia de DateRange aleatoria
     */
    public static function random(int $minDays = 1, int $maxDays = 30, ?Carbon $referenceDate = null): self
    {
        $reference = $referenceDate ?? Carbon::now();
        $duration = rand($minDays, $maxDays);
        
        $startDate = $reference->copy()->subDays(rand(0, 365));
        $endDate = $startDate->copy()->addDays($duration);
        
        return self::fromDates($startDate, $endDate);
    }

    /**
     * Verifica si este DateRange cumple con criterios específicos
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['min_duration_days'])) {
            if ($this->getDurationInDays() < $criteria['min_duration_days']) {
                return false;
            }
        }

        if (isset($criteria['max_duration_days'])) {
            if ($this->getDurationInDays() > $criteria['max_duration_days']) {
                return false;
            }
        }

        if (isset($criteria['range_type'])) {
            if ($this->getRangeType() !== $criteria['range_type']) {
                return false;
            }
        }

        if (isset($criteria['includes_weekend']) && $criteria['includes_weekend']) {
            if (!$this->includesWeekend()) {
                return false;
            }
        }

        if (isset($criteria['excludes_weekend']) && $criteria['excludes_weekend']) {
            if ($this->includesWeekend()) {
                return false;
            }
        }

        if (isset($criteria['crosses_seasons']) && $criteria['crosses_seasons']) {
            if (!$this->crossesSeasons()) {
                return false;
            }
        }

        if (isset($criteria['single_day_only']) && $criteria['single_day_only']) {
            if (!$this->isSingleDay()) {
                return false;
            }
        }

        if (isset($criteria['min_start_date'])) {
            if ($this->startDate->isBefore($criteria['min_start_date'])) {
                return false;
            }
        }

        if (isset($criteria['max_end_date'])) {
            if ($this->endDate->isAfter($criteria['max_end_date'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del DateRange
     *
     * @return array Estadísticas del rango de fechas
     */
    public function getStats(): array
    {
        return [
            'start_date' => $this->startDate->toISOString(),
            'end_date' => $this->endDate->toISOString(),
            'duration_days' => $this->getDurationInDays(),
            'duration_hours' => $this->getDurationInHours(),
            'duration_minutes' => $this->getDurationInMinutes(),
            'range_type' => $this->getRangeType(),
            'is_single_day' => $this->isSingleDay(),
            'is_single_week' => $this->isSingleWeek(),
            'is_single_month' => $this->isSingleMonth(),
            'is_single_year' => $this->isSingleYear(),
            'includes_weekend' => $this->includesWeekend(),
            'crosses_seasons' => $this->crossesSeasons(),
            'start_season' => $this->getStartSeason(),
            'end_season' => $this->getEndSeason(),
            'start_day_of_week' => $this->getStartDayOfWeek(),
            'end_day_of_week' => $this->getEndDayOfWeek(),
            'duration_for_humans' => $this->getDurationForHumans(),
            'date_range_string' => $this->__toString(),
        ];
    }

    /**
     * Genera un hash único para este DateRange (útil para cache keys)
     *
     * @return string Hash único del DateRange
     */
    public function getHash(): string
    {
        return 'daterange_' . md5($this->startDate->toISOString() . '_' . $this->endDate->toISOString());
    }

    /**
     * Verifica si este DateRange puede ser utilizado para matching
     * (rangos válidos que no son demasiado largos o cortos)
     *
     * @return bool True si es válido para matching
     */
    public function isValidForMatching(): bool
    {
        $durationDays = $this->getDurationInDays();
        
        // Rango válido para matching: entre 1 día y 1 año
        return $durationDays >= 1 && $durationDays <= 365;
    }

    /**
     * Obtiene el nivel de precisión del rango
     * (basado en la duración del rango)
     *
     * @return string Precisión (high, medium, low)
     */
    public function getPrecisionLevel(): string
    {
        $durationDays = $this->getDurationInDays();
        
        if ($durationDays <= 1) return 'high';
        if ($durationDays <= 7) return 'medium';
        return 'low';
    }

    /**
     * Crea un nuevo DateRange expandido por un número de días
     *
     * @param int $daysBefore Días a agregar antes del inicio
     * @param int $daysAfter Días a agregar después del fin
     * @return self Nuevo DateRange expandido
     */
    public function expand(int $daysBefore = 0, int $daysAfter = 0): self
    {
        return self::fromDates(
            $this->startDate->copy()->subDays($daysBefore),
            $this->endDate->copy()->addDays($daysAfter)
        );
    }

    /**
     * Crea un nuevo DateRange contraído por un número de días
     *
     * @param int $daysBefore Días a quitar antes del inicio
     * @param int $daysAfter Días a quitar después del fin
     * @return self Nuevo DateRange contraído
     */
    public function contract(int $daysBefore = 0, int $daysAfter = 0): self
    {
        return self::fromDates(
            $this->startDate->copy()->addDays($daysBefore),
            $this->endDate->copy()->subDays($daysAfter)
        );
    }

    /**
     * Crea un nuevo DateRange movido por un número de días
     *
     * @param int $days Días a mover (positivo = futuro, negativo = pasado)
     * @return self Nuevo DateRange movido
     */
    public function move(int $days): self
    {
        return self::fromDates(
            $this->startDate->copy()->addDays($days),
            $this->endDate->copy()->addDays($days)
        );
    }
}
