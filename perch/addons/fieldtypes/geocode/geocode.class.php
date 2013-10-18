<?php
require_once 'php-geocoder/autoload.php';

use Geocoder\Geocoder;
use Geocoder\Provider\ChainProvider;
use Geocoder\Exception\ChainNoResultException;

class PerchFieldType_geocode extends PerchAPI_FieldType
{
	/**
	 * Output the form fields for the edit page
	 *
	 * @param array $details
	 * @return string
	 */
	public function render_inputs($details = array())
	{
		// Display an Error Message.
		$error = '';
		if (isset($details['address']['error']) && ! empty($details['address']['error'])) {
			$error = '<p class="hint error-geocode">' . PerchUtil::html($details['address']['error']) . '</p>';
		}

		// Display an iFrame Preview.
		// Parameters: http://moz.com/ugc/everything-you-never-wanted-to-know-about-google-maps-parameters
		$iframe = '';
		if (isset($details['address']['latitude']) && isset($details['address']['longitude'])) {
			$name 		= PerchUtil::html($details['name'], true);
			$latitude   = PerchUtil::html($details['address']['latitude'], true);
			$longitude  = PerchUtil::html($details['address']['longitude'], true);

			$opts = http_build_query(array(
				'f' 		=> 'q',
				'hl'		=> 'en',
				'ie'		=> 'UTF8',
				't'			=> 'm',
				'iwloc'		=> '',
				'q'			=> $latitude . ',' . $longitude . ' (' . $name . ')',
				'output' 	=> 'embed',
			));

			$iframe .= '<iframe class="hint location" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?' . $opts . '"></iframe>';
			$iframe .= '<div class="clear"></div>';
		}

		// Build Text Area.
		$id 	= PerchUtil::html($this->Tag->input_id(), true);
		$value  = PerchUtil::html((isset($details['address']['raw']) ? $details['address']['raw'] : ''), true);
		$class  = PerchUtil::html($this->Tag->class, true);

		return <<< TEXTAREA
		<textarea id="{$id}" name="{$id}" class="text geocode {$class}" rows="6" cols="40">{$value}</textarea>
		<div class="clear"></div>
		{$error}
		{$iframe}
TEXTAREA;
	}

	/**
	 * Read in the form input, prepare data for storage in the database.
	 *
	 * @param string $post
	 * @param object $item
	 * @return string
	 */
	public function get_raw($post = false, $item = false)
	{
		// Get the raw value.
		if (! $post) {
			$post = $_POST;
		}

		// Extract and clean up the value.
		$id 	= $this->Tag->id();
		$raw 	= (isset($post[$id]) ? $post[$id] : '');
		$clean  = preg_replace(array('/\s+/', '/\n+/', '/\v/'), array(' ', ' ', ''), trim($raw));
		$clean  = trim($clean);

		// Determine the adapter.
		$adapter = $this->get_adapter(trim($this->Tag->adapter));
		if (! $adapter) {
			throw new \Exception('Invalid Geocoder HTTP Adapter.');
		}

		// Construct the adapter.
		$adapter = new $adapter;

		// Determine the providers.
		$providers = $this->get_providers(explode(' ', trim($this->Tag->providers)), $adapter);
		if (! $providers) {
			throw new \Exception('Invalid Geocoder Provider(s).');
		}

		// Construct the provider chain.
		$chain = new ChainProvider($providers);

		// Geocode the address.
		$geocoder = new Geocoder;
		$geocoder->registerProvider($chain);
		$geocoder->limit(1);

		try {
			$result = $geocoder->geocode($clean);
		} catch (ChainNoResultException $exception) {
			$result = false;
		}

		// Build Results Array.
		$store = array();
		$store['raw'] = $raw;

		if (! $result) {
			$store['error'] = 'This address could not be geocoded.';
			return $store;
		}

		$store['error'] 	= '';
		$store['latitude']  = $result->getLatitude();
		$store['longitude'] = $result->getLongitude();

		return $store;
	}

	/**
	 * Take the raw data input and return process values for templating
	 *
	 * @param string $raw
	 * @return string
	 */
	public function get_processed($raw = false)
	{
		if (! $raw) {
			$raw = $this->get_raw();
		}

		return $raw;
	}

	/**
	 * Get the value to be used for searching
	 *
	 * @param string $raw
	 * @return string
	 */
	public function get_search_text($raw = false)
	{
		if (! $raw) {
			$raw = $this->get_raw();
		}

		return $raw['raw'];
	}

