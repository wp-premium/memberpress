<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprEvent extends MeprBaseModel {
  // Supported event types
  public static $users_str = 'users';
  public static $transactions_str = 'transactions';
  public static $subscriptions_str = 'subscriptions';
  public static $drm_str = 'drm';

  // User events
  public static $login_event_str = 'login';

  public function __construct($obj = null) {
    $this->initialize(
      array(
        'id'          => 0,
        'args'        => null,
        'event'       => 'login',
        'evt_id'      => 0,
        'evt_id_type' => 'users',
        'created_at'  => null
      ),
      $obj
    );
  }

  public function validate() {
    $this->validate_is_numeric($this->evt_id, 0, null, 'evt_id');
  }

  public static function get_one($id, $return_type = OBJECT) {
    $mepr_db = new MeprDb();
    $args = compact('id');
    return $mepr_db->get_one_record($mepr_db->events, $args, $return_type);
  }

  public static function get_one_by_event_and_evt_id_and_evt_id_type($event, $evt_id, $evt_id_type, $return_type = OBJECT) {
    $mepr_db = new MeprDb();
    return $mepr_db->get_one_record($mepr_db->events, compact('event','evt_id','evt_id_type'), $return_type);
  }

  public static function get_count() {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->events);
  }

  public static function get_count_by_event($event) {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->events, compact('event'));
  }

  public static function get_count_by_evt_id_type($evt_id_type) {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->events, compact('evt_id_type'));
  }

  public static function get_count_by_event_and_evt_id_and_evt_id_type($event, $evt_id, $evt_id_type) {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->events, compact('event','evt_id','evt_id_type'));
  }

  public static function get_all($order_by = '', $limit = '') {
    $mepr_db = new MeprDb();
    return $mepr_db->get_records($mepr_db->events, array(), $order_by, $limit);
  }

  public static function get_all_by_event($event, $order_by = '', $limit = '') {
    $mepr_db = new MeprDb();
    $args = array('event' => $event);
    return $mepr_db->get_records($mepr_db->events, $args, $order_by, $limit);
  }

  public static function get_all_by_evt_id_type($evt_id_type, $order_by = '', $limit = '') {
    $mepr_db = new MeprDb();
    $args = array('evt_id_type' => $evt_id_type);
    return $mepr_db->get_records($mepr_db->events, $args, $order_by, $limit);
  }

  public function store() {
    $mepr_db = new MeprDb();

    MeprHooks::do_action('mepr-event-pre-store', $this);

    $this->use_existing_if_unique();

    $vals = (array)$this->rec;
    unset($vals['created_at']); // let mepr_db handle this

    if(isset($this->id) and (int)$this->id > 0) {
      $mepr_db->update_record( $mepr_db->events, $this->id, $vals );
      MeprHooks::do_action('mepr-event-update', $this);
    }
    else {
      $this->id = $mepr_db->create_record( $mepr_db->events, $vals );
      MeprHooks::do_action('mepr-event-create', $this);
      MeprHooks::do_action('mepr-event',$this);

      MeprHooks::do_action("mepr-evt-{$this->event}",$this); // DEPRECATED
      MeprHooks::do_action("mepr-event-{$this->event}",$this);
    }

    MeprHooks::do_action('mepr-event-store', $this);

    return $this->id;
  }

  public function destroy() {
    $mepr_db = new MeprDb();

    $id = $this->id;
    $args = compact('id');

    MeprHooks::do_action('mepr_event_destroy', $this);

    return MeprHooks::apply_filters('mepr_delete_event', $mepr_db->delete_records($mepr_db->events, $args), $args);
  }

  // TODO: This is a biggie ... we don't want to send the event object like this
  //       we need to send the object associated with the event instead.
  public function get_data() {
    $obj = false;
    switch($this->evt_id_type) {
      case self::$users_str:
        $obj = new MeprUser($this->evt_id);

        // If member-deleted event is being passed, make sure we generate some data.
        if(!isset($obj->ID) || $obj->ID <= 0) {
          if($this->event == 'member-deleted') {
            $obj->ID = 0;
            $obj->user_email = 'johndoe@email.com';
            $obj->user_login = 'johndoe';
            $obj->first_name = 'John';
            $obj->last_name = 'Doe';
          }
        }

        break;
      case self::$transactions_str:
        $obj = new MeprTransaction($this->evt_id);
        break;
      case self::$subscriptions_str:
        $obj = new MeprSubscription($this->evt_id);
        break;
      default:
        return new WP_Error(__('An unsupported Event type was used', 'memberpress'));
    }

    return $obj;
  }

  public function get_args() {
    return json_decode($this->args);
  }

  public static function record($event, MeprBaseModel $obj, $args='') {
    //Nothing to record? Hopefully this stops some ghost duplicate reminders we are seeing
    //Gotta use ->rec here to avoid weird shiz from happening hopefully
    if((!isset($obj->rec->id) || !$obj->rec->id) && (!isset($obj->rec->ID) || !$obj->rec->ID)) { return; }

    $e = new MeprEvent();
    $e->event = $event;
    $e->args = $args;

    // Just turn objects into json for fun
    if(is_array($args) || is_object($args)) {
      $e->args = json_encode($args);
    }

    if($obj instanceof MeprUser) {
      $e->evt_id = $obj->rec->ID;
      $e->evt_id_type = self::$users_str;
    }
    elseif($obj instanceof MeprTransaction) {
      $e->evt_id = $obj->rec->id;
      $e->evt_id_type = self::$transactions_str;
    }
    elseif($obj instanceof MeprSubscription) {
      $e->evt_id = $obj->rec->id;
      $e->evt_id_type = self::$subscriptions_str;
    }
    elseif($obj instanceof MeprDrm) {
      $e->evt_id = $obj->rec->id;
      $e->evt_id_type = self::$drm_str;
    }
    else { return; }

    $e->store();
  }

  /** Get the latest object for a given event */
  public static function latest($event) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("
      SELECT id
        FROM {$mepr_db->events}
       WHERE event=%s
       ORDER BY id DESC
       LIMIT 1
    ", $event);

    if(($id = $wpdb->get_var($q))) {
      return new MeprEvent($id);
    }

    return false;
  }

  /** Get the tablename for the specific type of event */
  public static function get_tablename($event_type) {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    if ( $event_type == MeprEvent::$users_str ) {
      return $wpdb->users;
    }
    else if ( $event_type == MeprEvent::$transactions_str ) {
      return $mepr_db->transactions;
    }
    else if ( $event_type == MeprEvent::$subscriptions_str ) {
      return $mepr_db->subscriptions;
    }
  }

  /** Gets info from app/data/events.php if it exists.
   *
   * @return associative array if found or false if not found
   */
  private function event_info() {
    $event_data = require(MEPR_DATA_PATH . '/events.php');

    if(isset($event_data[$this->event])) {
      return $event_data[$this->event];
    }

    return false;
  }

  /** Uses app/data/events.php to determine if the current event is
   * unique -- if true, only one row can be stored for a given event,
   * evt_id & evt_id_type.
   *
   * @return true/false
   */
  private function is_unique() {
    $event_info = $this->event_info();
    return (false !== $event_info && isset($event_info->unique) && $event_info->unique);
  }

  /** Copy an existing event id & args if the event is unique and another
   * event record with the same event, evt_id & evt_id_type already exists.
   *
   * @return void
   */
  private function use_existing_if_unique() {
    if($this->is_unique()) {
      $existing_event = self::get_one_by_event_and_evt_id_and_evt_id_type($this->event, $this->evt_id, $this->evt_id_type);
      if(!empty($existing_event)) {
        $this->id = $existing_event->id;
        $this->args = $existing_event->args;
      }
    }
  }

  /** Get the latest object for a given event and elapsed days */
  public static function latest_by_elapsed_days( $event, $elapsed_days ) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("
      SELECT id
        FROM {$mepr_db->events}
       WHERE event=%s
       AND created_at >= '%s' - interval %d day
       ORDER BY id DESC
       LIMIT 1
    ", $event, MeprUtils::db_now(), $elapsed_days );

    if(($id = $wpdb->get_var($q))) {
      return new MeprEvent($id);
    }

    return false;
  }
} //End class
