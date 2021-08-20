<?php
// See https://www.avalara.com/vatlive/en/vat-rates/european-vat-rates.html
// Reduced Rates are currently a "best guess" based on information in above link
return MeprHooks::apply_filters('mepr-vat-countries', array(
  'AT' => array( 'name' => __('Austria', 'memberpress'),        'rate' => 20, 'reduced_rate' => 10,   'fmt' => '(AT)?U[0-9]{8}' ),
  'BE' => array( 'name' => __('Belgium', 'memberpress'),        'rate' => 21, 'reduced_rate' => 6,    'fmt' => '(BE)?0[0-9]{9}' ),
  'BG' => array( 'name' => __('Bulgaria', 'memberpress'),       'rate' => 20, 'fmt' => '(BG)?[0-9]{9,10}' ),
  'CY' => array( 'name' => __('Cyprus', 'memberpress'),         'rate' => 19, 'reduced_rate' => 5,    'fmt' => '(CY)?[0-9]{8}L' ),
  'CZ' => array( 'name' => __('Czech Republic', 'memberpress'), 'rate' => 21, 'reduced_rate' => 10,   'fmt' => '(CZ)?[0-9]{8,10}' ),
  'DE' => array( 'name' => __('Germany', 'memberpress'),        'rate' => 19, 'reduced_rate' => 7,    'fmt' => '(DE)?[0-9]{9}' ),
  'DK' => array( 'name' => __('Denmark', 'memberpress'),        'rate' => 25, 'reduced_rate' => 0,    'fmt' => '(DK)?[0-9]{8}' ),
  'EE' => array( 'name' => __('Estonia', 'memberpress'),        'rate' => 20, 'reduced_rate' => 9,    'fmt' => '(EE)?[0-9]{9}' ),
  'GR' => array( 'name' => __('Greece', 'memberpress'),         'rate' => 24, 'reduced_rate' => 6,    'fmt' => '(EL|GR)?[0-9]{9}' ),
  'ES' => array( 'name' => __('Spain', 'memberpress'),          'rate' => 21, 'reduced_rate' => 4,    'fmt' => '(ES)?[0-9A-Z][0-9]{7}[0-9A-Z]' ),
  'FI' => array( 'name' => __('Finland', 'memberpress'),        'rate' => 24, 'reduced_rate' => 10,   'fmt' => '(FI)?[0-9]{8}' ),
  'FR' => array( 'name' => __('France', 'memberpress'),         'rate' => 20, 'reduced_rate' => 5.5,  'fmt' => '(FR)?[0-9A-Z]{2}[0-9]{9}' ),
  'HR' => array( 'name' => __('Croatia', 'memberpress'),        'rate' => 25, 'reduced_rate' => 5,    'fmt' => '(HR)?[0-9]{11}' ),
  'GB' => array( 'name' => __('United Kingdom', 'memberpress'), 'rate' => 20, 'reduced_rate' => 0,    'fmt' => '(GB)?([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3})' ),
  'HU' => array( 'name' => __('Hungary', 'memberpress'),        'rate' => 27, 'reduced_rate' => 5,    'fmt' => '(HU)?[0-9]{8}' ),
  'IE' => array( 'name' => __('Ireland', 'memberpress'),        'rate' => 23, 'reduced_rate' => 9,    'fmt' => '(IE)?[0-9][0-9|A-Z][0-9]{5}[0-9|A-Z]{1,2}' ),
  'IT' => array( 'name' => __('Italy', 'memberpress'),          'rate' => 22, 'reduced_rate' => 4,    'fmt' => '(IT)?[0-9]{11}' ),
  'LT' => array( 'name' => __('Lithuania', 'memberpress'),      'rate' => 21, 'reduced_rate' => 5,    'fmt' => '(LT)?([0-9]{9}|[0-9]{12})' ),
  'LU' => array( 'name' => __('Luxembourg', 'memberpress'),     'rate' => 17, 'reduced_rate' => 3,    'fmt' => '(LU)?[0-9]{8}' ),
  'LV' => array( 'name' => __('Latvia', 'memberpress'),         'rate' => 21, 'reduced_rate' => 12,   'fmt' => '(LV)?[0-9]{11}' ),
  'MT' => array( 'name' => __('Malta', 'memberpress'),          'rate' => 18, 'reduced_rate' => 5,    'fmt' => '(MT)?[0-9]{8}' ),
  'NL' => array( 'name' => __('Netherlands', 'memberpress'),    'rate' => 21, 'reduced_rate' => 9,    'fmt' => '(NL)?[0-9]{9}B[0-9]{2}' ),
  'PL' => array( 'name' => __('Poland', 'memberpress'),         'rate' => 23, 'reduced_rate' => 5,    'fmt' => '(PL)?[0-9]{10}' ),
  'PT' => array( 'name' => __('Portugal', 'memberpress'),       'rate' => 23, 'reduced_rate' => 6,    'fmt' => '(PT)?[0-9]{9}' ),
  'RO' => array( 'name' => __('Romania', 'memberpress'),        'rate' => 19, 'reduced_rate' => 5,    'fmt' => '(RO)?[0-9]{2,10}' ),
  'SE' => array( 'name' => __('Sweden', 'memberpress'),         'rate' => 25, 'reduced_rate' => 6,    'fmt' => '(SE)?[0-9]{12}' ),
  'SI' => array( 'name' => __('Slovenia', 'memberpress'),       'rate' => 22, 'reduced_rate' => 5,    'fmt' => '(SI)?[0-9]{8}' ),
  'SK' => array( 'name' => __('Slovakia', 'memberpress'),       'rate' => 20, 'fmt' => '(SK)?[0-9]{10}' )
) );
