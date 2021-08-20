<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseMetaModel extends MeprBaseModel {
  private $object_type;
  private $meta_table;

  public function __construct($obj = null) {
    $this->object_type = $this->object_type();
    $this->meta_table  = $this->object_type . '_meta';
  }
  /**
   * Get a metadata field for a given object
   *
   * Mimics the behavior of 'get_{type}_meta'
   *
   * @param string $meta_key   Meta key
   * @param bool   $single     Return a single value or not
   */
  public function get_meta($meta_key, $single=false) {
    $mepr_db = MeprDb::fetch();

    return $mepr_db->get_metadata($mepr_db->{$this->meta_table}, "{$this->object_type}_id", $this->id, $meta_key, $single);
  }

  /**
   * Add a metadata field for a given object
   *
   * Mimics the behavior of 'add_{type}_meta'
   *
   * @param string $meta_key   Meta key
   * @param string $meta_value Meta value. Will be serialized if an object or an array.
   * @param string $unique     Value should be unique for the meta_key/object_id
   *
   * @return int|false The meta ID on success, false on failure.
   */
  public function add_meta($meta_key, $meta_value, $unique=false) {
    $mepr_db = MeprDb::fetch();

    return $mepr_db->add_metadata($mepr_db->{$this->meta_table}, "{$this->object_type}_id", $this->id, $meta_key, $meta_value, $unique);
  }

  /**
   * Update a metadata field for the object
   *
   * Mimics the behavior of 'update_{type}_meta'
   *
   * @param string $meta_key   Meta key
   * @param string $meta_value Meta value. Will be serialized if an object or an array.
   * @param string $prev_value Prev value.
   * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
   */
  public function update_meta($meta_key, $meta_value, $prev_value='') {
    $mepr_db = MeprDb::fetch();

    return $mepr_db->update_metadata($mepr_db->{$this->meta_table}, "{$this->object_type}_id", $this->id, $meta_key, $meta_value, $prev_value);
  }

  /**
   * Delete metadata for the specified object & meta_key.
   *
   * @global wpdb $wpdb WordPress database abstraction object.
   *
   * @param string $meta_key   Metadata key
   * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
   *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
   *                           Pass `null, `false`, or an empty string to skip this check. (For backward compatibility,
   *                           it is not possible to pass an empty string to delete those entries with an empty string
   *                           for a value.)
   *
   * @return bool True on successful delete, false on failure.
   */
  public function delete_meta($meta_key, $meta_value = '') {
    $mepr_db = MeprDb::fetch();

    return $mepr_db->delete_metadata($mepr_db->{$this->meta_table}, "{$this->object_type}_id", $this->id, $meta_key, $meta_value);
  }

  /**
  * Singular object type from class
  * @return string Lowercased classname without the Mepr
  */
  private function object_type() {
    $mepr_class = get_class($this);

    return strtolower(str_replace('Mepr', '', $mepr_class));
  }
}
