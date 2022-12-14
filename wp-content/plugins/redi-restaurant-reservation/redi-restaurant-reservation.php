<?php
/*
	Plugin Name: ReDi Restaurant Reservation
	Plugin URI: http://reservationdiary.eu
	Description: ReDi Reservation plugin for Restaurants
	Version: 22.1018
	Author: Reservation Diary
	Author URI: http://reservationdiary.eu
	Text Domain: redi-restaurant-reservation
	Domain Path: /lang
 */
if (!defined('REDI_RESTAURANT_PLUGIN_URL')) {
    define('REDI_RESTAURANT_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('REDI_RESTAURANT_TEMPLATE')) {
    define('REDI_RESTAURANT_TEMPLATE', plugin_dir_path(__FILE__) . 'templates' . DIRECTORY_SEPARATOR);
}

require_once('redi.php');
require_once('redi-restaurant-reservation-db.php');
require_once('redi-restaurant-reservation-date-format.php');

if (!class_exists('ReDiRestaurantReservation')) {
    if (!class_exists('Report')) {
        class Report
        {
            const Full = 'Full';
            const None = 'None';
            const Single = 'Single';
        }
    }
    if (!class_exists('ReDiSendEmailFromOptions')) {
        class ReDiSendEmailFromOptions
        {
            const ReDi = 'ReDi';
            const CustomSMTP = 'CustomSMTP';
            const WordPress = 'WordPress';
            const Disabled = 'Disabled';
        }
    }
    if (!class_exists('EmailContentType')) {
        class EmailContentType
        {
            const Canceled = 'Canceled';
            const Confirmed = 'Confirmed';
        }
    }
    if (!class_exists('AlternativeTime')) {
        class AlternativeTime
        {
            const AlternativeTimeBlocks = 1;
            const AlternativeTimeByShiftStartTime = 2;
            const AlternativeTimeByDay = 3;
        }
    }
    if (!class_exists('CustomFieldsSaveTo')) {
        class CustomFieldsSaveTo
        {
            const WPOptions = 'options';
            const API = 'api';
        }
    }

    class ReDiRestaurantReservation
    {

        public $version = '22.1018';
        /**
         * @var string The options string name for this plugin
         */
        private $optionsName = 'wp_redi_restaurant_options';
        private $apiKeyOptionName = 'wp_redi_restaurant_options_ApiKey';
        private static $name = 'REDI_RESTAURANT';
        private $options = array();
        private $ApiKey;
        private $redi;
        private $emailContent;
        private $weekday = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        private $table_name = 'wp_redi_restaurant_reservation_v6';

        public function error_handler($errno, $errstr, $errfile, $errline)
        {
            if (in_array('redi-restaurant-reservation', explode(DIRECTORY_SEPARATOR, $errfile))) {
                $this->display_errors(array('Error' => array($errline . ':' . $errstr)), false);
            }
        }

        public function exception_handler($exception)
        {
            if (in_array('redi-restaurant-reservation', explode(DIRECTORY_SEPARATOR, $exception->getFile()))) {
                $this->display_errors(array(
                    'Error' => $exception->getMessage()
                ), false);
            }
        }

        function filter_timeout_time()
        {
            return 60; //new number of seconds default 5
        }

        function __destruct()
        {
            restore_error_handler();
            restore_exception_handler();
        }

        public function __construct()
        {
            $this->_name = self::$name;
            set_exception_handler(array($this, 'exception_handler'));
            set_error_handler(array($this, 'error_handler'));
            
            //Initialize the options
            $this->get_options();

            $this->ApiKey = isset($this->options['ID']) ? $this->options['ID'] : null;

            $this->redi = new Redi($this->ApiKey);
            //Actions
            add_action('init', array($this, 'init_sessions'));
            add_action('admin_menu', array($this, 'redi_restaurant_admin_menu_link_new'));
            add_action('admin_menu', array($this, 'remove_admin_submenu_items'));

            $this->page_title = 'Reservation';
            $this->content = '[redirestaurant]';
            $this->page_name = $this->_name;
            $this->page_id = '0';

            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            register_uninstall_hook(__FILE__, 'uninstall'); // static

            add_action('wp_ajax_nopriv_redi_restaurant-submit', array($this, 'redi_restaurant_ajax'));
            add_action('wp_ajax_redi_restaurant-submit', array($this, 'redi_restaurant_ajax'));

            add_action('wp_ajax_nopriv_redi_waitlist-submit', array($this, 'redi_restaurant_ajax'));
            add_action('wp_ajax_redi_waitlist-submit', array($this, 'redi_restaurant_ajax'));

            add_filter('http_request_timeout', array($this, 'filter_timeout_time'));
            add_action('http_api_curl', array($this, 'my_http_api_curl'), 100, 1);
            add_filter('http_request_args', array($this, 'my_http_request_args'), 100, 1);
            add_shortcode('redirestaurant', array($this, 'shortcode'));

            add_action('redi-reservation-send-confirmation-email', array($this, 'send_confirmation_email'));
            add_action('redi-reservation-email-content', array($this, 'redi_reservation_email_content'));
            add_action('redi-reservation-send-confirmation-email-other', array($this, 'send_confirmation_email'));
            do_action('redi-reservation-after-init');

            add_filter('redi-reservation-discount', array($this, 'get_discounts'), 100, 1);

            ReDiRestaurantReservationDb::CreateCustomDatabase($this->table_name);
        }

        function get_discounts($time)
        {
            return null;
        }

        function my_http_request_args($r)
        {
            $r['timeout'] = 60; # new timeout
            return $r;
        }

        function my_http_api_curl($handle)
        {
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 60); # new timeout
            curl_setopt($handle, CURLOPT_TIMEOUT, 60); # new timeout
        }

        function redi_reservation_email_content($args)
        {
            $this->emailContent = $this->redi->getEmailContent(
                $args['id'],
                EmailContentType::Confirmed,
                array(
                    'Lang' => $args['lang']
                )
            );
        }

        function send_confirmation_email()
        {
            if (!isset($this->emailContent['Error'])) {
                wp_mail($this->emailContent['To'], $this->emailContent['Subject'], $this->emailContent['Body'],
                    array(
                        'Content-Type: text/html; charset=UTF-8',
                        'From: ' . wp_specialchars_decode(get_option('blogname'), ENT_QUOTES) . ' <' . get_option('admin_email') . '>' . "\r\n"
                    ));
            }
        }

        function language_files($mofile, $domain)
        {
            if ($domain === 'redi-restaurant-reservation') {

                $full_file = plugin_dir_path( __FILE__ ). 'lang/' . $domain . '-' . get_locale() . '.mo';   
                $generic_file = plugin_dir_path( __FILE__ ) . 'lang/' . $domain . '-' . substr(get_locale(),
                        0, 2) . '.mo';
                if (file_exists($full_file)) {
                    return $full_file;
                }
                if (file_exists($generic_file)) {
                    return $generic_file;
                }
            }

            return $mofile;
        }

        function ReDiRestaurantReservation()
        {
            $this->__construct();
        }

        function plugin_get_version()
        {
            $plugin_data = get_plugin_data(__FILE__);
            $plugin_version = $plugin_data['Version'];

            return $plugin_version;
        }

        /**
         * Retrieves the plugin options from the database.
         * @return array
         */
        function get_options()
        {
            if (!$options = get_option($this->optionsName)) {
                update_option($this->optionsName, $options);
            }
            $this->options = $options;
        }

        private function register($email)
        {
            $new_account = $this->redi->createUser(array('Email' => $email, 'Source' => 'WordPress'));

            $name = get_bloginfo('name');//get from site name;

            if (empty($name)) {
                $name = "Restaurant name";
            }

            if (isset($new_account['ID']) && !empty($new_account['ID'])) {
                $this->ApiKey = $this->options['ID'] = $new_account['ID'];
                $this->redi->setApiKey($this->options['ID']);
                $place = $this->redi->createPlace(array(
                    'place' => array(
                        'Name' => $name,
                        'City' => 'city',
                        'Country' => 'country',
                        'Address' => 'Address line 1',
                        'Email' => $email,
                        'EmailCC' => '',
                        'Phone' => '[areacode] [number]',
                        'WebAddress' => get_option('siteurl'),
                        'Lang' => self::lang(),
						'ReservationDuration' => 30, // min
                        'MinTimeBeforeReservation' => 24 // hour
                    )
                ));

                if (isset($place['Error'])) {
                    return $place;
                }

                $placeID = (int)$place['ID'];

                $category = $this->redi->createCategory($placeID,
                    array('category' => array('Name' => 'Restaurant')));

                if (isset($category['Error'])) {
                    return $category;
                }

                $categoryID = (int)$category['ID'];
                $service = $this->redi->createService($categoryID,
                    array('service' => array('Name' => 'Person', 'Quantity' => 10)));

                if (isset($service['Error'])) {
                    return $service;
                }

                foreach ($this->weekday as $value) {
                    $times[$value] = array('OpenTime' => '12:00', 'CloseTime' => '00:00');
                }
                $this->redi->setServiceTime($categoryID, $times);

                $this->saveAdminOptions();
            }

            return $new_account;
        }

        /**
         * Saves the admin options to the database.
         */
        function saveAdminOptions()
        {
            return update_option($this->optionsName, $this->options);
        }

        function display_errors($errors, $admin = false, $action = '')
        {
            if (isset($errors['Error']) && is_array($errors)) {
                foreach ((array)$errors['Error'] as $error) {
                    echo '<div class="error redi-reservation-alert-error redi-reservation-alert"><p>' . $error . '</p></div>';
                }
            }
            //WP-errors
            if (isset($errors['Wp-Error'])) {

                foreach ((array)$errors['Wp-Error'] as $error_key => $error) {
                    foreach ((array)$error as $err) {
                        if ($admin) {
                            echo '<div class="error"><p>' . $error_key . ' : ' . $err . '</p></div>';
                        }
                    }
                }
            }
            if (isset($errors['updated'])){
                foreach ((array)$errors['updated'] as $error) {
                    echo ' <div class="updated notice"><p>' . $error . '</p></div>';
                }
            }
        }

        function redi_restaurant_admin_upcoming()
        {
            ?><script>window.location.assign("<?php echo $this->redi->getWaiterDashboardUrl(self::lang()) ?>");</script><?php
        }

        function redi_restaurant_basic_package_settings()
        {
            ?><script>window.location.assign("<?php echo $this->redi->getBasicPackageSettingsUrl(self::lang()) ?>");</script><?php
        }

        function redi_restaurant_admin_test()
        {
            ?><script>window.location.assign("<?php echo get_option('siteurl') . '/reservation' ?>");</script><?php
        }

        function redi_restaurant_admin_reservations()
        {
            ?><script>window.location.assign("<?php echo $this->redi->getReservationUrl(self::lang())?>");</script><?php
        }

        function redi_restaurant_admin_welcome()
		{	
			require_once(REDI_RESTAURANT_TEMPLATE . 'admin_welcome.php');
        }
        
        function admin_welcome_no_key()
		{	
			require_once(REDI_RESTAURANT_TEMPLATE . 'admin_welcome_no_key.php');
		}

        /**
         * Adds settings/options page
         */

        function redi_restaurant_admin_options_page()
        {
            if (isset($_POST['new_key']))
            {
                $newKey = sanitize_text_field($_POST['new_key']);
                $errors = array();
                
                if (!empty($newKey)) 
                {
                    if (preg_match("/^(\{)?[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}(?(1)\})$/i", $newKey) == 1) {
                        if ($this->ApiKey != $newKey) {
                            $this->redi->setApiKey($newKey);
                            $this->ApiKey = $this->options['ID'] = $newKey;
                            $this->saveAdminOptions();
                            $error['updated'] = __('API key is successfully changed.', 'redi-restaurant-reservation');
                            $this->display_errors($error, true, 'ApiKey update');
                        }
                    } else {

                        $error['Error'] = __('Not a valid API key provided.', 'redi-restaurant-reservation');
                        $this->display_errors($error, true, 'No ApiKey');
                    }
                }
            }

            if ($this->ApiKey == null) {

                $errors['Error'] = array(
                    __('ReDi Restaurant Reservation plugin could not get an API key from the reservationdiary.eu server when it activated.' .
                        '<br/> You can try to fix this by going to the ReDi Restaurant Reservation "options" page. ' .
                        '<br/>This will cause ReDi Restaurant Reservation plugin to retry fetching an API key for you. ' .
                        '<br/>If you keep seeing this error it usually means that server where you host your web site can\'t connect to our reservationdiary.eu server. ' .
                        '<br/>You can try asking your WordPress host to allow your WordPress server to connect to api.reservationdiary.eu' .
                        '<br/>In case you can not solve this problem yourself, please contact us directly by <a href="mailto:info@reservationdiary.eu">info@reservationdiary.eu</a>',
                        'redi-restaurant-reservation')
                );
                $this->display_errors($errors, true, 'Failed to register');
                die;
            }
            $places = $this->redi->getPlaces();

            if (isset($places['Error'])) {
                $this->display_errors($places, true, 'getPlaces');
                die;
            }
            $placeID = $places[0]->ID;

            $categories = $this->redi->getPlaceCategories($placeID);

            $categoryID = $categories[0]->ID;

            if (isset($_POST['action']) && $_POST['action'] == 'cancel') {
                if (isset($_POST['id'])) {
                    $params = array(
                        'ID' => urlencode(self::GetPost('id')),
                        'Lang' => self::lang(),
                        'Reason' => urlencode(mb_substr(self::GetPost('Reason'), 0, 250)),
                        'CurrentTime' => urlencode(date('Y-m-d H:i', current_time('timestamp'))),
                        'Version' => urlencode(self::plugin_get_version())
                    );

                    if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::Disabled ||
                        isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress
                    ) {
                        $params['DontNotifyClient'] = 'true';
                    }
                    $cancel = $this->redi->cancelReservation($params);
                    if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress && !isset($cancel['Error'])) {
                        //call api for content
                        $emailContent = $this->redi->getEmailContent(
                            (int)$cancel['ID'],
                            EmailContentType::Canceled,
                            array(
                                'Lang' => str_replace('_', '-', self::GetPost('lang'))
                            )
                        );

                        //send
                        if (!isset($emailContent['Error'])) {
                            wp_mail($emailContent['To'], $emailContent['Subject'], $emailContent['Body'], array(
                                'Content-Type: text/html; charset=UTF-8',
                                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>' . "\r\n"
                            ));
                        }
                    }
                    if (isset($cancel['Error'])) {
                        $errors[] = $cancel['Error'];
                    } else {
                        $cancel_success = __('Reservation has been successfully canceled.',
                            'redi-restaurant-reservation');
                    }

                } else {
                    $errors[] = __('Reservation number is required', 'redi-restaurant-reservation');
                }
            }
            $settings_saved = false;
            if (isset($_POST['submit'])) {
                $form_valid = true;
                $services = (int)self::GetPost('services');
                $minPersons = (int)self::GetPost('MinPersons');
                $maxPersons = (int)self::GetPost('MaxPersons');
                $largeGroupsMessage = self::GetPost('LargeGroupsMessage');
                $emailFrom = self::GetPost('EmailFrom');
                $report = self::GetPost('Report', Report::Full);
                $thanks = self::GetPost('Thanks', 0);
                $timepicker = self::GetPost('TimePicker');
                $alternativeTimeStep = self::GetPost('AlternativeTimeStep', 30);
                $MinTimeBeforeReservation = self::GetPost('MinTimeBeforeReservation');
                $MinTimeBeforeReservationType = self::GetPost('MinTimeBeforeReservationType');
                $waitlist = self::GetPost('WaitList');
                $confirmationPage = self::GetPost('ConfirmationPage');
                $dateFormat = self::GetPost('DateFormat');
                $calendar = self::GetPost('Calendar');
                $hidesteps = self::GetPost('Hidesteps');
                $enablefirstlastname = self::GetPost('EnableFirstLastName');
                $endreservationtime = self::GetPost('EndReservationTime');
                $countrycode = self::GetPost('CountryCode', false);
                $timeshiftmode = self::GetPost('TimeShiftMode');
                $manualReservation = self::GetPost('ManualReservation', 0);				
				$displayLeftSeats = self::GetPost('DisplayLeftSeats', 0);
				$EnableCancelForm = self::GetPost('EnableCancelForm', 0);
                $EnableModifyReservations = self::GetPost('EnableModifyReservations', 0);
                $EnableSocialLogin = self::GetPost('EnableSocialLogin', 0);
				$fullyBookedMessage = self::GetPost('FullyBookedMessage');
				$captcha = self::GetPost('Captcha', 0);
				$childrenSelection = self::GetPost('ChildrenSelection', 0);
				$childrenDescription = self::GetPost('ChildrenDescription');
				$captchaKey = self::GetPost('CaptchaKey');
                //validation
                if ($minPersons > $maxPersons) {
                    $errors[] = __('Min Persons should be lower than Max Persons', 'redi-restaurant-reservation');
                    $form_valid = false;
                }

                $reservationTime = (int)self::GetPost('ReservationTime');
                if ($reservationTime <= 0) {
                    $errors[] = __('Reservation time should be greater than 0', 'redi-restaurant-reservation');
                    $form_valid = false;
                }
                $place = array(
                    'place' => array(
                        'Name' => self::GetPost('Name'),
                        'City' => self::GetPost('City'),
                        'Country' => self::GetPost('Country'),
                        'Address' => self::GetPost('Address'),
                        'Email' => self::GetPost('Email'),
                        'EmailCC' => self::GetPost('EmailCC'),
                        'Phone' => self::GetPost('Phone'),
                        'WebAddress' => self::GetPost('WebAddress'),
                        'Lang' => self::GetPost('Lang'),
                        'DescriptionShort' => self::GetPost('DescriptionShort'),
                        'DescriptionFull' => self::GetPost('DescriptionFull'),
                        'MinTimeBeforeReservation' => self::GetPost('MinTimeBeforeReservation'),
                        'MinTimeBeforeReservationType' => self::GetPost('MinTimeBeforeReservationType'),
                        'Catalog' => (int)self::GetPost('Catalog'),
                        'DateFormat' => self::GetPost('DateFormat'),
                        'MaxTimeBeforeReservation' => self::GetPost('MaxTime'),
                        'MaxTimeBeforeReservationType' => self::GetPost('MaxTimeBeforeReservationType'),
                        'ReservationDuration' => $reservationTime,
                        'Version' => $this->version
                    )
                );

                if (empty($place['place']['Country'])) {
                    $errors[] = __('Country is required', 'redi-restaurant-reservation');
                    $form_valid = false;
                }

                $placeID = self::GetPost('Place');
				
                for ($i = 0; $i != REDI_MAX_CUSTOM_FIELDS; $i++) {
                    $field_id = 'field_' . $i . '_id';
                    $field_name = 'field_' . $i . '_name';
                    $field_text = 'field_' . $i . '_text';
                    $field_values = 'field_' . $i . '_values';
                    $field_type = 'field_' . $i . '_type';
                    $field_required = 'field_' . $i . '_required';
                    $field_print = 'field_' . $i . '_print';
                    $field_message = 'field_' . $i . '_message';

                    $$field_id = self::GetPost($field_id);

                    $$field_name = self::GetPost($field_name);
                    $$field_text = htmlentities(self::GetPost($field_text), ENT_QUOTES);

                    $$field_type = self::GetPost($field_type);
                    $$field_print = (self::GetPost($field_print) === 'on');
                    $$field_required = (self::GetPost($field_required) === 'on');
                    $$field_values = self::GetPost($field_values);

                    $$field_message = self::GetPost($field_message);

                    if (empty($$field_name) && isset($$field_id) && $$field_id > 0) { //name is empty so delete this field
                        $this->redi->deleteCustomField(self::lang(), $placeID, $$field_id);
                    } else {
                        //new or update
                        if (isset($$field_id) && $$field_id > 0) {
                            $this->redi->updateCustomField(self::lang(), $placeID, $$field_id, array(
                                'customfield' => array(
                                    'Name' => $$field_name,
                                    'Text' => $$field_text,
                                    'Values' => $$field_values,
                                    'Message' => $$field_message,
                                    'Required' => $$field_required ? 'true' : 'false',
                                    'Print' => $$field_print ? 'true' : 'false',
                                    'Type' => $$field_type
                                )
                            ));
                        } else {
                            $this->redi->saveCustomField(self::lang(), $placeID, array(
                                'customfield' => array(
                                    'Name' => $$field_name,
                                    'Text' => $$field_text,
                                    'Values' => $$field_values,
                                    'Message' => $$field_message,
                                    'Required' => $$field_required ? 'true' : 'false',
                                    'Print' => $$field_print ? 'true' : 'false',
                                    'Type' => $$field_type
                                )
                            ));
                        }
                    }
                }

                if ($form_valid) {
                    $settings_saved = true;
                    $serviceTimes = self::GetServiceTimes();
                    $this->options['WaitList'] = $waitlist;
                    $this->options['Thanks'] = $thanks;
                    $this->options['TimePicker'] = $timepicker;
                    $this->options['AlternativeTimeStep'] = $alternativeTimeStep;
                    $this->options['ConfirmationPage'] = $confirmationPage;
                    $this->options['services'] = $services;
                    $this->options['MinTimeBeforeReservation'] = $MinTimeBeforeReservation;
                    $this->options['MinTimeBeforeReservationType'] = $MinTimeBeforeReservationType;
                    $this->options['DateFormat'] = $dateFormat;
                    $this->options['Hidesteps'] = $hidesteps;
                    $this->options['EnableFirstLastName'] = $enablefirstlastname;
                    $this->options['EndReservationTime'] = $endreservationtime;
                    $this->options['CountryCode'] = $countrycode;
                    $this->options['MinPersons'] = $minPersons;
                    $this->options['MaxPersons'] = $maxPersons;
                    $this->options['LargeGroupsMessage'] = $largeGroupsMessage;
                    $this->options['EmailFrom'] = $emailFrom;
                    $this->options['Report'] = $report;
                    $this->options['Calendar'] = $calendar;
                    $this->options['TimeShiftMode'] = $timeshiftmode;
					$this->options['ManualReservation'] = $manualReservation;
					$this->options['DisplayLeftSeats'] = $displayLeftSeats;
                    $this->options['EnableCancelForm'] = $EnableCancelForm;
                    $this->options['EnableModifyReservations'] = $EnableModifyReservations;
                    $this->options['EnableSocialLogin'] = $EnableSocialLogin;                   
                    $this->options['FullyBookedMessage'] = $fullyBookedMessage;
					$this->options['Captcha'] = $captcha;
					$this->options['ChildrenSelection'] = $childrenSelection;
					$this->options['ChildrenDescription'] = $childrenDescription;
					$this->options['CaptchaKey'] = $captchaKey;

                    $placeID = self::GetPost('Place');
                    $categories = $this->redi->getPlaceCategories($placeID);
                    if (isset($categories['Error'])) {
                        $errors[] = $categories['Error'];
                        $settings_saved = false;
                    }
                    $categoryID = $categories[0]->ID;
                    $this->options['OpenTime'] = self::GetPost('OpenTime');
                    $this->options['CloseTime'] = self::GetPost('CloseTime');

                    $getServices = $this->redi->getServices($categoryID);
                    if (isset($getServices['Error'])) {
                        $errors[] = $getServices['Error'];
                        $settings_saved = false;
                    }
                    if (count($getServices) != $services) {
                        if (count($getServices) > $services) {
                            //delete
                            $diff = count($getServices) - $services;

                            $cancel = $this->redi->deleteServices($categoryID, $diff);
                            if (isset($cancel['Error'])) {
                                $errors[] = $cancel['Error'];
                                $settings_saved = false;
                            }
                            $cancel = array();
                        } else {
                            //add
                            $diff = $services - count($getServices);

                            $cancel = $this->redi->createService($categoryID,
                                array(
                                    'service' => array(
                                        'Name' => 'Person',
                                        'Quantity' => $diff
                                    )
                                ));
                            if (isset($cancel['Error'])) {
                                $errors[] = $cancel['Error'];
                                $settings_saved = false;
                            }
                            $cancel = array();
                        }
                    }

                    $this->saveAdminOptions();

                    if (is_array($serviceTimes) && count($serviceTimes)) {
                        $cancel = $this->redi->setServiceTime($categoryID, $serviceTimes);
                        if (isset($cancel['Error'])) {
                            $errors[] = $cancel['Error'];
                            $settings_saved = false;
                        }
                        $cancel = array();
                    }
                    $cancel = $this->redi->setPlace($placeID, $place);
                    if (isset($cancel['Error'])) {
                        $errors[] = $cancel['Error'];
                        $settings_saved = false;
                    }
                    $cancel = array();
                }

                $places = $this->redi->getPlaces();
                if (isset($places['Error'])) {
                    $errors[] = $places['Error'];
                    $settings_saved = false;
                }
            }

            $this->options = get_option($this->optionsName);

            if ($settings_saved || !isset($_POST['submit'])) {
                $thanks = $this->GetOption('Thanks', 0);
                $calendar = $this->GetOption('Calendar', 'hide');
                $hidesteps = $this->GetOption('Hidesteps', 0);
                $enablefirstlastname = $this->GetOption('EnableFirstLastName', 'false');
                $endreservationtime = $this->GetOption('EndReservationTime', 'false');
                $countrycode = $this->GetOption('CountryCode', 'true');
                $timeshiftmode = $this->GetOption('TimeShiftMode', 'byshifts');
                $timepicker = $this->GetOption('TimePicker');
                $confirmationPage = $this->GetOption('ConfirmationPage');
				$manualReservation = $this->GetOption('ManualReservation', 0);
				$displayLeftSeats = $this->GetOption( 'DisplayLeftSeats', 0);
                $EnableCancelForm = $this->GetOption('EnableCancelForm', 0);
                $EnableModifyReservations = $this->GetOption('EnableModifyReservations', 0);
                $EnableSocialLogin = $this->GetOption('EnableSocialLogin', 0);
                $fullyBookedMessage = $this->GetOption('FullyBookedMessage', '');
				$captcha = $this->GetOption('Captcha', 0);
				$childrenSelection = $this->GetOption('ChildrenSelection', 0);
				$childrenDescription = $this->GetOption('ChildrenDescription', '');
				$captchaKey = $this->GetOption('CaptchaKey', '');
				
                $waitlist = $this->GetOption('WaitList', 0);

                $minPersons = $this->GetOption('MinPersons', 1);
                $maxPersons = $this->GetOption('MaxPersons', 10);
                $alternativeTimeStep = $this->GetOption('AlternativeTimeStep', 30);
                $largeGroupsMessage = $this->GetOption('LargeGroupsMessage', '');
                $emailFrom = $this->GetOption('EmailFrom', ReDiSendEmailFromOptions::ReDi);
                $report = $this->GetOption('Report', Report::Full);

                $getServices = $this->redi->getServices($categoryID);
                if (isset($getServices['Error'])) {
                    $errors[] = $getServices['Error'];
                }

                $custom_fields = $this->redi->getCustomField(self::lang(), $placeID);
            }

            if (!$settings_saved && isset($_POST['submit'])) {
                $timepicker = self::GetPost('TimePicker');
                $alternativeTimeStep = self::GetPost('AlternativeTimeStep');
            }

            $place = $places[0];

            require_once(REDI_RESTAURANT_TEMPLATE . 'admin.php');
            require_once(REDI_RESTAURANT_TEMPLATE . 'basicpackage.php');
        }

        private function GetOption($name, $default = null)
        {
            return isset($this->options[$name]) ? $this->options[$name] : $default;
        }

        private static function GetPost($name, $default = null)
        {
            return isset($_POST[$name]) ? sanitize_text_field($_POST[$name]) : $default;
        }

        function GetServiceTimes()
        {
            $serviceTimes = array();
            foreach ($_POST['OpenTime'] as $key => $value) {
                if (self::set_and_not_empty($value)) {
                    $serviceTimes[$key]['OpenTime'] = sanitize_text_field($value);
                }
            }
            foreach ($_POST['CloseTime'] as $key => $value) {
                if (self::set_and_not_empty($value)) {
                    $serviceTimes[$key]['CloseTime'] = sanitize_text_field($value);
                }
            }

            return $serviceTimes;
        }

        function ajaxed_admin_page($placeID, $categoryID, $settings_saved = false)
        {
            require_once(plugin_dir_path(__FILE__) . 'languages.php');
            $places = $this->redi->getPlaces();
            $getServices = $this->redi->getServices($categoryID);
            $apiKey = isset($this->options['ID']) ? $this->options['ID'] : null;

            if (!isset($_POST['submit']) || $settings_saved) {

                $serviceTimes = $this->redi->getServiceTime($categoryID); //goes to template 'admin'
                $serviceTimes = json_decode(json_encode($serviceTimes), true);
                $place = $this->redi->getPlace($placeID); //goes to template 'admin'

            } else {
                $place = array(
                    'Name' => self::GetPost('Name'),
                    'City' => self::GetPost('City'),
                    'Country' => self::GetPost('Country'),
                    'Address' => self::GetPost('Address'),
                    'Email' => self::GetPost('Email'),
                    'EmailCC' => self::GetPost('EmailCC'),
                    'Phone' => self::GetPost('Phone'),
                    'WebAddress' => self::GetPost('WebAddress'),
                    'Lang' => self::GetPost('Lang'),
                    'DescriptionShort' => self::GetPost('DescriptionShort'),
                    'DescriptionFull' => self::GetPost('DescriptionFull'),
                    'MinTimeBeforeReservation' => self::GetPost('MinTimeBeforeReservation'),
                    'Catalog' => (int)self::GetPost('Catalog'),
                    'DateFormat' => self::GetPost('DateFormat')
                );
                $serviceTimes = self::GetServiceTimes();
            }
            require_once('countrylist.php');
            require_once(REDI_RESTAURANT_TEMPLATE . 'admin_ajaxed.php');
        }

        function init_sessions()
        {
            if (function_exists('load_plugin_textdomain')) {
                add_filter('load_textdomain_mofile', array($this, 'language_files'), 10, 2);
                load_plugin_textdomain('redi-restaurant-reservation', false, 'redi-restaurant-reservation/lang');
                load_plugin_textdomain('redi-restaurant-reservation-errors', false,
                    'redi-restaurant-reservation/lang');
            }

        }

        function redi_restaurant_admin_menu_link_new()
        {
            $icon = 'dashicons-groups';

            if ($this->ApiKey) {
                add_menu_page(
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi-restaurant-reservation-reservations',
                    array(&$this, 'redi_restaurant_admin_welcome'),
                    $icon);
					
				add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('Welcome', 'redi-restaurant-reservation'),
                    __('Welcome', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi_restaurant_welcome_reservations',
                    array(&$this, 'redi_restaurant_admin_welcome'));	
						
				add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi_restaurant_admin_reservations',
                    array(&$this, 'redi_restaurant_admin_reservations'));

                add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('Settings', 'redi-restaurant-reservation'),
                    __('Settings', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi-restaurant-reservation-settings',
                    array(&$this, 'redi_restaurant_admin_options_page'));

                add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('Settings $', 'redi-restaurant-reservation'),
                    __('Settings $', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi_restaurant_basic_package_settings',
                    array(&$this, 'redi_restaurant_basic_package_settings'));
                add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('Test reservation', 'redi-restaurant-reservation'),
                    __('Test reservation', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi-restaurant-reservation-test',
                    array(&$this, 'redi_restaurant_admin_test'));
                add_submenu_page(
                    'redi-restaurant-reservation-reservations',
                    __('Waiter Dashboard', 'redi-restaurant-reservation'),
                    __('Waiter Dashboard', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi_restaurant_admin_upcoming',
                    array(&$this, 'redi_restaurant_admin_upcoming'));
            } else {

				add_menu_page(
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    __('ReDi Reservations', 'redi-restaurant-reservation'),
                    'edit_posts',
                    'redi-restaurant-reservation-reservations',
                    array(&$this, 'admin_welcome_no_key'),
                    $icon);
            }
        }

        function remove_admin_submenu_items() {
			remove_submenu_page('redi-restaurant-reservation-reservations', 'redi-restaurant-reservation-reservations');
		}

        static function install()
        {
            //register is here
        }

        public function activate()
        {
            delete_option($this->_name . '_page_title');
            add_option($this->_name . '_page_title', $this->page_title, '', 'yes');

            delete_option($this->_name . '_page_name');
            add_option($this->_name . '_page_name', $this->page_name, '', 'yes');

            delete_option($this->_name . '_page_id');
            add_option($this->_name . '_page_id', $this->page_id, '', 'yes');

            $the_page = get_page_by_title($this->page_title);

            if (!$the_page) {
                // Create post object
                $_p = array();
                $_p['post_title'] = $this->page_title;
                $_p['post_content'] = $this->content;
                $_p['post_status'] = 'publish';
                $_p['post_type'] = 'page';
                $_p['comment_status'] = 'closed';
                $_p['ping_status'] = 'closed';
                $_p['post_category'] = array(1); // the default 'Uncategorized'
                // Insert the post into the database
                $this->page_id = wp_insert_post($_p);
            } else {
                // the plugin may have been previously active and the page may just be trashed...
                $this->page_id = $the_page->ID;

                //make sure the page is not trashed...
                $the_page->post_status = 'publish';
                $this->page_id = wp_update_post($the_page);
            }

            delete_option($this->_name . '_page_id');
            add_option($this->_name . '_page_id', $this->page_id);

        }

        private static function set_and_not_empty($value)
        {
            return (isset($value) && !empty($value));
        }

        public function deactivate()
        {
            $this->deletePage();
            $this->deleteOptions();
        }

        public static function uninstall()
        {
            self::deletePage(true);
            self::deleteOptions();
        }

        private function deletePage($hard = false)
        {
            $id = get_option(self::$name . '_page_id');
            if ($id && $hard == true) {
                wp_delete_post($id, true);
            } elseif ($id && $hard == false) {
                wp_delete_post($id);
            }
        }

        private function deleteOptions()
        {
            delete_option(self::$name . '_page_title');
            delete_option(self::$name . '_page_name');
            delete_option(self::$name . '_page_id');
        }



        public function shortcode($attributes)
        {
            if (is_array($attributes) && is_array($this->options)) {
                $this->options = array_merge($this->options, $attributes);
            }

            ob_start();
            wp_enqueue_script('jquery');
            wp_enqueue_style('jquery_ui');
            wp_enqueue_script('moment');

            wp_register_style('jquery-ui-custom-style',
                REDI_RESTAURANT_PLUGIN_URL . 'css/custom-theme/jquery-ui-1.8.18.custom.css');
            wp_enqueue_style('jquery-ui-custom-style');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_register_script('datetimepicker',
                REDI_RESTAURANT_PLUGIN_URL . 'lib/datetimepicker/js/jquery-ui-timepicker-addon.js',
                array('jquery', 'jquery-ui-core', 'jquery-ui-slider', 'jquery-ui-datepicker'));
            wp_enqueue_script('datetimepicker');

            wp_register_script('datetimepicker-lang',
                REDI_RESTAURANT_PLUGIN_URL . 'lib/datetimepicker/js/jquery.ui.i18n.all.min.js');
            wp_enqueue_script('datetimepicker-lang');

            wp_register_script('timepicker-lang',
                REDI_RESTAURANT_PLUGIN_URL . 'lib/timepicker/i18n/jquery-ui-timepicker.all.lang.js');
            wp_enqueue_script('timepicker-lang');

            if ($this->GetOption('CountryCode', true))
            {
                wp_register_style('intl-tel-custom-style',
                    REDI_RESTAURANT_PLUGIN_URL . 'lib/intl-tel-input-16.0.0/build/css/intlTelInput.min.css');
                wp_enqueue_style('intl-tel-custom-style');	
                wp_register_script('intl-tel-input',
                    REDI_RESTAURANT_PLUGIN_URL . 'lib/intl-tel-input-16.0.0/build/js/utils.js');
                wp_enqueue_script('intl-tel-input');
                wp_register_script('intl-tel',
                    REDI_RESTAURANT_PLUGIN_URL . 'lib/intl-tel-input-16.0.0/build/js/intlTelInput.min.js');
                wp_enqueue_script('intl-tel');
            }

            wp_register_script('restaurant', REDI_RESTAURANT_PLUGIN_URL . 'js/restaurant.js', array(
                'jquery',
                'jquery-ui-tooltip'
            ),self::plugin_get_version(), true);

            if (file_exists(plugin_dir_path(__FILE__) . 'js/maxpersonsoverride.js')) {
                wp_register_script('maxpersonsoverride', REDI_RESTAURANT_PLUGIN_URL . 'js/maxpersonsoverride.js', array(
                    'restaurant'
                ));
                wp_enqueue_script('maxpersonsoverride');
            }

            wp_localize_script('restaurant',
                'redi_restaurant_reservation',
                array( // URL to wp-admin/admin-ajax.php to process the request
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'id_missing' => __('Reservation number can\'t be empty', 'redi-restaurant-reservation'),
                    'name_missing' => __('Name can\'t be empty', 'redi-restaurant-reservation'),
                    'fname_missing' => __('First Name can\'t be empty', 'redi-restaurant-reservation'),
                    'lname_missing' => __('Last Name can\'t be empty', 'redi-restaurant-reservation'),
                    'personalInf' => __('Name or Phone or Email can\'t be empty', 'redi-restaurant-reservation'),
                    'email_missing' => __('Email can\'t be empty', 'redi-restaurant-reservation'),
                    'phone_missing' => __('Phone can\'t be empty', 'redi-restaurant-reservation'),
                    'phone_not_valid' => __('Phone number is not valid', 'redi-restaurant-reservation'),
                    'reason_missing' => __('Reason can\'t be empty', 'redi-restaurant-reservation'),
                    'next' => __('Next', 'redi-restaurant-reservation'),
                    'tooltip' => __('This time is fully booked', 'redi-restaurant-reservation'),
                    'error_fully_booked' => __('There are no more reservations can be made for selected day.', 'redi-restaurant-reservation'),
                    'time_not_valid' => __('Provided time is not in valid format.', 'redi-restaurant-reservation'),
					'captcha_not_valid' => __('Captcha is not valid.', 'redi-restaurant-reservation'),
					'available_seats' => __('Available seats', 'redi-restaurant-reservation'),
                    // data
                    'enablefirstlastname' => $this->GetOption('EnableFirstLastName'),
                    'endreservationtime' => $this->GetOption('EndReservationTime'),
                    'countrycode' => $this->GetOption('CountryCode'),
				));

            wp_enqueue_script('restaurant');

            if (file_exists(get_stylesheet_directory() . '/redi-restaurant-reservation/restaurant.css'))
            {
                wp_register_style('redi-restaurant', get_stylesheet_directory_uri() . '/redi-restaurant-reservation/restaurant.css');
            }
            else
            {
                wp_register_style('redi-restaurant', REDI_RESTAURANT_PLUGIN_URL . 'css/restaurant.css');
            }
            
            wp_enqueue_style('redi-restaurant');

            $apiKeyId = (int)$this->GetOption('apikeyid');

            if ($apiKeyId) {
                $this->ApiKey = $this->GetOption('apikey' . $apiKeyId, $this->ApiKey);

                $check = get_option($this->apiKeyOptionName . $apiKeyId);
                if ($check != $this->ApiKey) { // update only if changed
                    //Save Key if newed
                    update_option($this->apiKeyOptionName . $apiKeyId, $this->ApiKey);
                }
                $this->redi->setApiKey($this->ApiKey);
            }
            if ($this->ApiKey == null) {
                $this->display_errors(array(
                    'Error' => __('Online reservation service is not available at this time. Try again later or', 'redi-restaurant-reservation') . ' ' .
                    '<a href="mailto:info@reservationdiary.eu;' . get_bloginfo('admin_email') . '?subject=Reservation form is not working&body=' . get_bloginfo().'">' .
                    __('contact us directly', 'redi-restaurant-reservation').'</a>',
                ), false, 'Frontend No ApiKey');

                return;
            }

            if (isset($_GET['jquery_fail']) && $_GET['jquery_fail'] === 'true') {
                $this->display_errors(array(
                    'Error' => __('Plugin failed to properly load javascript file, please check that jQuery is loaded and no javascript errors present.',
                        'redi-restaurant-reservation')
                ), false, 'Frontend No ApiKey');
            }
            //places
            $places = $this->redi->getPlaces();
            if (isset($places['Error'])) {
                $this->display_errors($places, false, 'getPlaces');
                return;
            }

            if (isset($this->options['placeid'])) {

                $ind = array_search($this->options['placeid'], array_column($places, 'ID'));
                $places = array($places[$ind]);
            }
            $placeID = $places[0]->ID;

            $categories = $this->redi->getPlaceCategories($placeID);
            if (isset($categories['Error'])) {
                $this->display_errors($categories, false, 'getPlaceCategories');
                return;
            }

            $categoryID = $categories[0]->ID;
            $time_format = get_option('time_format');

            $date_format_setting = $this->GetOption('DateFormat');
            $date_format = RediDateFormats::getPHPDateFormat($date_format_setting);
            $calendar_date_format = RediDateFormats::getCalendarDateFormat($date_format_setting);
            
            // TODO: Get time before reservation from place selected in shortcode in case of multiplace mode
            $MinTimeBeforeReservation = $places[0]->MinTimeBeforeReservation;

            $reservationStartTime = strtotime('+' . $MinTimeBeforeReservation . ' ' . $this->getPeriodFromType($places[0]->MinTimeBeforeReservationType),
                current_time('timestamp'));
            
            $startDate = date($date_format, $reservationStartTime);
            $startDateISO = date('Y-m-d', $reservationStartTime);
            $startTime = mktime(date('G', $reservationStartTime), 0, 0, 0, 0, 0);
            
            $minPersons = $this->GetOption('MinPersons', 1);
            $maxPersons = $this->GetOption('MaxPersons', 10);

            $largeGroupsMessage = $this->GetOption('LargeGroupsMessage', '');
            $emailFrom = $this->GetOption('EmailFrom', ReDiSendEmailFromOptions::ReDi);
            $report = $this->GetOption('Report', Report::Full);
            $thanks = $this->GetOption('Thanks');
			$manualReservation = $this->GetOption('ManualReservation');
			$displayLeftSeats = $this->GetOption( 'DisplayLeftSeats');
            $EnableCancelForm = $this->GetOption('EnableCancelForm', 0);
            $EnableModifyReservations = $this->GetOption('EnableModifyReservations', 0);
            $EnableSocialLogin = $this->GetOption('EnableSocialLogin');
            $fullyBookedMessage = $this->GetOption('FullyBookedMessage', '');
			$captcha = $this->GetOption('Captcha');
			$childrenSelection = $this->GetOption('ChildrenSelection');
			$childrenDescription = $this->GetOption('ChildrenDescription');
			$captchaKey = $this->GetOption('CaptchaKey', '');

            $waitlist = $this->GetOption('WaitList', 0);

            $timepicker = $this->GetOption('timepicker', $this->GetOption('TimePicker'));
            $time_format_hours = self::dropdown_time_format();
            $calendar = $this->GetOption('calendar',
                $this->GetOption('Calendar')); // first admin settings then shortcode

            $custom_fields = $this->redi->getCustomField(self::lang(), $placeID);

            $custom_duration = $this->loadCustomDurations();

            // todo: Reservation time
            if (!isset($custom_duration)) {
                $default_reservation_duration = $places[0]->ReservationDuration;
            } else {
                $default_reservation_duration = $custom_duration["durations"][0]["duration"];
            }

            $hide_clock = false;
            $persons = 0;
            $all_busy = false;
            $hidesteps = false; // this settings only for 'byshifts' mode
            $timeshiftmode = $this->GetOption('timeshiftmode', $this->GetOption('TimeShiftMode', 'byshifts'));
            if ($timeshiftmode === 'byshifts') {
                $hidesteps = $this->GetOption('hidesteps',
                        $this->GetOption('Hidesteps')) == '1'; // first admin settings then shortcode
                //pre call
                $categories = $this->redi->getPlaceCategories($placeID);
                $categoryID = $categories[0]->ID;
                $step1 = self::object_to_array(
                    $this->step1($categoryID,
                        array(
                            'startDateISO' => $startDateISO,
                            'startTime' => '0:00',
                            'persons' => $persons,
                            'lang' => get_locale(),
                            'duration' => $default_reservation_duration
                        )
                    )
                );
                $hide_clock = true;
            }

            $js_locale = get_locale();
            $datepicker_locale = substr($js_locale, 0, 2);
            
            $confirmationPage = $this->GetOption('ConfirmationPage', 0);
            $redirect_to_confirmation_page = "";

            if (strlen($confirmationPage) != 0)
            {
                $redirect_to_confirmation_page = get_permalink($confirmationPage);
            }

            $time_format_s = explode(':', $time_format);

            $timepicker_time_format = (isset($time_format_s[0]) && in_array($time_format_s[0],
                    array('g', 'h'))) ? 'h:mm tt' : 'HH:mm';
            $buttons_time_format = (isset($time_format_s[0]) && in_array($time_format_s[0],
                    array('g', 'h'))) ? 'h:MM TT' : 'HH:MM';
            if (function_exists('qtrans_convertTimeFormat') || function_exists('ppqtrans_convertTimeFormat')) {// time format from qTranslate and qTranslate Plus
                global $q_config;
                $format = $q_config['time_format'][$q_config['language']];
                $buttons_time_format = self::convert_to_js_format($format);
            }
            if (isset($this->options['ManualReservation']) && $this->options['ManualReservation'] == 1) {
                $manual = true;
            }

			$username = '';
			$lname = '';
			$email = '';
			$phone = '';
            $user_id = get_current_user_id();
            $returned_user = FALSE;
			$enablefirstlastname = $this->GetOption('EnableFirstLastName');
            
            if ($user_id)
            {
                $user_data = get_userdata($user_id);
                $username = get_user_meta( $user_id, 'first_name', true ); 
				$lname = get_user_meta( $user_id, 'last_name', true );
				
				if ($enablefirstlastname == 'false'){
					$username = $username . ' ' . $lname;
				}
                $email = $user_data->user_email;
                $phone = get_user_meta($user_id, 'phone', true );
                $userimg = get_avatar($user_id);
                $returned_user = !empty($username) && !empty($email) && !empty($phone);
				
				$this->add_reminder_to_make_a_reservation($placeID, self::lang(), $username, $email);
            }

            if (file_exists(get_stylesheet_directory() . '/redi-restaurant-reservation/frontend.php'))
            {
                require_once(get_stylesheet_directory() . '/redi-restaurant-reservation/frontend.php');
            }
            else
            {
                require_once(REDI_RESTAURANT_TEMPLATE . 'frontend.php');
            }

            $out = ob_get_contents();

            ob_end_clean();

            return $out;
        }

        function getPeriodFromType($type)
        {
            switch ($type) {
                case 'M':
                    return 'minutes';
                case 'D':
                    return 'days';
            }

            return 'hour';
        }

		function add_reminder_to_make_a_reservation($placeID, $lang, $username, $email)
		{
			if (empty($email) || empty($username))
			{
				// don't create if no information provided
				return;
			}

			// check that reminder is not created for this user
			$key = 'redi-reminder-for-' . $email;
			$val = get_transient($key);

			if ($val == null)
			{
				$ret = $this->redi->addReminder(
					$placeID,
					$lang,
					array(
						'Name'  => $username,
						'Email'  => $email));

				if ( !isset( $ret['Error'] ) ) 
				{
					set_transient($key, true, 60 * 60 * 24);
				}
			}
		}        

        function convert_to_js_format($format)
        {
            $convert = array(
//Day 	    --- 	---
//Week 	    --- 	---
//Month 	--- 	---
//Year 	    --- 	---
//Time 	    --- 	---

//%I 	Two digit representation of the hour in 12-hour format 	01 through 12
//hh 	Hours; leading zero for single-digit hours (12-hour clock).
                'I' => 'hh',
//%l (lower-case 'L') 	Hour in 12-hour format, with a space preceding single digits 	1 through 12
//h 	Hours; no leading zero for single-digit hours (12-hour clock).
                'l' => 'h',
//%k 	Two digit representation of the hour in 24-hour format, with a space preceding single digits 	0 through 23
//H 	Hours; no leading zero for single-digit hours (24-hour clock).
                'k' => 'H',
//%H 	Two digit representation of the hour in 24-hour format 	00 through 23
//HH 	Hours; leading zero for single-digit hours (24-hour clock).
                'H' => 'HH',
//%M 	Two digit representation of the minute 	00 through 59
//MM 	Minutes; leading zero for single-digit minutes.
                'M' => 'MM',
//%P 	lower-case 'am' or 'pm' based on the given time 	Example: am for 00:31, pm for 22:23
//tt 	Lowercase, two-character time marker string: am or pm.
                'P' => 'tt',
//%p 	UPPER-CASE 'AM' or 'PM' based on the given time 	Example: AM for 00:31, PM for 22:23
//TT 	Uppercase, two-character time marker string: AM or PM.
                'p' => 'TT',
            );

            $result = '';
            foreach (str_split($format) as $char) {
                if ($char == '%') {
                    $result .= '';
                } elseif (array_key_exists($char, $convert)) {
                    $result .= $convert[$char];
                } else {
                    $result .= $char;
                }
            }

            return $result;
        }

        function dropdown_time_format()
        {
            $wp_time_format = get_option('time_format');
            $wp_time_format_array = str_split($wp_time_format);
            foreach ($wp_time_format_array as $index => $format_char) // some users have G \h i \m\i\n
            {
                if ($format_char === '\\') {
                    $wp_time_format_array[$index] = '';
                    if (isset($wp_time_format_array[$index + 1])) {
                        $wp_time_format_array[$index + 1] = '';
                    }
                }
            }
            $wp_time_format = implode('', $wp_time_format_array);
            $is_am_pm = strpos($wp_time_format, 'g');
            $is_am_pm_lead_zero = strpos($wp_time_format, 'h');

            $is_24 = strpos($wp_time_format, 'G');
            $is_24_lead_zero = strpos($wp_time_format, 'H');

            if ($is_am_pm !== false || $is_am_pm_lead_zero !== false) {
                $a = stripos($wp_time_format, 'a');
                $am_text = '';
                if ($a !== false) {
                    $am_text = $wp_time_format[$a];
                }
                if ($is_am_pm !== false) {
                    return $wp_time_format[$is_am_pm] . ' ' . $am_text;
                }
                if ($is_am_pm_lead_zero !== false) {
                    return $wp_time_format[$is_am_pm_lead_zero] . ' ' . $am_text;
                }
            }
            if ($is_24 !== false) {
                return $wp_time_format[$is_24];
            }
            if ($is_24_lead_zero !== false) {
                return $wp_time_format[$is_24_lead_zero];
            }

            return 'H'; //if no time format found use 24 h with lead zero
        }

        private static function format_time($start_time, $language, $format)
        {

            global $q_config;
            if (function_exists('qtrans_convertTimeFormat')) {// time format from qTranslate
                $format = $q_config['time_format'][$language];

                return qtrans_strftime($format, strtotime($start_time));
            } elseif (function_exists('ppqtrans_convertTimeFormat')) { //and qTranslate Plus
                $format = $q_config['time_format'][$language];

                return ppqtrans_strftime($format, strtotime($start_time));
            }

            return date($format, strtotime($start_time));
        }

        private static function load_time_format($lang, $default_time_format)
        {
            $filename = plugin_dir_path(__FILE__) . 'time_format.json';

            if (file_exists($filename)) {
                $json = json_decode(file_get_contents($filename), true);
                if (array_key_exists($lang, $json)) {
                    return $json[$lang];
                }
            }

            return $default_time_format;
        }

        private function step1($categoryID, $post, $placeID = null)
        {
            global $q_config;
            $loc = get_locale();
            if (isset($post['lang'])) {
                $loc = $post['lang'];
            }
            $time_lang = null;
            $time_format = get_option('time_format');
            if (isset($q_config['language'])) { //if q_translate
                $time_lang = $q_config['language'];
                foreach ($q_config['locale'] as $key => $val) {
                    if ($loc == $val) {
                        $time_lang = $key;
                    }
                }
            } else { // load time format from file
                $time_format = self::load_time_format($loc, $time_format);
            }

            $timeshiftmode = self::GetPost('timeshiftmode',
                $this->GetOption('timeshiftmode', $this->GetOption('TimeShiftMode', 'byshifts')));
            // convert date to array
            $date = date_parse(self::GetPost('startDateISO') . ' ' . self::GetPost('startTime',
                    date('H:i', current_time('timestamp'))));

            if ($date['error_count'] > 0) {
                echo json_encode(
                    array_merge($date['errors'],
                        array('Error' => __('Selected date or time is not valid.', 'redi-restaurant-reservation'))
                    ));
                die;
            }

            $startTimeStr = $date['year'] . '-' . $date['month'] . '-' . $date['day'] . ' ' . $date['hour'] . ':' . $date['minute'];

            $persons = (int)$post['persons'];
            // convert to int
            $startTimeInt = strtotime($startTimeStr, 0);

            // calculate end time
            $endTimeInt = strtotime('+' . $this->getReservationTime($persons, (int)$post['duration']) . 'minutes', $startTimeInt);

            // format to ISO
            $startTimeISO = date('Y-m-d H:i', $startTimeInt);
            $endTimeISO = date('Y-m-d H:i', $endTimeInt);
            $currentTimeISO = current_datetime()->format('Y-m-d H:i');

            if ($timeshiftmode === 'byshifts') {
                $StartTime = date('Y-m-d 00:00', strtotime($post['startDateISO'])); //CalendarDate + 00:00
                $EndTime = date('Y-m-d 00:00',
                    strtotime('+1 day', strtotime($post['startDateISO']))); //CalendarDate + 1day + 00:00
                $params = array(
                    'StartTime' => urlencode($StartTime),
                    'EndTime' => urlencode($EndTime),
                    'Quantity' => $persons,
                    'Lang' => str_replace('_', '-', $post['lang']),
                    'CurrentTime' => urlencode($currentTimeISO),
                    'AlternativeTimeStep' => self::getAlternativeTimeStep($persons)
                );
                if (isset($post['alternatives'])) {
                    $params['Alternatives'] = (int)$post['alternatives'];
                }
                $params = apply_filters('redi-reservation-pre-query', $params);
                $alternativeTime = AlternativeTime::AlternativeTimeByDay;

                $custom_duration = $this->loadCustomDurations();

                if (isset($custom_duration)) {
                    // Check availability for custom duration
                    $custom_duration_availability = $this->redi->getCustomDurationAvailability($categoryID, array(
                        'date' => $post['startDateISO']));

                    // if for selected duration no more reservation is allowed return all booked flag
                    if (!$this->isReservationAvailableForSelectedDuration(
                        $persons, $custom_duration_availability, $custom_duration, (int)$post['duration'])) {
                        $query = array("all_booked_for_this_duration" => true);
                        return $query;
                    }
                }

                switch ($alternativeTime) {
                    case AlternativeTime::AlternativeTimeBlocks:
                        $query = $this->redi->query($categoryID, $params);
                        break;

                    case AlternativeTime::AlternativeTimeByShiftStartTime:
                        $query = $this->redi->availabilityByShifts($categoryID, $params);
                        break;

                    case AlternativeTime::AlternativeTimeByDay:
                        $params['ReservationDuration'] = $this->getReservationTime($persons, (int)$post['duration']);
                        $query = $this->redi->availabilityByDay($categoryID, $params);
                        break;
                }
            } else {
                $categories = $this->redi->getPlaceCategories($placeID);
                if (isset($categories['Error'])) {
                    $categories['Error'] = __($categories['Error'], 'redi-restaurant-reservation-errors');
                    echo json_encode($categories);
                    die;
                }

                $params = array(
                    'StartTime' => urlencode($startTimeISO),
                    'EndTime' => urlencode($endTimeISO),
                    'Quantity' => $persons,
                    'Alternatives' => 2,
                    'Lang' => str_replace('_', '-', $post['lang']),
                    'CurrentTime' => urlencode($currentTimeISO),
                    'AlternativeTimeStep' => self::getAlternativeTimeStep($persons)
                );
                $category = $categories[0];

                $query = $this->redi->query($category->ID, $params);
            }

            if (isset($query['Error'])) {
                return $query;
            }

            if ($timeshiftmode === 'byshifts') {

                $discounts = apply_filters('redi-reservation-discount', $startTimeInt);

                $query['alternativeTime'] = $alternativeTime;
                switch ($alternativeTime) {
                    case AlternativeTime::AlternativeTimeBlocks: // pass thought
                    case AlternativeTime::AlternativeTimeByShiftStartTime:
                        foreach ($query as $q) {
                            $q->Select = ($startTimeISO == $q->StartTime && $q->Available);
                            $q->StartTimeISO = $q->StartTime;
                            $q->EndTimeISO = $q->EndTime;
                            $q->StartTime = self::format_time($q->StartTime, $time_lang, $time_format);
                            $q->EndTime = date($time_format, strtotime($q->EndTime));

                            $duration = date_diff(date_create($q->StartTimeISO), date_create($q->EndTimeISO));
                            $q->Duration = $duration->h * 60 + $duration->i;
                        }
                        break;
                    case AlternativeTime::AlternativeTimeByDay:
                        foreach ($query as $q2) {
                            if (isset($q2->Availability)) {
                                foreach ($q2->Availability as $q) {
                                    
                                    if (isset($discounts))
                                    {
                                        $discountElement = apply_filters('redi-reservation-max-discount', $discounts, $q->StartTime);

                                        if (isset($discountElement))
                                        {
                                            $q->Discount = $discountElement->discountVisual;
                                            $q->DiscountClass = $discountElement->discountClass;
                                        }
                                    }
                                    
                                    $q->Select = ($startTimeISO == $q->StartTime && $q->Available);
                                    $q->StartTimeISO = $q->StartTime;
                                    $q->EndTimeISO = $q->EndTime;
                                    $q->StartTime = self::format_time($q->StartTime, $time_lang, $time_format);
                                    $q->EndTime = date($time_format, strtotime($q->EndTime));
                                    
                                    $duration = date_diff(date_create($q->StartTimeISO), date_create($q->EndTimeISO));
                                    $q->Duration = $duration->h * 60 + $duration->i;
                                }
                            }
                        }
                        break;
                }
            } else {
                foreach ($query as $q) {
                    $q->Select = ($startTimeISO == $q->StartTime && $q->Available);
                    $q->StartTimeISO = $q->StartTime;
                    $q->StartTime = self::format_time($q->StartTime, $time_lang, $time_format);
                    $q->EndTimeISO = $q->EndTime;
                    $q->EndTime = date($time_format, strtotime($q->EndTime));

                    $duration = date_diff(date_create($q->StartTimeISO), date_create($q->EndTimeISO));
                    $q->Duration = $duration->h * 60 + $duration->i;
                }
            }

            return $query;
        }

        private function isReservationAvailableForSelectedDuration($persons, $custom_duration_availability, $custom_duration, $selected_duration)
        {
            // Find from custom duration limits
            foreach ($custom_duration["durations"] as $d) {
                if ($d["duration"] == $selected_duration) {
                    if (!isset($d["limit"])) {
                        return true;
                    }

                    $limit = $d["limit"];

                    if ($limit == null) {
                        return true;
                    }

                    foreach ((array)$custom_duration_availability as $a) {
                        if ($a->Duration == $selected_duration) {
                            return $a->Guests + $persons < $limit;
                        }
                    }
                }
            }

            return true;
        }

        function save_reservation($params, $reservation)
        {
            if (isset($reservation['Error']))
            {
                return;
            }

            global $wpdb;
            
            $reservParams = $params['reservation'];

            $wpdb->insert( $this->table_name, [	
                'reservation_number' => $reservation['ID'],
                'name'    			 => $reservParams['UserName'],
                'lastname'    		 => $reservParams['LastName'],
                'phone'  		     => $reservParams['UserPhone'],
                'email'     		 => $reservParams['UserEmail'],
                'date_from'     	 => $reservParams['StartTime'],
                'date_to'          	 => $reservParams['EndTime'],
                'guests'        	 => $reservParams['Quantity'],
                'comments'           => $reservParams['UserComments'],			
                'prepayment'         => $reservParams['PrePayment'],			
                'currenttime'        => $reservParams['CurrentTime'],			
                'language'           => $reservParams['Lang']	
            ] );

            if (!isset($params['reservation']['Parameters']))
            {
                return;
            }

            $custom_fields = $params['reservation']['Parameters'];

            foreach ($custom_fields as $custom_field)
            {
                $wpdb->insert( $this->table_name . '_custom_fields', [	
                    'reservation_number' => $reservation['ID'],
                    'field_text'         => htmlentities($custom_field['Text'], ENT_QUOTES),
                    'name'    			 => $custom_field['Name'],
                    'type'  		     => $custom_field['Type'],
                    'value'     		 => $custom_field['Value']
                ] );
            }
        }

        function redi_restaurant_ajax()
        {

            $apiKeyId = $this->GetPost('apikeyid');
            if ($apiKeyId) {
                $this->ApiKey = get_option($this->apiKeyOptionName . $apiKeyId);
                $this->redi->setApiKey($this->ApiKey);
            }

            if (isset($_POST['placeID'])) {
                $placeID = (int)self::GetPost('placeID');
                $categories = $this->redi->getPlaceCategories($placeID);
                if (isset($categories['Error'])) {
                    echo json_encode($categories);
                    die;
                }
                $categoryID = $categories[0]->ID;

            }

            $date_format_setting = $this->GetOption('DateFormat');
            $date_format = RediDateFormats::getPHPDateFormat($date_format_setting);

            switch ($_POST['get']) {
                case 'step1':
                    echo json_encode($this->step1($categoryID, 
                    array(
                        'startDateISO' => self::GetPost('startDateISO'),
                        'persons' => self::GetPost('persons'),
                        'lang' => self::GetPost('lang'),
                        'duration' => self::GetPost('duration'),
                        'alternatives' => self::GetPost('alternatives')
                    ), $placeID));
                    break;

                case 'step3':
                    try 
                    {
                        $persons = (int)self::GetPost('persons');
                        $startTimeStr = self::GetPost('startTime');

                        // convert to int
                        $startTimeInt = strtotime($startTimeStr, 0);

                        // calculate end time
                        $endTimeInt = strtotime('+' . $this->getReservationTime($persons, (int)self::GetPost('duration')) . 'minutes', $startTimeInt);

                        // format to ISO
                        $startTimeISO = date('Y-m-d H:i', $startTimeInt);
                        $endTimeISO = date('Y-m-d H:i', $endTimeInt);
                        $currentTimeISO = current_datetime()->format('Y-m-d H:i');
                        $comment = '';
                        $parameters = array();
                        $custom_fields = array();
                        $custom_fields = $this->redi->getCustomField(self::lang(), $placeID);

                        foreach ($custom_fields as $custom_field) {
                            if (isset($_POST['field_' . $custom_field->Id])) {
                                $parameters[] = array(
                                    'Name' => $custom_field->Name,
                                    'Text' => $custom_field->Text,
                                    'Type' => $custom_field->Type,
                                    'Print' => $custom_field->Print ? 'true' : 'false',
                                    'Value' => sanitize_text_field(
                                        $custom_field->Type === 'text' ||  $custom_field->Type === 'dropdown' || $custom_field->Type === 'options' || $custom_field->Type === 'birthday' ?
                                            self::GetPost('field_' . $custom_field->Id) : (self::GetPost('field_' . $custom_field->Id) === 'on' ? 'true' : 'false')));
                            }
                        }

                        $discounts = apply_filters('redi-reservation-discount', $startTimeInt);

                        if (isset($discounts))
                        {
                            $discount = apply_filters('redi-reservation-max-discount', $discounts, $startTimeInt);

                            if ($discount && !empty($discount->discountText)) {
                                $comment .= __('Discount', 'redi-restaurant-reservation') . ': ' . $discount->discountText . '<br/>';
                            }                        
                        }
   
                        $children = (int)self::GetPost('children');

                        if ($children > 0)
                        {
                            $comment .= __('Children', 'redi-restaurant-reservation') . ': ' . $children . '<br/>';
                        }

                        if (!empty($comment)) {
                            $comment .= '<br/>';
                        }

                        $comment .= mb_substr(sanitize_text_field(self::GetPost('UserComments', '')), 0, 250);

                        $user_id = get_current_user_id();
                        $user_profile_image = "";

                        if ($user_id)
                        {
                            $user_profile_image = get_avatar_url($user_id);
                        }                    

                        $params = array(
                            'reservation' => array(
                                'StartTime' => $startTimeISO,
                                'EndTime' => $endTimeISO,
                                'Quantity' => $persons,
                                'UserName' => sanitize_text_field(self::GetPost('UserName')),
                                'FirstName' => sanitize_text_field(self::GetPost('UserName')),
                                'LastName' => sanitize_text_field(self::GetPost('UserLastName')),
                                'UserEmail' => sanitize_email(self::GetPost('UserEmail')),
                                'UserComments' => $comment,
                                'UserPhone' => sanitize_text_field(self::GetPost('UserPhone')),
                                'UserProfileUrl' => $user_profile_image,
                                'Name' => 'Person',
                                'Lang' => str_replace('_', '-', self::GetPost('lang')),
                                'CurrentTime' => $currentTimeISO,
                                'Version' => $this->version,
                                'PrePayment' => 'false',
                                'Source' => 'HOMEPAGE'
                            )
                        );
                        if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::Disabled ||
                            isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress
                        ) {
                            $params['reservation']['DontNotifyClient'] = 'true';
                        }

                        if (isset($this->options['ManualReservation']) && $this->options['ManualReservation'] == 1) {
                            $params['reservation']['ManualConfirmationLevel'] = 100;
                        }
                        if (!empty($parameters)) {
                            $params['reservation']['Parameters'] = $parameters;
                        }

                        if (isset($this->options['EnableFirstLastName']))
                        {
                            $reservation = $this->redi->createReservation_v1($categoryID, $params);
                        }
                        else
                        {
                            $reservation = $this->redi->createReservation($categoryID, $params);
                        }

                        //insert parameters into user database wp_redi_restaurant_reservation
                        if( !isset( $reservation['Error'] ) ) {
                        
                            $this->save_reservation($params, $reservation);

                            if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress ) {
                                //call api for content

                                do_action('redi-reservation-email-content', array(
                                    'id' => (int)$reservation['ID'],
                                    'lang' => str_replace('_', '-', self::GetPost('lang'))
                                ));

                                do_action('redi-reservation-send-confirmation-email', $this->emailContent);
                                //send
                            }
                        }
                        echo json_encode($reservation);
                    }
                    catch (Exception $e) {
                        echo $e->getMessage();
                    }
                    break;

                case 'get_place':
                    self::ajaxed_admin_page($placeID, $categoryID, true);
                    break;

                case 'date_information':
                    $place = $this->redi->getPlace($placeID);

                    $dates = $this->redi->getDateInformation(str_replace('_', '-', get_locale()), $categoryID, array(
                        'StartTime' => self::GetPost('from'),
                        'EndTime' => self::GetPost('to'),
                        'Guests' => self::GetPost('guests'),
                    ));

                    echo json_encode( $dates );

                    break;

				case 'get_custom_fields':
					
					$html = '';
					$custom_fields = $this->redi->getCustomField(self::lang(), $placeID);
					foreach ( $custom_fields as $custom_field ) {
						$html .= '<div>
							<label for="field_'.$custom_field->Id.'">'.$custom_field->Text;
								if( isset( $custom_field->Required) && $custom_field->Required ) {
                                    $html .= '
                                    <span class="redi_required"> *</span>
									<input type="hidden" id="field_'.$custom_field->Id.'_message" value="'.( ( !empty( $custom_field->Message ) ) ? ( $custom_field->Message ) : ( __( 'Custom field is required', 'redi-restaurant-reservation' ) ) ).'">';
								}
							$html .= '</label>';
							
							$input_field_type = 'text'; 
							switch( $custom_field->Type ) {
                                case 'options': 
                                    $input_field_type = 'radio';
                                    break;
                                case 'dropdown': 
                                    $input_field_type = 'dropdown';
                                    break;
								case 'newsletter':
								case 'reminder':
								case 'allowsms':
								case 'checkbox':
								case 'gdpr':
								$input_field_type = 'checkbox';	
                            }
                         
                            if ( $input_field_type == 'text' || $input_field_type == 'checkbox' ) {
                                $html .= '<input type="'.$input_field_type.'" value="" id="field_'.$custom_field->Id.'" name="field_'.$custom_field->Id.'"';

                                if( isset( $custom_field->Required ) && $custom_field->Required ) {
                                    $html .= ' class="field_required"';
                                }

                                if (isset ($custom_field->Default) && $custom_field->Default == 'True')
                                {
                                    $html .= ' checked';
                                }

                                $html .= '>';

                            } elseif ( $input_field_type =='radio' ) {
                                $field_values = explode( ',', $custom_field->Values );

                                foreach ( $field_values as $field_value ) {
                                    if( $field_value ) {
                                        $html .= '<input type="'.$input_field_type.'" value="'.$field_value.'" name="field_'.$custom_field->Id.'" id="field_'.$custom_field->Id.'_'.$field_value.'" class="redi-radiobutton';
                                        
                                        if( isset( $custom_field->Required ) && $custom_field->Required ) {
                                            $html .= ' field_required';
                                        } 
                                        $html .= '"><label class="redi-radiobutton-label" for="field_'.$custom_field->Id.'_'.$field_value.'">'.$field_value.'</label><br/>';
                                    }
                                }

                            } elseif ( $custom_field->Type == 'dropdown' ) {
                                $field_values = explode( ',', $custom_field->Values );
                                $html .= '<select id="field_'.$custom_field->Id.'" name="field_'.$custom_field->Id.'"';

                                if( isset( $custom_field->Required ) && $custom_field->Required ) {
                                    $html .= ' class="field_required"';
                                }                          
                                $html .= '>
                                    <option value="">Select</option>';
                                    foreach ( $field_values as $field_value ) {
                                        if( $field_value ) $html .= '<option value="'.$field_value.'">'.$field_value.'</option>';
                                    }
                                $html .= '</select>';
                            }												
                        $html .= '</div>';
					}
					echo json_encode($html);
					break;
                case 'cancel':

                    if( self::GetPost('Email') ) {
                        $personalInformation = urlencode(sanitize_email(self::GetPost('Email')));
                    } elseif ( self::GetPost('Phone') ) {
                        $personalInformation = urlencode(sanitize_text_field(self::GetPost('Phone')));
                    } elseif ( self::GetPost('Name') ) {
                        $personalInformation = urlencode(sanitize_text_field(self::GetPost('Name')));
                    }

                    $params = array(
                        'ID' => urlencode(self::GetPost('ID')),
                        'personalInformation' => $personalInformation,
                        'Reason' => urlencode(mb_substr(sanitize_text_field(self::GetPost('Reason')), 0, 250)),
                        'Lang' => str_replace('_', '-', self::GetPost('lang')),
                        'CurrentTime' => urlencode(date('Y-m-d H:i', current_time('timestamp'))),
                        'Version' => urlencode(self::plugin_get_version())
                    );
                    if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::Disabled ||
                        isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress
                    ) {
                        $params['DontNotifyClient'] = 'true';
                    }

                    $cancel = $this->redi->cancelReservationByClient($params);

                    if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress && !isset($cancel['Error'])) {
                        //call api for content
                        $emailContent = $this->redi->getEmailContent(
                            (int)$cancel['ID'],
                            EmailContentType::Canceled,
                            array(
                                'Lang' => str_replace('_', '-', self::GetPost('lang'))
                            )
                        );

                        //send
                        if (!isset($emailContent['Error'])) {
                            wp_mail($emailContent['To'], $emailContent['Subject'], $emailContent['Body'], array(
                                'Content-Type: text/html; charset=UTF-8',
                                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>' . "\r\n"
                            ));
                        }
                    }
                    echo json_encode($cancel);

                    break;

                case 'modify':
                    $reservation = $this->redi->findReservation( str_replace( '_', '-', self::GetPost( 'lang' ) ), preg_replace( '/[^0-9]/', '', self::GetPost( 'ID' )));

                    if( isset( $reservation['Error'] ) 
                        || ( strtolower( self::GetPost( 'Email' ) ) != strtolower( $reservation['Email'] )
                            && self::GetPost( 'Phone' ) != $reservation['Phone'] 
                            && strtolower( self::GetPost( 'Name' ) ) != strtolower( $reservation['Name'] ) ) 
                    ) 
                    {
                        $data = [
                            'reservation' => [
                                'Error' => __( 'Unable to find reservation with provided information. Please verify that provided reservation number and phone or name or email is correct.', 'redi-restaurant-reservation' )
                            ]
                        ];
                    } 
                    else if ($reservation['Status'] == 'CANCELED')
                    {
                        $data = [
                            'reservation' => [
                                'Error' => __( 'Reservation that is canceled can not be modified.', 'redi-restaurant-reservation' )
                            ]
                        ];
                    }
                    else 
                    {
                        $startDate = date(get_option('date_format'), strtotime( $reservation['From'] ) );
                        $startTime = date(get_option('time_format'), strtotime( $reservation['From'] ) );
                        
                        $data = [
                            'startDate' => $startDate, 
                            'startTime' => $startTime,
                            'reservation' => $reservation
                        ];
                    }

                    echo json_encode( $data );
                    break;

                case 'update':

                    $params = [
                        'PlaceReferenceId' => self::GetPost('PlaceReferenceId'),
                        'UserName' => sanitize_text_field(self::GetPost('UserName')),
                        'UserEmail' => sanitize_email(self::GetPost('UserEmail')),
                        'UserComments' => sanitize_text_field(self::GetPost('UserComments')),
                        'StartTime' => self::GetPost('StartTime'),
                        'EndTime' => self::GetPost('EndTime'),
                        'UserPhone' => sanitize_text_field(self::GetPost('UserPhone')),
                        'Quantity' => self::GetPost('Quantity'),
                        'Name' => 'Person',
                    ];

                    $DontNotifyClient = 'false';

                    if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::Disabled ||
                        isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress
                    ) 
                    {
                        $DontNotifyClient = 'true';
                    }

                    $currentTimeISO = current_datetime()->format('Y-m-d H:i');

                    $reservation = $this->redi->updateReservation(preg_replace( '/[^0-9]/', '', self::GetPost( 'ID' )), str_replace( '_', '-', self::GetPost( 'lang' ) ), $currentTimeISO, $DontNotifyClient, $params);

                    //update parameters into user database wp_redi_restaurant_reservation
                    if( !isset( $reservation['Error'] ) ) {
                        global $wpdb;
                        $wpdb->update( $this->table_name, [	
                            'name'    			 => $params['UserName'],
                            'phone'  		     => $params['UserPhone'],
                            'email'     		 => $params['UserEmail'],
                            'date_from'     	 => $params['StartTime'],
                            'date_to'          	 => $params['EndTime'],
                            'guests'        	 => $params['Quantity'],
                            'comments'           => $params['UserComments'],				
                        ], ['reservation_number' => self::GetPost('ID')] );
                        
                        //mail
                        if (isset($this->options['EmailFrom']) && $this->options['EmailFrom'] == ReDiSendEmailFromOptions::WordPress ) {
                            
                            //call api for content
                            do_action('redi-reservation-email-content', array(
                                'id' => (int)self::GetPost('ID'),
                                'lang' => str_replace('_', '-', self::GetPost('lang'))
                            ));

                            do_action('redi-reservation-send-confirmation-email', $this->emailContent);
                            //send
                        }
                        
                    }
                    
                    echo json_encode($reservation);
                    break;

                case 'formatDate':

                    $startDate = date($date_format, self::GetPost('startDate'));

                    echo $startDate;
                    break; 

                case 'waitlist':
                    $params = array(
                        'Name' => self::GetPost('Name'),
                        'Guests' => (int)self::GetPost('Guests'),
                        'Date' => self::GetPost('Date'),
                        'Email' => self::GetPost('Email'),
                        'Phone' => self::GetPost('Phone'),
                        'Time' => self::GetPost('Time')
                    );

                    $lang = self::lang();
                    
                    $CurrentTime = urlencode(date('Y-m-d H:i'));
                    
                    $waitlist = $this->redi->addWaitList($placeID, $params, $CurrentTime, $lang);
                    echo json_encode($waitlist);
                    #echo json_encode($params);
                    break;

                case 'activationCheck':

                    $type = self::GetPost('type');
                    $data = self::GetPost('data');

                    if($type == 'email') {

                        if(preg_match("/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i", $data) == 1) {
                            $this->register($data);
                            echo 'success';
                        } else {
                            $error['Error'] = __('Email is not valid.', 'redi-restaurant-reservation');
                            echo $this->display_errors($error, true);
                        }
                    } else {

                        if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89AB][0-9a-f]{3}-[0-9a-f]{12}$/i", $data) == 1) {               
                            $this->redi->setApiKey($data);
                            $this->ApiKey = $this->options['ID'] = $data;
                            $this->saveAdminOptions();
                            echo 'success';
                        } else {
                            $error['Error'] = __('Api key should be a GUID number.', 'redi-restaurant-reservation');
                            echo $this->display_errors($error, true);
                        }
                    }                  
                    break;     
            }

            die;
        }

        private function getAlternativeTimeStep($persons = 0)
        {
            $filename = plugin_dir_path(__FILE__) . 'alternativetimestep.json';

            if (file_exists($filename) && $persons) {
                $json = json_decode(file_get_contents($filename), true);
                if ($json !== null) {
                    if (array_key_exists($persons, $json)) {
                        return (int)$json[$persons];
                    }
                }
            }

            if (isset($this->options['AlternativeTimeStep']) && $this->options['AlternativeTimeStep'] > 0) {
                return (int)$this->options['AlternativeTimeStep'];
            }

            return 30;
        }

        private function loadCustomDurations()
        {
            // Load durations from config and show to users to select
            $filename = plugin_dir_path(__FILE__) . 'customduration.json';

            if (file_exists($filename)) {
                return json_decode(file_get_contents($filename), true);
            }

            return null;
        }

        private function getReservationTime($persons, $default_duration)
        {
            // Override duration based on number of persons visiting
            $filename = plugin_dir_path(__FILE__) . 'reservationtime.json';

            if (file_exists($filename) && $persons) {
                $json = json_decode(file_get_contents($filename), true);
                if ($json !== null) {
                    if (array_key_exists($persons, $json)) {
                        return (int)$json[$persons];
                    }
                }
            }

            return $default_duration;
        }

        private function object_to_array($object)
        {
            return json_decode(json_encode($object), true);
        }

        private static function lang()
        {

            $l = get_locale();

            if ($l == "") {
                $l = "en-US";
            }

            return str_replace('_', '-', $l);
        }
    }
}
new ReDiRestaurantReservation();

register_activation_hook(__FILE__, array('ReDiRestaurantReservation', 'install'));