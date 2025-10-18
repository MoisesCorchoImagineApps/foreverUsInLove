<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Repositories;

use App\Domain\Commerce\Entities\Gift;
use App\Domain\Commerce\Entities\Plan;
use App\Domain\Commerce\Entities\CoinPackage;
use App\Domain\Commerce\Entities\Product;
use App\Domain\Commerce\Entities\GiftCategory;
use App\Domain\Commerce\Entities\Feature;
use App\Domain\Commerce\ValueObjects\GiftId;
use App\Domain\Commerce\ValueObjects\PlanId;
use App\Domain\Commerce\ValueObjects\CoinPackageId;
use App\Domain\Commerce\ValueObjects\ProductId;
use App\Domain\Commerce\ValueObjects\ProductType;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * ProductRepositoryInterface - Repository contracts for product management
 * 
 * Comprehensive repository interface for product management in the ForeverUsInLove
 * dating application. Handles all product types including gifts, subscription plans,
 * coin packages, features, and premium services with full CRUD operations,
 * advanced search capabilities, inventory management, and analytics support.
 * 
 * Features:
 * - Complete product lifecycle management (create, read, update, delete)
 * - Multi-type product support (gifts, plans, coins, features, services)
 * - Advanced search and filtering with full-text capabilities
 * - Inventory management and availability tracking
 * - Pricing and promotional management
 * - Category and taxonomy organization
 * - Product analytics and performance tracking
 * - Personalization and recommendation support
 * - A/B testing and variant management
 * - Geographic and demographic targeting
 * - Seasonal and promotional product handling
 * - Integration with payment and fulfillment systems
 * - Performance optimization with caching and indexing
 * - Compliance and regulatory feature management
 * 
 * Architecture:
 * - Repository Pattern implementation
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Comprehensive error handling and validation
 * - Performance optimization strategies
 * 
 * @package App\Domain\Commerce\Repositories
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Gift
 * @see \App\Domain\Commerce\Entities\Plan
 * @see \App\Domain\Commerce\Entities\CoinPackage
 * @see \App\Infrastructure\Persistence\Eloquent\ProductEloquentRepository
 */
interface ProductRepositoryInterface
{
    // ============================================================================
    // GIFT MANAGEMENT OPERATIONS
    // ============================================================================

    /**
     * Find gift by ID with optional eager loading
     * 
     * @param GiftId|string $giftId Unique gift identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return Gift|null Gift entity if found, null otherwise
     */
    public function findGiftById(GiftId|string $giftId, array $with = []): ?Gift;

    /**
     * Get available gifts with comprehensive filtering
     * 
     * @param array{
     *     categories?: array<string>,
     *     types?: array<string>,
     *     price_range?: array{min: float, max: float},
     *     rarity?: array<string>,
     *     seasonal?: bool,
     *     limited_edition?: bool,
     *     premium_only?: bool,
     *     availability?: string,
     *     search?: string,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int
     * } $filters Comprehensive filtering options
     * 
     * @return LengthAwarePaginator Paginated gift results
     */
    public function getAvailableGifts(array $filters = []): LengthAwarePaginator;

    /**
     * Get gift categories with statistics
     * 
     * @param array{
     *     include_counts?: bool,
     *     include_trending?: bool,
     *     active_only?: bool
     * } $options Category retrieval options
     * 
     * @return Collection Gift categories with optional statistics
     */
    public function getGiftCategories(array $options = []): Collection;

    /**
     * Build gift catalog query with personalization
     * 
     * @param array $filters Filter parameters
     * @return mixed Query builder instance for further customization
     */
    public function buildGiftCatalogQuery(array $filters);

    /**
     * Execute gift catalog query with pagination
     * 
     * @param mixed $query Pre-built query instance
     * @param array $filters Additional execution parameters
     * 
     * @return LengthAwarePaginator Executed query results
     */
    public function executeGiftCatalogQuery($query, array $filters): LengthAwarePaginator;

    /**
     * Apply personalization to gift query
     * 
     * @param mixed $query Query builder instance
     * @param array $userProfile User personalization data
     * 
     * @return mixed Modified query with personalization
     */
    public function applyPersonalization($query, array $userProfile);

