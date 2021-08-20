<?php
if( ! defined( 'ABSPATH' ) ) { die( 'You are not allowed to call this page directly.' ); }
/*
Integration of free version of If Menu plugin with MemberPress
*/
class MeprIfMenuIntegration {
  public function __construct() {
    add_filter( 'if_menu_conditions', array( $this, 'add_if_menu_conditions' ) );
  }

  public function add_if_menu_conditions( $conditions ) {
    $memberships = MeprCptModel::all( 'MeprProduct' );

    if( !empty( $memberships ) ) {
      $conditions[] = array(
        'id'        => "active-membership-any",
        'name'      => '-- ' . __( 'Any Membership', 'memberpress' ) . ' --',
        'group'     => __( 'Active on Membership', 'memberpress' ),
        'condition' => function( $item ) {
          $user = MeprUtils::get_currentuserinfo();

          if( $user === false ) { return false; }

          $subs = $user->active_product_subscriptions('ids');

          return ( ! empty( $subs ) );
        }
      );

      foreach( $memberships as $m ) {
        $conditions[] = array(
          'id'        => "active-membership-{$m->ID}",
          'name'      => $m->post_title,
          'group'     => __( 'Active on Membership', 'memberpress' ),
          'condition' => function( $item ) use ($m) {
            return current_user_can( 'mepr-active', "membership: {$m->ID}" );
          }
        );
      }
    }

    $rules = MeprCptModel::all( 'MeprRule' );

    if( empty( $rules ) ) { return $conditions; }

    foreach( $rules as $r ) {
      $conditions[] = array(
        'id'        => "active-rule-{$r->ID}",
        'name'      => $r->post_title . ' [' . $r->ID . ']',
        'group'     => __( 'Active Membership Rule', 'memberpress' ),
        'condition' => function( $item ) use ($r) {
          return current_user_can( 'mepr-active', "rule: {$r->ID}" );
        }
      );
    }

    return $conditions;
  }
} //End class

new MeprIfMenuIntegration;
