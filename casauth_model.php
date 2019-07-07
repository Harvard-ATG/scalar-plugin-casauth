<?php
class Casauth_model extends CI_Model {

    protected $table_name = 'scalar_db_casauth';

    public static $required_attributes = array('eduPersonPrincipleName', 'mail', 'displayName');

    public $cas_id   = '';
    public $email = '';
    public $fullname = '';
    public $created    = '';
    public $user_id = '';

    function __construct() {
        parent::__construct();
        $this->load->database();
    }

    protected function _hasAttributes($attributes) {
        $expected_keys = Casauth_model::$required_attributes;
        $actual_keys = array_keys($attributes);
        $diff = array_diff($actual_keys, $expected_keys);
        return empty($diff);
    }

    public function add_new_cas_entry($attributes) {
        if(!$this->_hasAttributes($attributes)) {
            throw new Exception("Missing required CAS attributes");
        }
        $entry = $this->find_cas_entry($attributes);
        if(!$entry) {
            $this->insert_cas_entry($attributes);
        }
    }

    public function link_cas_entry_to_scalar_user($cas_id, $scalar_user_id) {
        $this->db->where('cas_id', $cas_id);
        $this->db->update($this->table_name, array('user_id' => $scalar_user_id));
    }

    public function find_cas_entry($attributes) {
        $cas_id = $attributes['eduPersonPrincipleName'];
        $query = $this->db->query("SELECT cas_id, email, fullname, created, user_id FROM {$this->table_name} WHERE cas_id = ?", array($cas_id));
        $result = $query->result_array();
        if(empty($result)) {
            return false;
        }
        return $result[0];
    }

    public function insert_cas_entry($attributes) {
        $now = date("Y-m-d H:i:s");
        $data = array(
            'cas_id' => $attributes['eduPersonPrincipleName'],
            'email' => $attributes['mail'],
            'fullname' => $attributes['displayName'],
            'created' => $now,
        );
        $this->db->insert($this->table_name, $data);
    }
}