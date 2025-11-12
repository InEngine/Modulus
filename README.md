# A Module System for InEngine

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inengine/modulus.svg?style=flat-square)](https://packagist.org/packages/inengine/modulus)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/inengine/modulus/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/inengine/modulus/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/inengine/modulus/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/inengine/modulus/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/inengine/modulus.svg?style=flat-square)](https://packagist.org/packages/inengine/modulus)
[![CC BY-NC-SA 4.0](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-lightgrey.svg)](http://creativecommons.org/licenses/by-nc-sa/4.0/)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/Modulus.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/Modulus)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require inengine/modulus
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="modulus-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="modulus-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="modulus-views"
```

## Usage

```php
$modulus = new InEngine\Modulus();
echo $modulus->echoPhrase('Hello, InEngine!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [James Johnson](https://github.com/excellentingenuity)
- [All Contributors](../../contributors)

## License

CC BY-NC-SA 4.0. Please see the [License File](LICENSE.txt) for more information.
