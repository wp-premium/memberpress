<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprVatTaxCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // Filter for MP Options page (field to enable VAT and collect VAT country & VAT ID)
    add_action('mepr_tax_rate_options', array($this,'options'));
    add_action('mepr-process-options', array($this,'store_options'));

    $calculate_taxes = get_option('mepr_calculate_taxes');
    $vat_enabled = get_option('mepr_vat_enabled');

    if($calculate_taxes && $vat_enabled) {
      $mepr_options = MeprOptions::fetch();

      // Enqueue scripts
      add_filter('mepr-signup-scripts', array($this,'product_scripts'), 10, 3);

      // Filter for signup / payment page
      add_action('mepr-checkout-before-submit', array($this,'signup'), 9, 1);

      // Validate the VAT number
      add_filter('mepr-validate-signup', array($this,'validate_signup'));

      // STORE THE VAT FIELDS WITH THE USER RECORD
      add_action('mepr-process-signup', array($this,'process_signup'), 10, 4);

      // Filter for tax calculation
      add_filter('mepr_find_tax_rate', array($this,'find_rate'), 20, 8);

      // Follow use merchant address from here on out?
      //add_filter('mepr-tax-rate-use-customer-address', array($this,'use_customer_address'), 10, 2);

      add_action('mepr_extra_profile_fields', array($this,'extra_profile_fields'));

      // TODO: VAT collected by month available as CSV download
      add_action('mepr-report-footer', array($this,'vat_csv_buttons'));
      add_action('wp_ajax_mepr_vat_country_report', array($this,'country_vat_csv'));
    }
  }

  public function product_scripts($prereqs, $is_product_page, $is_account_page) {
    $mepr_options = MeprOptions::fetch();

    if($this->vat_calc_possible()) {
      $vat_enabled = get_option('mepr_vat_enabled');

      if(($mepr_options->global_styles || $is_product_page) && $vat_enabled) {
        $countries   = $this->get_vat_countries();
        $vat_country = strtoupper( get_option( 'mepr_vat_country' ) );
        wp_enqueue_script('mpvat', MEPR_JS_URL.'/mpvat.js', array('jquery', 'mp-i18n'));
        wp_localize_script('mpvat', 'MpVat', array(
          'rates'       => $countries,
          'countries'   => array_keys($countries),
          'vat_country' => $vat_country,
        ));
        $prereqs[] = 'mpvat';
      }
    }

    return $prereqs;
  }

  public function options() {
    $vat_enabled        = get_option('mepr_vat_enabled');
    $vat_country        = get_option('mepr_vat_country');
    $vat_tax_businesses = get_option('mepr_vat_tax_businesses');
    $vat_disable_vies_service = get_option('mepr_vat_disable_vies_service');
    $charge_business_customer_net_price = get_option('mepr_charge_business_customer_net_price');

    $countries = $this->get_vat_countries();

    MeprView::render('/admin/taxes/vat_options', get_defined_vars());
  }

  public function validate_signup($errors) {
    $prd = new MeprProduct($_POST['mepr_product_id']);

    if($this->vat_calc_possible() && ($prd->price > 0.00 || ($prd->price <= 0.00 && !$prd->disable_address_fields))) {
      $country = $_POST['mepr-address-country'];
      $customer_type = $this->get_customer_type();
      $vat_number = $this->get_vat_number();
      $vat_tax_businesses = get_option('mepr_vat_tax_businesses', false);

      //If customer is a business, then a value must be entered for the vat number
      //Unless tax all eu business is enabled
      if ($customer_type=='business' && empty($vat_number) && !$vat_tax_businesses) {
              $errors['mepr_vat_number'] = __('VAT number is required', 'memberpress');
      } else if($customer_type=='business' &&
         !empty($vat_number) &&
         !$this->vat_number_is_valid($vat_number, $country)) {
        $errors['mepr_vat_number'] = __('Your VAT number is invalid', 'memberpress');
      }
    }

    return $errors;
  }

  public function store_options() {
    $vat_enabled = isset($_POST['mepr_vat_enabled']);
    $vat_country = isset($_POST['mepr_vat_country']) ? sanitize_text_field($_POST['mepr_vat_country']) : '';
    $vat_tax_businesses = isset($_POST['mepr_vat_tax_businesses']);
    $vat_disable_vies_service = isset($_POST['mepr_vat_disable_vies_service']);
    $charge_business_customer_net_price = isset($_POST['mepr_charge_business_customer_net_price']);

    update_option('mepr_vat_enabled', $vat_enabled);
    update_option('mepr_vat_country', $vat_country);
    update_option('mepr_vat_tax_businesses', $vat_tax_businesses);
    update_option('mepr_vat_disable_vies_service', $vat_disable_vies_service);
    update_option('mepr_charge_business_customer_net_price', $charge_business_customer_net_price);
  }

  public function signup($prd_id) {
    $prd = new MeprProduct($prd_id);

    if($this->vat_calc_possible() && ($prd->price > 0.00 || ($prd->price <= 0.00 && !$prd->disable_address_fields))) {
      $vat_customer_type = $this->get_customer_type();
      $vat_number = $this->get_vat_number();

      static $unique_suffix = 0;
      $unique_suffix++;

      MeprView::render('/taxes/vat_signup', get_defined_vars());
    }
  }

  public function process_signup($amt, $usr, $pid, $tid) {
    $is_stripe_spc = isset($_POST['mepr_process_signup_form'], $_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']);

    if($this->vat_calc_possible() || $is_stripe_spc) {
      if(isset($_POST['mepr_vat_customer_type'])) {
        update_user_meta($usr->ID, 'mepr_vat_customer_type', $this->get_customer_type());
      }

      if(isset($_POST['mepr_vat_number'])) {
        update_user_meta($usr->ID, 'mepr_vat_number', $this->get_vat_number());
      }
    }
  }

  /** VAT overrides anything that could possibly be set by the standard tax rate db tables */
  public function find_rate($tax_rate, $country, $state, $postcode, $city, $street, $usr=null, $prd_id = null) {
    $mepr_options = MeprOptions::fetch();
    $countries = $this->get_vat_countries();
    $customer_type = $this->get_customer_type($usr);
    $vat_number = $this->get_vat_number($usr);
    $vat_tax_businesses = get_option('mepr_vat_tax_businesses', false);
    $vat_country = get_option('mepr_vat_country');
    $vies_country = $country;

    if ( $customer_type === 'business' ) {
      $tax_rate->customer_type = 'business';
    }

    if(!empty($usr) && $usr instanceof MeprUser && $usr->address_is_set()) {
      $usr_country = $usr->address('country');

      // When updating pricing terms string with AJAX,user country should be the POST country
      if( isset($_POST['action']) && ($_POST['action'] == "mepr_update_price_string" || $_POST['action'] == "mepr_update_spc_invoice_table") ){
        $usr_country = sanitize_text_field($_POST['mepr_address_country']);
      }

      if($customer_type == 'business'){
        $vies_country = $usr_country;
      }

      // If the user's address is set and their country is outside the UK or EU then bail
      if($vat_country != $usr_country && !array_key_exists($usr_country, $countries)) {
        return $tax_rate;
      }

      // Canary Islands (Spain) has different VAT Rules
      if($usr_country == 'ES') {
        $canary_island_zips = array('35','38','51','52');
        $usr_zip = (string)trim($usr->address('zip'));

        foreach($canary_island_zips as $zip_prefix) {
          if(strpos($usr_zip, $zip_prefix) === 0) {
            return $tax_rate;
          }
        }
      }

      if($mepr_options->attr('tax_calc_location')=='customer'
        && $vat_country === 'GB'
        && $usr_country !== 'GB'
        && strtotime('2020-12-31 23:59:59') < time()
      ) {
        return new MeprTaxRate();
      }
    }

    // Make sure this is an EU country
    if(array_key_exists($country,$countries)) {
      // Conditions for calculating VAT or not
      // If we're taxing all businesses then vat tax validation doesn't matter
      if( $customer_type=='consumer' ||
          ( $customer_type=='business' &&
            ( $vat_country==$usr_country ||
              $vat_tax_businesses ||
              !$this->vat_number_is_valid($vat_number, $vies_country) )
          ) ||
          ( MeprTransactionsHelper::is_charging_business_net_price() )
      ) {
        $tax_rate = $this->get_rate($tax_rate, $country, $prd_id);
      }
    }

    return $tax_rate;
  }

  private function get_rate(MeprTaxRate $tax_rate, $country, $prd_id=null) {
    $countries = $this->get_vat_countries();
    $prd_id = ! empty( $_POST['mepr_product_id'] ) ? (int) $_POST['mepr_product_id'] : $prd_id;
    $prd_id = ! empty( $_POST['prd_id'] ) ? (int) $_POST['prd_id'] : $prd_id;

    $prd = new MeprProduct( $prd_id );

    $tax_rate->tax_rate = $countries[$country]['rate'];

    if($prd->ID && $prd->tax_class == 'reduced' && isset($countries[$country]['reduced_rate'])){
      $tax_rate->tax_rate = $countries[$country]['reduced_rate'];
    }
    $tax_rate->tax_desc = sprintf(__('VAT (%s)', 'memberpress'), $countries[$country]['name']);
    $tax_rate->tax_class = 'vat';

    return $tax_rate;
  }

  public function vat_number_is_valid($vat_number, $country) {
    $countries = $this->get_vat_countries();
    $vat_number = trim(preg_replace('/[-.●]/','',$vat_number));

    return (
      isset($countries[$country]) &&
      preg_match("/^{$countries[$country]['fmt']}$/i",$vat_number) &&
      $this->vies_says_vat_is_valid($vat_number,$country)
    );
  }

  // http://ec.europa.eu/taxation_customs/vies/technicalInformation.html
  private function vies_says_vat_is_valid($vat_number, $country) {
    if(get_option('mepr_vat_disable_vies_service')) {
        return true;
    }

    if(extension_loaded('soap')) {
      $client = new SoapClient(
        'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl'
      );

      $args = array(
        'countryCode' => $country,
        // if the vat number is prefixed by the country code cut it out
        'vatNumber'   => preg_replace('/^'.preg_quote($country).'/i', '', $vat_number)
      );

      try {
        $resp = $client->checkVat($args);
        return (isset($resp->valid) && $resp->valid);
      }
      catch(Exception $e) {
        // If the VIES service is unavailable just fail silently
      }
    }

    return true; // Silently fail for now?
  }

  private function get_customer_type($usr=null) {
    if(array_key_exists('mepr_vat_customer_type',$_POST)) {
      return sanitize_text_field($_POST['mepr_vat_customer_type']);
    }

    // If the vat number is empty then grab the current user info
    if(MeprUtils::is_user_logged_in() && empty($usr)) {
      $usr = MeprUtils::get_currentuserinfo();
    }

    if(!empty($usr) &&
       ($ctype = get_user_meta($usr->ID,'mepr_vat_customer_type',true)) &&
       !empty($ctype)) {
      return $ctype;
    }

    // Default customer type right here people
    return 'consumer';
  }

  private function get_vat_number($usr=null) {
    if(array_key_exists('mepr_vat_number',$_POST)) {
      return sanitize_text_field($_POST['mepr_vat_number']);
    }

    // If the vat number is empty then grab the current user info
    if(MeprUtils::is_user_logged_in() && empty($usr)) {
      $usr = MeprUtils::get_currentuserinfo();
    }

    if(!empty($usr) &&
       ($vnum = get_user_meta($usr->ID,'mepr_vat_number',true)) &&
       !empty($vnum)) {
      return $vnum;
    }

    return '';
  }

  public function vat_csv_buttons($type='monthly') {
    // Download transactions with VAT country, business/consumer, and VAT number

    $totals_url = MeprUtils::admin_url(
      'admin-ajax.php',
      array('export_report','mepr_reports_nonce'),
      array(
        'action' => 'mepr_export_report',
        'export' => $type,
        'q'      => array(
          'var'  => 'tax_class',
          'val'  => 'vat',
        )
      ),
      true
    );

    $countries_url = MeprUtils::admin_url(
      'admin-ajax.php',
      array('export_vat_countries', 'mepr_vattaxes_nonce'),
      array(
        'action' => 'mepr_vat_country_report',
        'export' => $type
      ),
      true
    );

    ?>
    <a class="button" href="<?php echo $totals_url; ?>"><?php _e('Export VAT Totals', 'memberpress'); ?></a>
    <a class="button" href="<?php echo $countries_url; ?>"><?php _e('Export VAT by Country', 'memberpress'); ?></a>
    <?php
  }

  public function country_vat_csv() {
    check_ajax_referer('export_vat_countries', 'mepr_vattaxes_nonce');

    $type = (isset($_REQUEST['export']) && !empty($_REQUEST['export']))?$_REQUEST['export']:'monthly';

    if($type=='monthly') {
      $month = (isset($_REQUEST['month']) && !empty($_REQUEST['month']))?$_REQUEST['month']:date('n');
    }
    else {
      $month = false;
    }

    $year = (isset($_REQUEST['year']) && !empty($_REQUEST['year']))?$_REQUEST['year']:date('Y');
    $product = (isset($_REQUEST['product']) && $_REQUEST['product'] != 'all')?$_REQUEST['product']:'all';

    $data = $this->get_country_vat_data($year, $month, $product);

    $countries = $this->get_vat_countries();
    for($i=0; $i<count($data); $i++) {
      if( isset($countries[$data[$i][__('Country Code', 'memberpress')]]['name']) ) {
        $data[$i][__('Country', 'memberpress')] = $countries[$data[$i][__('Country Code', 'memberpress')]]['name'];
      }
      else {
        $data[$i][__('Country', 'memberpress')] = $data[$i][__('Country Code', 'memberpress')];
      }
    }

    $filename = sprintf(
      'memberpress%s-vat-by-country%s%s-for%s',
      "-{$type}",
      ($month?"-{$month}":''),
      "-{$year}",
      "-{$product}"
    );

    MeprUtils::render_csv($data, $filename);
    exit();
  }

  // VAT tax collected by country per month
  public function get_country_vat_data($year=false, $month=false, $product='all') {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = $month ? $wpdb->prepare(' AND MONTH(created_at) = %d', $month) : '';
    $andyear = $year ? $wpdb->prepare(' AND YEAR(created_at) = %d', $year) : '';
    $andproduct = (!isset($product) || empty($product) || $product == 'all') ? '' : " AND product_id = {$product}";

    $q = "
      SELECT um.meta_value AS \"" . __('Country Code', 'memberpress') . "\",
             SUM(tr.tax_amount) AS " . __('Total', 'memberpress') . "
        FROM {$mepr_db->transactions} AS tr
       INNER JOIN {$wpdb->usermeta} AS um
          ON um.user_id=tr.user_id
         AND um.meta_key='mepr-address-country'
       WHERE tr.tax_class='vat'
         AND tr.txn_type = '".MeprTransaction::$payment_str."'
         AND tr.status = '".MeprTransaction::$complete_str."'
       {$andmonth}
       {$andyear}
       {$andproduct}
       GROUP BY um.meta_value
    ";

    $res = $wpdb->get_results($q, ARRAY_A);

    return $res;
  }

  // VAT tax ALWAYS uses the customer address now ... so we override that here
  public function use_customer_address($use, $usr) {
    $countries = $this->get_vat_countries();
    $customer_country = $usr->address('country');

    if(isset($countries[$customer_country])) {
      return true;
    }
    else {
      return $use;
    }
  }

  public function extra_profile_fields($usr) {
    $tax_rate = $usr->tax_rate();

    // We're showing these regardless
    $ctype = $this->get_customer_type($usr);
    $vnum = $this->get_vat_number($usr);
    MeprView::render('/admin/taxes/vat_profile_fields', get_defined_vars());
  }

  private function get_vat_countries() {
    $mepr_options = MeprOptions::fetch();

    $moss_country = get_option('mepr_vat_country');
    $countries = require(MEPR_DATA_PATH.'/taxes/vat_countries.php');

    return $countries;
  }

  private function vat_calc_possible() {
    $mepr_options = MeprOptions::fetch();
    global $post;

    // Remove VAT Inputs on Tax-Exempt Memberships
    if ( false !== ( $prd = MeprProduct::is_product_page($post) ) ){
      if ( $prd->ID && $prd->is_tax_exempt() ){
        return false;
      }
    }

    return (!MeprUtils::is_user_logged_in() ||
            ($mepr_options->show_fields_logged_in_purchases &&
             $mepr_options->show_address_fields &&
             $mepr_options->require_address_fields));
  }
}
