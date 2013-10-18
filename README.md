Perch Geocode Field Type
========================

A Perch CMS field type for geocoding addresses.

# Requirements

* PHP 5.3+
* Perch CMS

# Installation

1. Copy `perch/addons/fieldtypes/geocode` into your project `$PERCH/addons/fieldtypes` directory.
2. Download [PHP Geocoder](https://github.com/geocoder-php) into `$PERCH/addons/fieldtypes/geocode/php-geocoder`.

# Usage

To use the fieldtype, add into your template as follows:

	```html
	<perch:content id="address" type="geocode" label="Address" adapter="curl" providers="google_maps openstreetmaps map_quest" required="true" />
	```

Once an address has been successfully geocoded, you'll see a Google Map preview:

[screenshot]: https://github.com/ziadoz/perch-fieldtype-geocode/raw/master/screenshot.png "Google Map Preview"

## Adapters

The `adapter` attributes determines which HTTP adapter to use when geocoding. The choices are `curl` or `socket`.

## Providers

The `providers` attributes determines which service(s) to use for geocoding. They should be seperated by a single space, and will be executed in the order specified, stopping once a provider returns a result.

The available options are `google_maps`, `google_maps_business`, `bing_maps`, `openstreetmaps`, `map_quest`, `nominatim`, `geocoder_ca`, `geocoder_us`, `ign_openls`, `data_science_toolkit`, `yandex`, `baidu` and `tomtom`.

### Provider Configuration

Providers are configured using constants. For example, the Google Maps provider accepts a region, locale and a useSSL parameter upon construction. The constant names are determined by the name of the provider (e.g., `google_maps`) and the name of the parameter (e.g., `region`), capitalised and underscored separated.

You should specify constants in your `$PERCH/config/config.php` file:

	```php
	<?php
	// Your existing Perch configuration…

	// Google Maps Provider Configuration.
	define('GOOGLE_MAPS_REGION', '');
	define('GOOGLE_MAPS_LOCALE', '');
	define('GOOGLE_MAPS_USESSL', '');

	// Bind Provider Configuration.
	define('BING_MAPS_APIKEY', '');
	define('BING_MAPS_LOCALE', '');
	```

Check out the [PHP Geocoder](https://github.com/geocoder-php) documentation for more information providers and what parameters their constructors accept.

# Accessing Latitude/Longitude Data

The latitude and longitude values, as well as the address entered by the user and any error messages, are stored in the item's JSON:

	```js
	{
		"address": {
			"raw": '1218 2nd Avenue South, Lethbridge, AB T1J 0E3',
			"latitude": 49.6974029,
			"longitude": -112.8277358,
			"error": ''
		}
	}
	```

You can access this information using Perch's API functionality.