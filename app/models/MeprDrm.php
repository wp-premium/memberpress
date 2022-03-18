<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprDrm extends MeprBaseModel {
  /** INSTANCE VARIABLES & METHODS **/
  public function __construct( $id ) {
      $this->rec = new stdClass();
      $this->rec->id = $id;
  }

  public function store(){}
  public function destroy(){}
} //End class
