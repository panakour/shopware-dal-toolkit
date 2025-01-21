<?php

declare(strict_types=1);

namespace Panakour\ShopwareDALToolkit;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Tax\TaxEntity;

final readonly class Dal
{
    /**
     * @template TCollection of EntityCollection
     *
     * @param  EntityRepository<TCollection>  $categoryRepository
     * @param  EntityRepository<TCollection>  $productManufacturerRepository
     * @param  EntityRepository<TCollection>  $taxRepository
     * @param  EntityRepository<TCollection>  $currencyRepository
     * @param  EntityRepository<TCollection>  $propertyGroupRepository
     * @param  EntityRepository<TCollection>  $propertyGroupOptionRepository
     * @param  EntityRepository<TCollection>  $salesChannelRepository
     * @param  EntityRepository<TCollection>  $countryRepository
     * @param  EntityRepository<TCollection>  $paymentMethodRepository
     * @param  EntityRepository<TCollection>  $customerGroupRepository
     * @param  EntityRepository<TCollection>  $salutationRepository
     */
    public function __construct(
        private EntityRepository $categoryRepository,
        private EntityRepository $productManufacturerRepository,
        private EntityRepository $taxRepository,
        private EntityRepository $currencyRepository,
        private EntityRepository $propertyGroupRepository,
        private EntityRepository $propertyGroupOptionRepository,
        private EntityRepository $salesChannelRepository,
        private EntityRepository $countryRepository,
        private EntityRepository $paymentMethodRepository,
        private EntityRepository $customerGroupRepository,
        private EntityRepository $salutationRepository,
        private MediaServiceHelper $mediaService,
    ) {}

    public function getCurrencyByIsoCode(Context $context, string $isoCurrencyCode): ?CurrencyEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('isoCode', $isoCurrencyCode));

        return $this->currencyRepository->search($criteria, $context)->first();
    }

    public function getDefaultTax(Context $context, string $taxName = 'Standard rate'): ?TaxEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $taxName));

        return $this->taxRepository->search($criteria, $context)->first();
    }

    public function createTax(Context $context, string $name, float $taxRate): string
    {
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'name' => $name,
            'taxRate' => $taxRate,
        ];
        $this->taxRepository->create([$taxData], $context);

        return $taxId;
    }

    public function getManufacturerByName(Context $context, string $manufacturerName): ?ProductManufacturerEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $manufacturerName));

        return $this->productManufacturerRepository->search($criteria, $context)->first();
    }

    public function createManufacturer(Context $context, string $manufacturerName, ?string $img = null): string
    {
        $manufacturerId = Uuid::randomHex();
        $manufacturerData = [
            'id' => $manufacturerId,
            'name' => $manufacturerName,
        ];
        if ($img !== null) {
            $media = $this->mediaService->assignMedia($context, [$img]);
            if (isset($media[0]['mediaId'])) {
                $manufacturerData['mediaId'] = $media[0]['mediaId'];
            }
        }
        $this->productManufacturerRepository->create([$manufacturerData], $context);

        return $manufacturerId;
    }

    public function getCategoryByName(Context $context, string $categoryName, ?string $parentId = null): ?CategoryEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $categoryName));
        if ($parentId !== null) {
            $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        }

        return $this->categoryRepository->search($criteria, $context)->first();
    }

    public function createCategory(Context $context, string $categoryName, ?string $parentId = null): string
    {
        $categoryId = Uuid::randomHex();
        $categoryData = [
            'id' => $categoryId,
            'name' => $categoryName,
        ];
        if ($parentId !== null) {
            $categoryData['parentId'] = $parentId;
        }
        $this->categoryRepository->create([$categoryData], $context);

        return $categoryId;
    }

    public function getPropertyGroupByName(Context $context, string $propertyName): ?PropertyGroupEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $propertyName));

        return $this->propertyGroupRepository->search($criteria, $context)->first();
    }

    /**
     * @param  array<array<string, string>>  $translations
     */
    public function createPropertyGroup(Context $context, string $propertyName, bool $filterable = true, int $position = 0, array $translations = []): string
    {
        $propertyGroupId = Uuid::randomHex();
        $propertyData = [
            'id' => $propertyGroupId,
            'name' => $propertyName,
            'description' => $propertyName,
            'filterable' => $filterable,
            'position' => $position,
        ];
        if ($translations !== []) {
            $propertyData['translations'] = $translations;
        }
        $this->propertyGroupRepository->create([$propertyData], $context);

        return $propertyGroupId;
    }

    public function getPropertyOptionByName(Context $context, string $propertyValue, string $propertyGroupId): ?PropertyGroupOptionEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $propertyValue));
        $criteria->addFilter(new EqualsFilter('groupId', $propertyGroupId));

        return $this->propertyGroupOptionRepository->search($criteria, $context)->first();
    }

    /**
     * @param  array<string, string>  $customFields
     * @param  array<array<string, string>>  $translations
     */
    public function createPropertyOption(
        Context $context,
        string $propertyValue,
        string $propertyGroupId,
        string $propertyType,
        array $customFields = [],
        int $position = 0,
        array $translations = []
    ): string {
        $propertyId = Uuid::randomHex();
        $propertyData = [
            'id' => $propertyId,
            'groupId' => $propertyGroupId,
            'name' => $propertyValue,
            'position' => $position,
            'type' => $propertyType,
            'customFields' => $customFields,
        ];
        if ($translations !== []) {
            $propertyData['translations'] = $translations;
        }
        $this->propertyGroupOptionRepository->create([$propertyData], $context);

        return $propertyId;
    }

    public function getSalesChannelByName(Context $context, string $channelName): ?SalesChannelEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $channelName));

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }

    public function getCountryByIsoCode(Context $context, string $isoCode): ?CountryEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('iso', $isoCode));

        return $this->countryRepository->search($criteria, $context)->first();
    }

    public function getCustomerGroup(Context $context, string $customerGroupName = 'Standard customer group'): ?CustomerGroupEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $customerGroupName));

        return $this->customerGroupRepository->search($criteria, $context)->first();
    }

    public function getFirstActivePaymentMethod(Context $context): ?PaymentMethodEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->setLimit(1);

        return $this->paymentMethodRepository->search($criteria, $context)->first();
    }

    public function getSalutation(Context $context, string $salutationKey = 'not_specified'): ?SalutationEntity
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('salutationKey', $salutationKey));

        return $this->salutationRepository->search($criteria, $context)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function getChannelVisibilityAll(Context $context, string $channelName): array
    {
        $salesChannelId = $this->getSalesChannelByName($context, $channelName)?->getId();

        return [
            'salesChannelId' => $salesChannelId,
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ];
    }

    public function firstOrCreateManufacturer(Context $context, string $manufacturerName, ?string $img = null): string
    {
        $manufacturer = $this->getManufacturerByName($context, $manufacturerName);
        if ($manufacturer instanceof \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity) {
            return $manufacturer->getId();
        }

        return $this->createManufacturer($context, $manufacturerName, $img);
    }

    public function firstOrCreateCategory(Context $context, string $categoryName, ?string $parentId = null): string
    {
        $category = $this->getCategoryByName($context, $categoryName, $parentId);
        if ($category instanceof \Shopware\Core\Content\Category\CategoryEntity) {
            return $category->getId();
        }

        return $this->createCategory($context, $categoryName, $parentId);
    }

    /**
     * @param  array<array<string, string>>  $translations
     */
    public function firstOrCreatePropertyGroup(Context $context, string $propertyName, bool $filterable = true, int $position = 0, array $translations = []): string
    {
        $propertyGroup = $this->getPropertyGroupByName($context, $propertyName);
        if ($propertyGroup instanceof \Shopware\Core\Content\Property\PropertyGroupEntity) {
            return $propertyGroup->getId();
        }

        return $this->createPropertyGroup($context, $propertyName, $filterable, $position, $translations);
    }

    /**
     * @param  array<string, string>  $customFields
     * @param  array<array<string, string>>  $translations
     */
    public function firstOrCreatePropertyOption(
        Context $context,
        string $propertyValue,
        string $propertyGroupId,
        string $propertyType,
        array $customFields = [],
        int $position = 0,
        array $translations = []
    ): string {
        $propertyOption = $this->getPropertyOptionByName($context, $propertyValue, $propertyGroupId);
        if ($propertyOption instanceof \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity) {
            return $propertyOption->getId();
        }

        return $this->createPropertyOption($context, $propertyValue, $propertyGroupId, $propertyType, $customFields, $position, $translations);
    }
}
