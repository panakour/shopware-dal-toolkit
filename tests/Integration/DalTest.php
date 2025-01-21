<?php

declare(strict_types=1);

namespace Panakour\ShopwareDALToolkit\Tests\Integration;

use Panakour\ShopwareDALToolkit\Dal;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DalTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Dal $dal;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dal = $this->getContainer()->get(Dal::class);
        $this->context = Context::createCLIContext();
    }

    public function test_get_currency_by_iso_code(): void
    {
        $currency = $this->dal->getCurrencyByIsoCode($this->context, 'EUR');
        $this->assertNotNull($currency);
        $this->assertEquals('EUR', $currency->getIsoCode());
    }

    public function test_get_and_create_tax(): void
    {
        $defaultTax = $this->dal->getDefaultTax($this->context);
        $this->assertNotNull($defaultTax);

        $newTaxName = 'Custom Tax '.uniqid();
        $taxRate = 19.0;
        $taxId = $this->dal->createTax($this->context, $newTaxName, $taxRate);
        $this->assertNotNull($taxId);
    }

    public function test_sales_channel_operations(): void
    {
        $repo = $this->getContainer()->get('sales_channel.repository');
        $criteria = new Criteria;
        $criteria->setLimit(1);
        $existing = $repo->search($criteria, $this->context)->first();
        $this->assertNotNull($existing);

        $channelName = $existing->getName();
        $channel = $this->dal->getSalesChannelByName($this->context, $channelName);
        $this->assertNotNull($channel);
        $this->assertEquals($existing->getId(), $channel->getId());

        $visibility = $this->dal->getChannelVisibilityAll($this->context, $channelName);
        $this->assertArrayHasKey('salesChannelId', $visibility);
        $this->assertArrayHasKey('visibility', $visibility);
    }

    public function test_country_and_customer_group_operations(): void
    {
        $country = $this->dal->getCountryByIsoCode($this->context, 'DE');
        $this->assertNotNull($country);

        $customerGroup = $this->dal->getCustomerGroup($this->context);
        $this->assertNotNull($customerGroup);
    }

    public function test_payment_and_salutation(): void
    {
        $payment = $this->dal->getFirstActivePaymentMethod($this->context);
        $this->assertNotNull($payment);
        $this->assertTrue($payment->getActive());

        $salutation = $this->dal->getSalutation($this->context);
        $this->assertNotNull($salutation);
        $this->assertEquals('not_specified', $salutation->getSalutationKey());
    }

    public function test_first_or_create_category(): void
    {
        $catName = 'Test Category '.uniqid();
        $catId = $this->dal->firstOrCreateCategory($this->context, $catName);
        $this->assertNotNull($catId);

        $secondId = $this->dal->firstOrCreateCategory($this->context, $catName);
        $this->assertEquals($catId, $secondId);

        $parentId = $this->dal->createCategory($this->context, 'Parent Category '.uniqid());
        $childName = 'Child Category '.uniqid();
        $childId = $this->dal->firstOrCreateCategory($this->context, $childName, $parentId);
        $this->assertNotNull($childId);

        $sameChildId = $this->dal->firstOrCreateCategory($this->context, $childName, $parentId);
        $this->assertEquals($childId, $sameChildId);
    }

    public function test_first_or_create_manufacturer(): void
    {
        $name = 'Test Manufacturer '.uniqid();
        $id = $this->dal->firstOrCreateManufacturer($this->context, $name);
        $this->assertNotNull($id);

        $secondId = $this->dal->firstOrCreateManufacturer($this->context, $name);
        $this->assertEquals($id, $secondId);

        $imgName = 'Test Manufacturer With Image '.uniqid();
        $imgUrl = 'https://placehold.co/100x120';
        $imgId = $this->dal->firstOrCreateManufacturer($this->context, $imgName, $imgUrl);
        $this->assertNotNull($imgId);
    }

    public function test_first_or_create_property_group(): void
    {
        $propName = 'Test Property '.uniqid();
        $propId = $this->dal->firstOrCreatePropertyGroup($this->context, $propName);
        $this->assertNotNull($propId);

        $secondId = $this->dal->firstOrCreatePropertyGroup($this->context, $propName);
        $this->assertEquals($propId, $secondId);

        $propWithParams = 'Test Property With Params '.uniqid();
        $translations = [
            'en-GB' => ['name' => 'Test Property EN', 'description' => 'Test Description EN'],
            'de-DE' => ['name' => 'Test Property DE', 'description' => 'Test Description DE'],
        ];
        $propWithParamsId = $this->dal->firstOrCreatePropertyGroup(
            $this->context,
            $propWithParams,
            true,
            1,
            $translations
        );
        $this->assertNotNull($propWithParamsId);
    }

    public function test_first_or_create_property_option(): void
    {
        $groupName = 'Test Group '.uniqid();
        $groupId = $this->dal->firstOrCreatePropertyGroup($this->context, $groupName);

        $optionVal = 'Test Option '.uniqid();
        $optionId = $this->dal->firstOrCreatePropertyOption($this->context, $optionVal, $groupId, 'text');
        $this->assertNotNull($optionId);

        $secondId = $this->dal->firstOrCreatePropertyOption($this->context, $optionVal, $groupId, 'text');
        $this->assertEquals($optionId, $secondId);

        $optionWithParams = 'Test Option With Params '.uniqid();
        $translations = ['en-GB' => ['name' => 'Test Option EN'], 'de-DE' => ['name' => 'Test Option DE']];
        $customFields = ['test_field' => 'test_value'];
        $optionWithParamsId = $this->dal->firstOrCreatePropertyOption(
            $this->context,
            $optionWithParams,
            $groupId,
            'text',
            $customFields,
            1,
            $translations
        );
        $this->assertNotNull($optionWithParamsId);
    }
}