	/**
	 * Get a version of the content for listing in the admin editing interface.
	 * @param  boolean $raw
	 * @return string
	 */
	public function render_admin_listing($raw = false)
	{
	}

	/**
	 * Add additional CSS or JS to the admin page.
	 * @return string
	 */
	public function add_page_resources()
	{
		$css = <<< CSS
		<style>
			textarea.geocode { 		width: 340px; height: 100px; }
			iframe.location { 		width: 350px; height: 350px; }
			p.error-geocode { 		color: red; }
		</style>

CSS;

		$js = <<< JS
		<script>
			$(document).ready(function() {
				$('#content-edit').on('submit', function(event) {
					var error = false;
					$('textarea.geocode').each(function() {
						if ($(this).val() == '') {
							$(this).parents('div.field').addClass('error');
							error = true;
						}
					});

					if (error) {
						event.preventDefault();
					}
				});
			});
		</script>

JS;

		$siblings = $this->get_sibling_tags();
		if (is_array($siblings)) {
			$perch = Perch::fetch();
			$perch->add_foot_content($css);
			$perch->add_foot_content($js);
		}
	}

	/**
	 * Get an adapter.
	 * @return string
	 */
	private function get_adapter($adapter)
	{
		if (! $adapter || empty($adapter)) {
			$adapter = 'curl';
		}

		$available_adapters = $this->available_adapters();

		if (! isset($available_adapters[$adapter])) {
			return false;
		}

		return $available_adapters[$adapter];
	}

	/**
	 * Get providers.
	 * @return string
	 */
	private function get_providers($providers, $adapter)
	{
		if (! is_array($providers)) {
			$providers = array($providers);
		}

		$providers = array_unique($providers);
		$providers = array_filter($providers);
		if (count($providers) === 0) {
			$providers[] = 'google_maps';
			$providers[] = 'openstreetmap';
			$providers[] = 'map_quest';
		}

		$available_providers = $this->available_providers();

		$chain = array();
		foreach ($providers as $provider) {
			if (! isset($available_providers[$provider])) {
				continue;
			}

			// Store the provider class name.
			$class = $available_providers[$provider];

			// Use reflection to get the constructor parameter names.
			$reflection = new \ReflectionClass($class);
			$method = $reflection->getMethod('__construct');
			$params = $method->getParameters();

			$args = array();
			$args[] = $adapter;

			foreach ($params as $param) {
				if ($param->name === 'adapter') {
					continue;
				}

				// Determine the constant name and put it into the args array.
				// E.g., GOOGLE_MAPS_LOCALE, GOOGLE_MAPS_REGION, GOOGLE_MAPS_USESSL
				$constant = strtoupper($provider . '_' . $param->name);
				if (defined($constant)) {
					$args[] = constant($constant);
				} else {
					$args[] = null;
				}
			}

			// Dispense an provider instance.
			$chain[] = $reflection->newInstanceArgs($args);
		}

		if (count($chain) === 0) {
			return false;
		}

		return $chain;
	}

	/**
	 * Get the available adapters.
	 * @return string
	 */
	private function available_adapters()
	{
		return array(
			'curl' 		=> '\Geocoder\HttpAdapter\CurlHttpAdapter',
			'socket' 	=> '\Geocoder\HttpAdapter\SocketHttpAdapter',
		);
	}

	/**
	 * Get the available providers.
	 * @return string
	 */
	private function available_providers()
	{
		return array(
			'google_maps' 			=> '\Geocoder\Provider\GoogleMapsProvider',
			'google_maps_business' 	=> '\Geocoder\Provider\GoogleMapsBusinessProvider',
			'bing_maps' 			=> '\Geocoder\Provider\BingMapsProvider',
			'openstreetmaps' 		=> '\Geocoder\Provider\OpenStreetMapsProvider',
			'map_quest' 			=> '\Geocoder\Provider\MapQuestProvider',
			'nominatim' 			=> '\Geocoder\Provider\NominatimProvider',
			'geocoder_ca' 			=> '\Geocoder\Provider\GeocoderCaProvider',
			'geocoder_us' 			=> '\Geocoder\Provider\GeocoderUsProvider',
			'ign_openls' 			=> '\Geocoder\Provider\IGNOpenLSProvider',
			'data_science_toolkit'	=> '\Geocoder\Provider\DataScienceToolkitProvider',
			'yandex'				=> '\Geocoder\Provider\YandexProvider',
			'baidu'					=> '\Geocoder\Provider\YandexProviderBaiduProvider',
			'tomtom'				=> '\Geocoder\Provider\TomTomProvider',
		);
	}
}