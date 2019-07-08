<?php
require_once dirname(__FILE__).'/casauth_exception.php';

class Casauth_model extends CI_Model {

    protected $table_name = 'scalar_db_casauth';

    public static $cas_id_attribute = 'eduPersonPrincipleName';
    public static $email_attribute = 'mail';
    public static $fullname_attribute = 'displayName';

    public $cas_id   = '';
    public $email = '';
    public $fullname = '';
    public $created = '';
    public $is_active = '1';
    public $user_id = '';
    public $required_attributes = array();

    function __construct() {
        parent::__construct();

        $this->required_attributes = array(
            self::$cas_id_attribute,
            self::$email_attribute,
            self::$fullname_attribute,
        );

        $this->load->database();
    }

    public function check_attributes($attributes) {
        $actual_attributes = array_keys($attributes);
        $missing_attributes = array_diff($this->required_attributes, $actual_attributes);
        $has_attributes = empty($missing_attributes);
        if(!$has_attributes) {
            $errmsg = "Missing required CAS attributes: ".implode(",", $missing_attributes);
            throw new CasauthException($errmsg);
        }
    }

    public function save_user($attributes) {
        $this->check_attributes($attributes);
        $casuser = $this->find_by_cas_id($attributes[self::$cas_id_attribute]);
        if(!$casuser) {
            $this->insert_user($attributes);
        }
    }

    public function insert_user($attributes) {
        $now = date("Y-m-d H:i:s");
        $cas_id = trim($attributes[self::$cas_id_attribute]);
        $email = trim($attributes[self::$email_attribute]);
        $fullname = trim($attributes[self::$fullname_attribute]);

        if(!$cas_id) {
            throw new Casauth_Exception("CAS attribute ".self::$cas_id_attribute. " is empty or invalid.");
        }
        if(!$email) {
            throw new Casauth_Exception("CAS attribute".self::$email_attribute. " is empty or invalid");
        }
        if(!$fullname) {
            throw new Casauth_Exception("CAS attribute".self::$fullname_attribute. " is empty or invalid");
        }

        $data = array(
            'cas_id' => $cas_id,
            'email' => $email,
            'fullname' => $fullname,
            'created' => $now,
        );
        $this->db->insert($this->table_name, $data);

        return $data;
    }

    public function link_to_scalar_user($cas_id, $scalar_user_id) {
        $this->db->where('cas_id', $cas_id);
        $this->db->update($this->table_name, array('user_id' => $scalar_user_id));
    }

    public function find_by_cas_id($cas_id) {
        $query = $this->db->query("SELECT cas_id, email, fullname, created, user_id, is_active FROM {$this->table_name} WHERE cas_id = ?", array($cas_id));
        $result = $query->result_array();
        if(empty($result)) {
            return false;
        }
        return $result[0];
    }

}