    /**
     * Increment gift popularity counter
     * 
     * @param GiftId|string $giftId Gift to update
     * @param int $increment Amount to increment by
     * 
     * @return bool True if successfully updated
     */
    public function incrementGiftPopularity(GiftId|string $giftId, int $increment = 1): bool;

    /**
     * Update gift revenue tracking
     * 
     * @param GiftId|string $giftId Gift to update
     * @param float $amount Revenue amount to add
     * 
     * @return bool True if successfully updated
     */
    public function updateGiftRevenue(GiftId|string $giftId, float $amount): bool;

    // ============================================================================
    // SUBSCRIPTION PLAN OPERATIONS
    // ============================================================================

    /**
     * Find plan by ID with optional eager loading
     * 
     * @param PlanId|string $planId Unique plan identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return Plan|null Plan entity if found, null otherwise
     */
    public function findPlanById(PlanId|string $planId, array $with = []): ?Plan;

    /**
     * Get available subscription plans with filtering
     * 
     * @param array{
     *     types?: array<string>,
     *     tiers?: array<string>,
     *     billing_cycles?: array<string>,
     *     currency?: Currency|string,
     *     features?: array<string>,
     *     target_audience?: string,
     *     geographic_region?: string,
     *     include_trials?: bool,
     *     active_only?: bool,
     *     sort_by?: string
     * } $options Plan filtering and retrieval options
     * 
     * @return Collection Available subscription plans
     */
    public function getAvailablePlans(array $options = []): Collection;

    /**
     * Get plan features and capabilities
     * 
     * @param PlanId|string $planId Plan to analyze
     * 
     * @return array{
     *     included_features: array,
     *     feature_limits: array,
     *     exclusive_benefits: array,
     *     restrictions: array
     * } Comprehensive plan feature analysis
     */
    public function getPlanFeatures(PlanId|string $planId): array;

    /**
     * Compare plans for feature differences
     * 
     * @param PlanId|string $planId1 First plan for comparison
     * @param PlanId|string $planId2 Second plan for comparison
     * 
     * @return array{
     *     common_features: array,
     *     plan1_exclusive: array,
     *     plan2_exclusive: array,
     *     feature_differences: array
     * } Detailed plan comparison
     */
    public function comparePlans(PlanId|string $planId1, PlanId|string $planId2): array;

    // ============================================================================
    // COIN PACKAGE OPERATIONS
    // ============================================================================

    /**
     * Find coin package by ID
     * 
     * @param CoinPackageId|string $packageId Unique package identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return CoinPackage|null Package entity if found, null otherwise
     */
    public function findCoinPackageById(CoinPackageId|string $packageId, array $with = []): ?CoinPackage;

    /**
     * Get available coin packages with pricing
     * 
     * @param array{
     *     currency?: Currency|string,
     *     min_coins?: int,
     *     max_coins?: int,
     *     bonus_tiers?: array<string>,
     *     promotional_only?: bool,
     *     user_tier?: string,
     *     sort_by?: string
     * } $options Package filtering options
     * 
     * @return Collection Available coin packages with pricing
     */
    public function getCoinPackages(array $options = []): Collection;

    /**
     * Get coin package pricing for specific currency
     * 
     * @param CoinPackageId|string $packageId Package to price
     * @param Currency|string $currency Target currency
     * @param array{
     *     user_id?: UserId|string,
     *     promotional_codes?: array<string>,
     *     volume_discounts?: bool
     * } $options Pricing calculation options
     * 
     * @return array{
     *     base_price: float,
     *     discounted_price: float,
     *     currency: string,
     *     discounts_applied: array,
     *     bonus_coins: int,
     *     total_value: float
     * } Comprehensive pricing information
     */
    public function getCoinPackagePricing(CoinPackageId|string $packageId, Currency|string $currency, array $options = []): array;

    // ============================================================================
    // PRODUCT SEARCH AND DISCOVERY
    // ============================================================================

    /**
     * Search products across all types with advanced criteria
     * 
     * @param array{
     *     query?: string,
     *     product_types?: array<ProductType|string>,
     *     categories?: array<string>,
     *     price_range?: array{min: float, max: float},
     *     availability?: array<string>,
     *     user_context?: array{
     *         user_id?: UserId|string,
     *         preferences?: array,
     *         history?: array
     *     },
     *     filters?: array,
     *     sort_by?: string,
     *     per_page?: int
     * } $criteria Comprehensive search criteria
     * 
     * @return LengthAwarePaginator Search results with relevance scoring
     */
    public function searchProducts(array $criteria): LengthAwarePaginator;

