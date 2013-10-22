<?php
require_once 'php-geocoder/autoload.php';

use Geocoder\Geocoder;
use Geocoder\Provider\ChainProvider;
use Geocoder\Exception\ChainNoResultException;

class PerchFieldType_geocode extends PerchAPI_FieldType
{
    /**
     * The field names and placeholders.
     *
     * @var array
     */
    protected static $fields = array(
        'addr1'     => 'Address Line 1',
        'addr2'     => 'Address Line 2',
        'city'      => 'City',
        'state'     => 'State / Province',
        'postcode'  => 'Postal Code',
    );

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
            $name       = PerchUtil::html($details['name'], true);
            $latitude   = PerchUtil::html($details['address']['latitude'], true);
            $longitude  = PerchUtil::html($details['address']['longitude'], true);

            $opts = http_build_query(array(
                'f'         => 'q',
                'hl'        => 'en',
                'ie'        => 'UTF8',
                't'         => 'm',
                'iwloc'     => '',
                'q'         => $latitude . ',' . $longitude . ' (' . $name . ')',
                'output'    => 'embed',
            ));

            $iframe .= '<iframe class="hint location" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?' . $opts . '"></iframe>';
            $iframe .= '<div class="clear"></div>';
        }

        // Build Inputs.
        $id         = PerchUtil::html($this->Tag->input_id(), true);
        $class      = PerchUtil::html($this->Tag->class, true);
        $required   = ($this->Tag->required == 'true' && defined('PERCH_HTML5') && PERCH_HTML5 == true);
        $value      = PerchUtil::html((isset($details['address']['address']) ? $details['address']['address'] : 'noop'), true);

        $input = <<< INPUT
        <input type="text" id="{$id}" name="{$id}" class="text" style="display: none;" value="{$value}" />
INPUT;

        foreach (self::$fields as $field => $placeholder) {
            $validate   = ($required && $field != 'addr2' ? 'required' : '');
            $value      = PerchUtil::html((isset($details['address'][$field]) ? $details['address'][$field] : ''), true);

            $input .= <<< INPUT
            <input type="text" id="{$id}_{$field}" name="{$id}_{$field}" class="text geocode {$class}" placeholder="{$placeholder}" value="{$value}" {$validate} />
INPUT;

        }

        $input .= $error;
        $input .= $iframe;

        return $input;
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

        // Extract and clean up the values.
        $id  = $this->Tag->id();
        $raw = (isset($post[$id]) ? $post[$id] : '');

        $address = array();
        foreach (self::$fields as $field => $placeholder) {
            $key = 'address' . '_' . $field;
            $address[$field] = (isset($post[$key]) ? trim($post[$key]) : '');
        }

        $clean = implode("\n", array_filter($address));

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
        $store['address'] = $clean;

        foreach ($address as $field => $value) {
            $store[$field] = $value;
        }

        if (! $result) {
            $store['error'] = 'This address could not be geocoded.';
            return $store;
        }

        $store['error']     = '';
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

        return $this->process_address($raw);
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

        return $this->process_address($raw);
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
            input.geocode {         width: 280px; display: block; margin-bottom: 10px; margin-left: 230px; }
            iframe.location {       width: 290px; height: 290px; }
            p.error-geocode {       color: red; }

            @media screen and (max-width: 1060px) {
                form input.geocode,
                form iframe.location {
                    margin-left: 0;
                }
            }
        </style>

CSS;

        $siblings = $this->get_sibling_tags();
        if (is_array($siblings)) {
            $perch = Perch::fetch();
            $perch->add_foot_content($css);
        }
    }

    /**
     * Process the address from an array into a newline separated string.
     *
     * @param array
     * @return string
     **/
    private function process_address($raw)
    {
        if (! is_array($raw)) {
            $raw = array($raw);
        }

        $address = array();
        foreach (self::$fields as $field => $placeholder) {
            $address[$field] = (isset($raw[$field]) ? $raw[$field] : '');
        }

        return implode("\n", array_filter($address));
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
            'curl'      => '\Geocoder\HttpAdapter\CurlHttpAdapter',
            'socket'    => '\Geocoder\HttpAdapter\SocketHttpAdapter',
        );
    }

    /**
     * Get the available providers.
     * @return string
     */
    private function available_providers()
    {
        return array(
            'google_maps'           => '\Geocoder\Provider\GoogleMapsProvider',
            'google_maps_business'  => '\Geocoder\Provider\GoogleMapsBusinessProvider',
            'bing_maps'             => '\Geocoder\Provider\BingMapsProvider',
            'openstreetmaps'        => '\Geocoder\Provider\OpenStreetMapsProvider',
            'map_quest'             => '\Geocoder\Provider\MapQuestProvider',
            'nominatim'             => '\Geocoder\Provider\NominatimProvider',
            'geocoder_ca'           => '\Geocoder\Provider\GeocoderCaProvider',
            'geocoder_us'           => '\Geocoder\Provider\GeocoderUsProvider',
            'ign_openls'            => '\Geocoder\Provider\IGNOpenLSProvider',
            'data_science_toolkit'  => '\Geocoder\Provider\DataScienceToolkitProvider',
            'yandex'                => '\Geocoder\Provider\YandexProvider',
            'baidu'                 => '\Geocoder\Provider\YandexProviderBaiduProvider',
            'tomtom'                => '\Geocoder\Provider\TomTomProvider',
        );
    }
}
