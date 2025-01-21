# <h1 align="center">Shopware Data Abstraction Layer Toolkit</h1>

<p align="center">
    <strong>A fluent and type-safe toolkit for working with Shopware's Data Abstraction Layer (DAL)</strong>
</p>

<p align="center">
    <a href="https://github.com/panakour/shopware-dal-toolkit/actions"><img alt="GitHub Workflow Status (master)" src="https://github.com/panakour/shopware-dal-toolkit/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/panakour/shopware-dal-toolkit"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/panakour/shopware-dal-toolkit"></a>
    <a href="https://packagist.org/packages/panakour/shopware-dal-toolkit"><img alt="Latest Version" src="https://img.shields.io/packagist/v/panakour/shopware-dal-toolkit"></a>
    <a href="https://packagist.org/packages/panakour/shopware-dal-toolkit"><img alt="License" src="https://img.shields.io/packagist/l/panakour/shopware-dal-toolkit"></a>
    <a href="./coverage-badge.svg"><img alt="Code Coverage Badge" src="./coverage-badge.svg"></a>
</p>

------

## About

Shopware DAL Toolkit provides a clean, type-safe abstraction layer for working with Shopware Data Abstraction Layer
using PHP. It simplifies common operations like entity management and media handling while maintaining strong typing and
best practices.

Very useful for programmatically integrating third party systems e.g synchronize with ERP or any other system.

Key Features:

- Type-safe entity operations with proper generic types
- Simplified media handling for both URLs and base64 images
- First-or-create pattern for common entities
- Comprehensive test coverage

## Installation

Install this package as a dependency using [Composer](https://getcomposer.org).

```bash
composer require panakour/shopware-dal-toolkit
```

## Usage

### Basic Entity Operations

```php
use Panakour\ShopwareDALToolkit\Dal;

// Create or retrieve the Dal service from the container
$dal = $container->get(Dal::class);
$currency = $dal->getCurrencyByIsoCode($context, 'EUR');

// Create a tax
$taxName = 'Custom Tax 19%';
$taxRate = 19.0;
$dal->createTax($context, $taxName, $taxRate);

// Create or retrieve a category
$categoryName = 'My Custom Category';
$categoryId = $dal->firstOrCreateCategory($context, $categoryName);
echo $categoryId; // newly generated or existing ID

// Create or retrieve a manufacturer
$manufacturerName = 'My Manufacturer';
$manufacturerId = $dal->firstOrCreateManufacturer($context, $manufacturerName);
echo $manufacturerId; // newly generated or existing ID
```

### Media Handling

```php
use Panakour\ShopwareDALToolkit\MediaServiceHelper;

// Retrieve the MediaServiceHelper from the container
$mediaHelper = $container->get(MediaServiceHelper::class);

// Assign media from a list of URLs and/or base64-encoded images
$images = [
    'https://placehold.co/100x110',
    'https://placehold.co/300x300',
    'data:image/png;base64,' . base64_encode($myRawPngContents),
];
$results = $mediaHelper->assignMedia($context, $images);

// Each item in $results has a 'mediaId' key
foreach ($results as $ref) {
    echo $ref['mediaId']; // Newly created media ID
}

```
### More usage:
- View the [Integration tests](tests/Integration)
- Look directly to the classes [Dal.php](src/Dal.php) and [MediaServiceHelper.php](src/MediaServiceHelper.php)

## Requirements

- PHP 8.3+
- Shopware 6.6+

🧹 Keep a modern codebase with **Pint**:

```bash
composer lint
```

✅ Run refactors using **Rector**

```bash
composer refactor
```

⚗️ Run static analysis using **PHPStan**:

```bash
composer test:types
```

✅ Run unit tests using **PHPUnit**

```bash
composer test:unit
```

🚀 Run the entire test suite:

```bash
composer test
```

## Copyright and License

panakour/shopware-dal-toolkit is copyright © [Panagiotis Koursaris](mailto:panakourweb@gmail.com)
and licensed for use under the terms of the
MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