    /**
     * Get product recommendations for user
     * 
     * @param UserId|string $userId Target user for recommendations
     * @param array{
     *     product_types?: array<ProductType|string>,
     *     categories?: array<string>,
     *     algorithm?: string,
     *     max_results?: int,
     *     include_reasoning?: bool,
     *     exclude_owned?: bool
     * } $options Recommendation configuration
     * 
     * @return array{
     *     recommendations: Collection,
     *     reasoning?: array,
     *     confidence_scores: array,
     *     categories_recommended: array
     * } Personalized product recommendations
     */
    public function getProductRecommendations(UserId|string $userId, array $options = []): array;

    /**
     * Get trending products across categories
     * 
     * @param array{
     *     period?: string,
     *     product_types?: array<ProductType|string>,
     *     categories?: array<string>,
     *     geographic_region?: string,
     *     user_segment?: string,
     *     limit?: int
     * } $options Trending analysis options
     * 
     * @return Collection Trending products with popularity metrics
     */
    public function getTrendingProducts(array $options = []): Collection;

    /**
     * Get featured products with promotional priority
     * 
     * @param array{
     *     placement?: string,
     *     product_types?: array<ProductType|string>,
     *     target_audience?: string,
     *     active_campaigns?: bool,
     *     limit?: int
     * } $options Featured product configuration
     * 
     * @return Collection Featured products with promotional data
     */
    public function getFeaturedProducts(array $options = []): Collection;

    // ============================================================================
    // INVENTORY AND AVAILABILITY
    // ============================================================================

    /**
     * Check product availability
     * 
     * @param ProductId|string $productId Product to check
     * @param array{
     *     quantity?: int,
     *     user_id?: UserId|string,
     *     geographic_region?: string,
     *     check_restrictions?: bool
     * } $options Availability check options
     * 
     * @return array{
     *     available: bool,
     *     quantity_available: int|null,
     *     restrictions: array,
     *     availability_date: Carbon|null,
     *     alternative_products: array
     * } Comprehensive availability information
     */
    public function checkProductAvailability(ProductId|string $productId, array $options = []): array;

    /**
     * Reserve product inventory
     * 
     * @param ProductId|string $productId Product to reserve
     * @param int $quantity Quantity to reserve
     * @param array{
     *     reservation_duration?: int,
     *     user_id?: UserId|string,
     *     order_id?: string,
     *     metadata?: array
     * } $options Reservation configuration
     * 
     * @return array{
     *     reservation_id: string,
     *     reserved_until: Carbon,
     *     quantity_reserved: int,
     *     remaining_inventory: int
     * } Reservation confirmation details
     */
    public function reserveProductInventory(ProductId|string $productId, int $quantity, array $options = []): array;

    /**
     * Release product inventory reservation
     * 
     * @param string $reservationId Reservation to release
     * @param array{
     *     partial_quantity?: int,
     *     reason?: string
     * } $options Release configuration
     * 
     * @return bool True if successfully released
     */
    public function releaseInventoryReservation(string $reservationId, array $options = []): bool;

    // ============================================================================
    // PRICING AND PROMOTIONS
    // ============================================================================

    /**
     * Get product pricing with all applicable discounts
     * 
     * @param ProductId|string $productId Product to price
     * @param array{
     *     currency?: Currency|string,
     *     user_id?: UserId|string,
     *     quantity?: int,
     *     promotional_codes?: array<string>,
     *     geographic_region?: string,
     *     user_tier?: string
     * } $options Pricing calculation options
     * 
     * @return array{
     *     base_price: float,
     *     final_price: float,
     *     currency: string,
     *     discounts: array,
     *     taxes: array,
     *     total_savings: float,
     *     pricing_tier: string
     * } Comprehensive pricing breakdown
     */
    public function getProductPricing(ProductId|string $productId, array $options = []): array;

    /**
     * Apply promotional pricing to products
     * 
     * @param array<ProductId|string> $productIds Products to update
     * @param array{
     *     discount_percentage?: float,
     *     discount_amount?: float,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     user_segments?: array<string>,
     *     geographic_regions?: array<string>,
     *     conditions?: array
     * } $promotion Promotional configuration
     * 
     * @return array{
     *     affected_products: int,
     *     promotion_id: string,
     *     estimated_impact: array
     * } Promotion application results
     */
    public function applyPromotionalPricing(array $productIds, array $promotion): array;

