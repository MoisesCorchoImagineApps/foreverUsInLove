<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Location Value Object
 * 
 * Representa una ubicación geográfica en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de coordenadas geográficas en toda la aplicación.
 * 
 * Este Value Object es compartido entre todos los dominios (Matching, Profile, Chat, etc.)
 * para mantener consistencia en el manejo de ubicaciones geográficas a través de la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta coordenadas válidas (latitud/longitud)
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos Location con las mismas coordenadas son iguales
 * - Cross-domain: Utilizable en todos los dominios de la aplicación
 * - Geographic operations: Soporte para cálculos de distancia y proximidad
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class Location implements JsonSerializable
{
    /**
     * La latitud de la ubicación
     */
    private readonly float $latitude;

    /**
     * La longitud de la ubicación
     */
    private readonly float $longitude;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Factory method para crear una nueva instancia de Location
     *
     * @param float $latitude Latitud (-90 a 90)
     * @param float $longitude Longitud (-180 a 180)
     * @return self Nueva instancia de Location
     * @throws InvalidArgumentException Si las coordenadas no son válidas
     */
    public static function fromCoordinates(float $latitude, float $longitude): self
    {
        self::validateCoordinates($latitude, $longitude);
        return new self($latitude, $longitude);
    }

    /**
     * Factory method para crear Location desde array
     *
     * @param array $coordinates Array con coordenadas ['lat' => float, 'lng' => float]
     * @return self Nueva instancia de Location
     * @throws InvalidArgumentException Si las coordenadas no son válidas
     */
    public static function fromArray(array $coordinates): self
    {
        if (!isset($coordinates['lat']) || !isset($coordinates['lng'])) {
            throw new InvalidArgumentException(
                'Array de coordenadas debe contener las claves "lat" y "lng"'
            );
        }

        return self::fromCoordinates($coordinates['lat'], $coordinates['lng']);
    }

    /**
     * Factory method para crear Location desde string de coordenadas
     *
     * @param string $coordinates String en formato "lat,lng" o "lat,lng"
     * @return self Nueva instancia de Location
     * @throws InvalidArgumentException Si el formato no es válido
     */
    public static function fromString(string $coordinates): self
    {
        $parts = explode(',', trim($coordinates));
        
        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                'String de coordenadas debe estar en formato "lat,lng", se recibió: ' . $coordinates
            );
        }

        $lat = trim($parts[0]);
        $lng = trim($parts[1]);

        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException(
                'Las coordenadas deben ser números válidos, se recibió: ' . $coordinates
            );
        }

        return self::fromCoordinates((float) $lat, (float) $lng);
    }

    /**
     * Factory method para crear Location desde modelo Eloquent
     *
     * @param \Illuminate\Database\Eloquent\Model $model Modelo con campos latitude y longitude
     * @return self Nueva instancia de Location
     * @throws InvalidArgumentException Si el modelo no tiene coordenadas válidas
     */
    public static function fromModel(\Illuminate\Database\Eloquent\Model $model): self
    {
        if (!$model->latitude || !$model->longitude) {
            throw new InvalidArgumentException(
                'El modelo debe tener campos latitude y longitude válidos'
            );
        }

        return self::fromCoordinates($model->latitude, $model->longitude);
    }

    /**
     * Factory method para crear Location desde datos de perfil
     *
     * @param array $profileData Datos del perfil que contienen ubicación
     * @return self Nueva instancia de Location
     * @throws InvalidArgumentException Si los datos no contienen ubicación válida
     */
    public static function fromProfileData(array $profileData): self
    {
        if (isset($profileData['location'])) {
            if (is_array($profileData['location'])) {
                return self::fromArray($profileData['location']);
            }
            if (is_string($profileData['location'])) {
                return self::fromString($profileData['location']);
            }
        }

        if (isset($profileData['latitude']) && isset($profileData['longitude'])) {
            return self::fromCoordinates($profileData['latitude'], $profileData['longitude']);
        }

        throw new InvalidArgumentException(
            'Los datos del perfil deben contener información de ubicación válida'
        );
    }

    /**
     * Obtiene la latitud
     *
     * @return float Latitud de la ubicación
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * Obtiene la longitud
     *
     * @return float Longitud de la ubicación
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * Obtiene las coordenadas como array
     *
     * @return array Array con latitud y longitud
     */
    public function getCoordinates(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude
        ];
    }

    /**
     * Obtiene las coordenadas como array con claves específicas
     *
     * @param string $latKey Clave para latitud (por defecto 'latitude')
     * @param string $lngKey Clave para longitud (por defecto 'longitude')
     * @return array Array con coordenadas
     */
    public function getCoordinatesWithKeys(string $latKey = 'latitude', string $lngKey = 'longitude'): array
    {
        return [
            $latKey => $this->latitude,
            $lngKey => $this->longitude
        ];
    }

    /**
     * Verifica si esta Location es igual a otra
     *
     * @param self $other Otra Location para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }

    /**
     * Verifica si esta Location está cerca de otra (dentro de un radio específico)
     *
     * @param self $other Otra Location para comparar
     * @param float $radiusKm Radio en kilómetros
     * @return bool True si están dentro del radio
     */
    public function isNearTo(self $other, float $radiusKm): bool
    {
        return $this->distanceTo($other) <= $radiusKm;
    }

    /**
     * Calcula la distancia a otra Location usando la fórmula de Haversine
     *
     * @param self $other Otra Location
     * @param string $unit Unidad de medida ('km' o 'miles')
     * @return float Distancia en la unidad especificada
     */
    public function distanceTo(self $other, string $unit = 'km'): float
    {
        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($other->latitude);
        $lon2 = deg2rad($other->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = 6371 * $c; // Radio de la Tierra en kilómetros

        return $unit === 'miles' ? $distance * 0.621371 : $distance;
    }

    /**
     * Verifica si esta Location está en un país específico (aproximación)
     *
     * @param string $countryCode Código del país (ISO 3166-1 alpha-2)
     * @return bool True si está en el país
     */
    public function isInCountry(string $countryCode): bool
    {
        // Mapeo básico de coordenadas aproximadas por país
        $countryBounds = [
            'US' => ['lat' => [24.0, 49.0], 'lng' => [-125.0, -66.0]],
            'MX' => ['lat' => [14.0, 32.0], 'lng' => [-118.0, -86.0]],
            'CA' => ['lat' => [41.0, 84.0], 'lng' => [-141.0, -52.0]],
            'ES' => ['lat' => [35.0, 44.0], 'lng' => [-9.0, 4.0]],
            'FR' => ['lat' => [41.0, 51.0], 'lng' => [-5.0, 9.0]],
            'GB' => ['lat' => [49.0, 61.0], 'lng' => [-8.0, 2.0]],
            'DE' => ['lat' => [47.0, 55.0], 'lng' => [5.0, 15.0]],
            'IT' => ['lat' => [35.0, 47.0], 'lng' => [6.0, 19.0]],
            'AR' => ['lat' => [-55.0, -21.0], 'lng' => [-73.0, -53.0]],
            'BR' => ['lat' => [-34.0, 5.0], 'lng' => [-74.0, -34.0]],
            'CO' => ['lat' => [-4.0, 15.0], 'lng' => [-82.0, -66.0]],
            'PE' => ['lat' => [-18.0, 0.0], 'lng' => [-84.0, -68.0]],
            'CL' => ['lat' => [-56.0, -17.0], 'lng' => [-76.0, -66.0]],
        ];

        if (!isset($countryBounds[$countryCode])) {
            return false;
        }

        $bounds = $countryBounds[$countryCode];
        
        return $this->latitude >= $bounds['lat'][0] && $this->latitude <= $bounds['lat'][1] &&
               $this->longitude >= $bounds['lng'][0] && $this->longitude <= $bounds['lng'][1];
    }

    /**
     * Verifica si esta Location está en una ciudad importante (aproximación)
     *
     * @return bool True si está en una ciudad importante
     */
    public function isInMajorCity(): bool
    {
        // Coordenadas aproximadas de ciudades importantes
        $majorCities = [
            ['lat' => 40.7128, 'lng' => -74.0060, 'radius' => 50], // New York
            ['lat' => 34.0522, 'lng' => -118.2437, 'radius' => 50], // Los Angeles
            ['lat' => 41.8781, 'lng' => -87.6298, 'radius' => 50], // Chicago
            ['lat' => 19.4326, 'lng' => -99.1332, 'radius' => 50], // Mexico City
            ['lat' => 40.4168, 'lng' => -3.7038, 'radius' => 50], // Madrid
            ['lat' => 48.8566, 'lng' => 2.3522, 'radius' => 50], // Paris
            ['lat' => 51.5074, 'lng' => -0.1278, 'radius' => 50], // London
            ['lat' => 52.5200, 'lng' => 13.4050, 'radius' => 50], // Berlin
            ['lat' => 41.9028, 'lng' => 12.4964, 'radius' => 50], // Rome
            ['lat' => -34.6118, 'lng' => -58.3960, 'radius' => 50], // Buenos Aires
            ['lat' => -23.5505, 'lng' => -46.6333, 'radius' => 50], // São Paulo
            ['lat' => 4.7110, 'lng' => -74.0721, 'radius' => 50], // Bogotá
        ];

        foreach ($majorCities as $city) {
            $cityLocation = self::fromCoordinates($city['lat'], $city['lng']);
            if ($this->isNearTo($cityLocation, $city['radius'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene el hemisferio de la ubicación
     *
     * @return string 'north', 'south', 'equator'
     */
    public function getHemisphere(): string
    {
        if ($this->latitude > 0) {
            return 'north';
        } elseif ($this->latitude < 0) {
            return 'south';
        } else {
            return 'equator';
        }
    }

    /**
     * Obtiene la zona horaria aproximada basada en la longitud
     *
     * @return int Offset de zona horaria en horas
     */
    public function getApproximateTimezoneOffset(): int
    {
        return (int) round($this->longitude / 15);
    }

    /**
     * Verifica si esta Location está en el océano (aproximación)
     *
     * @return bool True si está en el océano
     */
    public function isInOcean(): bool
    {
        // Coordenadas aproximadas de océanos principales
        $oceanBounds = [
            ['lat' => [-90, 90], 'lng' => [-180, -100]], // Pacífico Oeste
            ['lat' => [-90, 90], 'lng' => [-100, -20]], // Atlántico
            ['lat' => [-90, 90], 'lng' => [20, 180]], // Pacífico Este/Índico
        ];

        foreach ($oceanBounds as $ocean) {
            if ($this->latitude >= $ocean['lat'][0] && $this->latitude <= $ocean['lat'][1] &&
                $this->longitude >= $ocean['lng'][0] && $this->longitude <= $ocean['lng'][1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convierte la Location a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'coordinates' => $this->getCoordinates(),
            'hemisphere' => $this->getHemisphere(),
            'timezone_offset' => $this->getApproximateTimezoneOffset(),
            'is_major_city' => $this->isInMajorCity(),
            'is_ocean' => $this->isInOcean(),
        ];
    }

    /**
     * Representación en string de la Location
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return "{$this->latitude},{$this->longitude}";
    }

    /**
     * Serialización para JSON
     *
     * @return array Datos para JSON
     */
    public function jsonSerialize(): array
    {
        return $this->getCoordinates();
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'hemisphere' => $this->getHemisphere(),
            'is_major_city' => $this->isInMajorCity(),
            'is_ocean' => $this->isInOcean(),
        ];
    }

    /**
     * Valida que las coordenadas sean válidas
     *
     * @param float $latitude Latitud a validar
     * @param float $longitude Longitud a validar
     * @throws InvalidArgumentException Si las coordenadas no son válidas
     */
    private static function validateCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException(
                "La latitud debe estar entre -90 y 90 grados, se recibió: {$latitude}"
            );
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException(
                "La longitud debe estar entre -180 y 180 grados, se recibió: {$longitude}"
            );
        }

        // Verificar que no sean NaN o infinito
        if (!is_finite($latitude) || !is_finite($longitude)) {
            throw new InvalidArgumentException(
                "Las coordenadas deben ser números finitos válidos"
            );
        }
    }

    /**
     * Verifica si un valor es una Location válida
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

            if (is_array($value) && isset($value['lat']) && isset($value['lng'])) {
                self::validateCoordinates($value['lat'], $value['lng']);
                return true;
            }

            if (is_string($value)) {
                self::fromString($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea una Location desde un valor mixto
     *
     * @param mixed $value Valor de la ubicación
     * @return self Nueva instancia de Location
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

        if (is_string($value)) {
            return self::fromString($value);
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            return self::fromModel($value);
        }

        throw new InvalidArgumentException(
            'Location solo puede crearse desde array, string, modelo o instancia de Location, se recibió: ' . gettype($value)
        );
    }

    /**
     * Crea una Location aleatoria para testing
     *
     * @param string|null $countryCode Código del país para limitar la ubicación
     * @return self Nueva instancia de Location aleatoria
     */
    public static function random(?string $countryCode = null): self
    {
        if ($countryCode) {
            // Generar coordenadas dentro de los límites del país
            $countryBounds = [
                'US' => ['lat' => [24.0, 49.0], 'lng' => [-125.0, -66.0]],
                'MX' => ['lat' => [14.0, 32.0], 'lng' => [-118.0, -86.0]],
                'ES' => ['lat' => [35.0, 44.0], 'lng' => [-9.0, 4.0]],
                'AR' => ['lat' => [-55.0, -21.0], 'lng' => [-73.0, -53.0]],
            ];

            if (isset($countryBounds[$countryCode])) {
                $bounds = $countryBounds[$countryCode];
                $lat = $bounds['lat'][0] + (mt_rand() / mt_getrandmax()) * ($bounds['lat'][1] - $bounds['lat'][0]);
                $lng = $bounds['lng'][0] + (mt_rand() / mt_getrandmax()) * ($bounds['lng'][1] - $bounds['lng'][0]);
                return self::fromCoordinates($lat, $lng);
            }
        }

        // Generar coordenadas aleatorias globales
        $lat = -90 + (mt_rand() / mt_getrandmax()) * 180;
        $lng = -180 + (mt_rand() / mt_getrandmax()) * 360;
        
        return self::fromCoordinates($lat, $lng);
    }

    /**
     * Verifica si esta Location cumple con criterios específicos
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['country'])) {
            if (!$this->isInCountry($criteria['country'])) {
                return false;
            }
        }

        if (isset($criteria['hemisphere'])) {
            if ($this->getHemisphere() !== $criteria['hemisphere']) {
                return false;
            }
        }

        if (isset($criteria['major_city_only']) && $criteria['major_city_only']) {
            if (!$this->isInMajorCity()) {
                return false;
            }
        }

        if (isset($criteria['exclude_ocean']) && $criteria['exclude_ocean']) {
            if ($this->isInOcean()) {
                return false;
            }
        }

        if (isset($criteria['max_latitude'])) {
            if ($this->latitude > $criteria['max_latitude']) {
                return false;
            }
        }

        if (isset($criteria['min_latitude'])) {
            if ($this->latitude < $criteria['min_latitude']) {
                return false;
            }
        }

        if (isset($criteria['max_longitude'])) {
            if ($this->longitude > $criteria['max_longitude']) {
                return false;
            }
        }

        if (isset($criteria['min_longitude'])) {
            if ($this->longitude < $criteria['min_longitude']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas de la Location
     *
     * @return array Estadísticas de la ubicación
     */
    public function getStats(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'hemisphere' => $this->getHemisphere(),
            'timezone_offset' => $this->getApproximateTimezoneOffset(),
            'is_major_city' => $this->isInMajorCity(),
            'is_ocean' => $this->isInOcean(),
            'coordinates_string' => $this->__toString(),
        ];
    }

    /**
     * Genera un hash único para esta Location (útil para cache keys)
     *
     * @return string Hash único de la Location
     */
    public function getHash(): string
    {
        return 'location_' . md5($this->__toString());
    }

    /**
     * Verifica si esta Location puede ser utilizada para matching
     * (ubicaciones válidas que no están en el océano)
     *
     * @return bool True si es válida para matching
     */
    public function isValidForMatching(): bool
    {
        return !$this->isInOcean() && $this->isValid($this);
    }

    /**
     * Obtiene la precisión aproximada de la ubicación
     * (basada en la proximidad a ciudades importantes)
     *
     * @return string Precisión (high, medium, low)
     */
    public function getPrecisionLevel(): string
    {
        if ($this->isInMajorCity()) {
            return 'high';
        }

        // Verificar proximidad a ciudades importantes (100km)
        $majorCities = [
            ['lat' => 40.7128, 'lng' => -74.0060], // New York
            ['lat' => 34.0522, 'lng' => -118.2437], // Los Angeles
            ['lat' => 19.4326, 'lng' => -99.1332], // Mexico City
            ['lat' => 40.4168, 'lng' => -3.7038], // Madrid
        ];

        foreach ($majorCities as $city) {
            $cityLocation = self::fromCoordinates($city['lat'], $city['lng']);
            if ($this->isNearTo($cityLocation, 100)) {
                return 'medium';
            }
        }

        return 'low';
    }
}