    // ============================================================================
    // ANALYTICS AND PERFORMANCE
    // ============================================================================

    /**
     * Get product performance analytics
     * 
     * @param array{
     *     product_ids?: array<ProductId|string>,
     *     product_types?: array<ProductType|string>,
     *     period?: string,
     *     metrics?: array<string>,
     *     geographic_breakdown?: bool,
     *     user_segment_breakdown?: bool
     * } $options Analytics configuration
     * 
     * @return array{
     *     sales_metrics: array,
     *     engagement_metrics: array,
     *     conversion_metrics: array,
     *     revenue_metrics: array,
     *     trend_analysis: array,
     *     performance_rankings: array
     * } Comprehensive performance analytics
     */
    public function getProductAnalytics(array $options = []): array;

    /**
     * Get product catalog health metrics
     * 
     * @return array{
     *     total_products: int,
     *     active_products: int,
     *     out_of_stock: int,
     *     pricing_issues: int,
     *     category_distribution: array,
     *     performance_summary: array,
     *     recommendations: array
     * } Catalog health and optimization insights
     */
    public function getCatalogHealthMetrics(): array;

    // ============================================================================
    // BULK OPERATIONS
    // ============================================================================

    /**
     * Bulk update product information
     * 
     * @param array<array{
     *     product_id: ProductId|string,
     *     updates: array
     * }> $updates Product updates to apply
     * @param array{
     *     validate_changes?: bool,
     *     skip_conflicts?: bool,
     *     batch_size?: int
     * } $options Bulk update configuration
     * 
     * @return array{
     *     updated: int,
     *     failed: array,
     *     conflicts: array,
     *     validation_errors: array
     * } Bulk update results
     */
    public function bulkUpdateProducts(array $updates, array $options = []): array;

    /**
     * Bulk import products from external sources
     * 
     * @param array $productData Product data to import
     * @param array{
     *     source?: string,
     *     validate_data?: bool,
     *     handle_duplicates?: string,
     *     batch_size?: int,
     *     dry_run?: bool
     * } $options Import configuration
     * 
     * @return array{
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     errors: array,
     *     warnings: array
     * } Import operation results
     */
    public function bulkImportProducts(array $productData, array $options = []): array;

    // ============================================================================
    // CACHE AND PERFORMANCE OPTIMIZATION
    // ============================================================================

    /**
     * Warm product caches for improved performance
     * 
     * @param array{
     *     product_types?: array<ProductType|string>,
     *     popular_products?: bool,
     *     featured_products?: bool,
     *     category_caches?: bool,
     *     pricing_caches?: bool
     * } $options Cache warming configuration
     * 
     * @return array{
     *     caches_warmed: array,
     *     cache_sizes: array,
     *     warming_time: float
     * } Cache warming results
     */
    public function warmProductCaches(array $options = []): array;

    /**
     * Clear product-related caches
     * 
     * @param array{
     *     product_ids?: array<ProductId|string>,
     *     cache_types?: array<string>,
     *     cascade_clear?: bool
     * } $options Cache clearing configuration
     * 
     * @return bool True if caches cleared successfully
     */
    public function clearProductCaches(array $options = []): bool;

    // ============================================================================
    // COMPLIANCE AND GOVERNANCE
    // ============================================================================

    /**
     * Get products requiring compliance review
     * 
     * @param array{
     *     compliance_types?: array<string>,
     *     geographic_regions?: array<string>,
     *     review_status?: array<string>,
     *     urgency_level?: string
     * } $filters Compliance filtering options
     * 
     * @return LengthAwarePaginator Products needing compliance review
     */
    public function getProductsForCompliance(array $filters = []): LengthAwarePaginator;

    /**
     * Update product compliance status
     * 
     * @param ProductId|string $productId Product to update
     * @param array{
     *     compliance_status: string,
     *     reviewer_id?: UserId|string,
     *     review_notes?: string,
     *     compliance_date?: Carbon|string,
     *     restrictions?: array
     * } $complianceData Compliance update information
     * 
     * @return bool True if successfully updated
     */
    public function updateProductCompliance(ProductId|string $productId, array $complianceData): bool;
}