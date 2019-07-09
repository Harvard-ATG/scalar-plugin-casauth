<?php
require_once dirname(__FILE__).'/casauth_exception.php';

class Casauth_model extends CI_Model {

    /**
     * @var string The Scalar table name that holds CAS user data.
     */
    protected $table_name = 'scalar_db_casauth';

    /**
     * @var string Defines the attribute to use to identify the CAS user.
     */
    public static $cas_id_attribute = 'eduPersonPrincipleName'; // A unique but opaque identifier for a user; generally of the form user@domain

    /**
     * @var string Defines the attribute for the CAS user's email address.
     */
    public static $email_attribute = 'mail';

    /**
     * @var string Defines the attribute for the CAS user's full name.
     */
    public static $fullname_attribute = 'displayName';

    /**
     * @var array Holds the required attributes for creating a new CAS user record.
     */
    public $required_attributes = array();

    /**#@+
     * @var string Active record fields.
     */
    public $cas_id   = '';
    public $email = '';
    public $fullname = '';
    public $created = '';
    public $is_active = '1';
    public $user_id = '';
    public $last_login = '';
    /**#@-*/


    /**
     * Casauth_model constructor.
     */
    function __construct() {
        parent::__construct();

        $this->required_attributes = array(
            self::$cas_id_attribute,
            self::$email_attribute,
            self::$fullname_attribute,
        );

        $this->load->database();
    }

    /**
     * Saves a CAS user.
     *
     * @param $attributes
     * @throws Casauth_Exception
     */
    public function save_user($attributes) {
        $this->check_attributes($attributes);
        $casuser = $this->find_by_cas_id($attributes[self::$cas_id_attribute]);
        if(!$casuser) {
            $this->insert_user($attributes);
        }
    }

    /**
     * Inserts a new CAS user.
     *
     * @param $attributes
     * @return array
     * @throws Casauth_Exception
     */
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

    /**
     * Checks if required attributes are present.
     * Throws an exception if any required attributes are missing.
     *
     * @param $attributes
     * @throws Casauth_Exception
     */
    public function check_attributes($attributes) {
        $actual_attributes = array_keys($attributes);
        $missing_attributes = array_diff($this->required_attributes, $actual_attributes);
        $has_attributes = empty($missing_attributes);
        if(!$has_attributes) {
            $errmsg = "Missing required CAS attributes: ".implode(",", $missing_attributes);
            throw new Casauth_Exception($errmsg);
        }
    }

    /**
     * Links a CAS user to a Scalar user ID.
     *
     * @param $cas_id
     * @param $scalar_user_id
     */
    public function link_to_scalar_user($cas_id, $scalar_user_id) {
        $this->db->where('cas_id', $cas_id);
        $this->db->update($this->table_name, array('user_id' => $scalar_user_id));
    }

    /**
     * Updates the last_login time for a CAS user.
     *
     * @param $cas_id
     */
    public function update_last_login($cas_id) {
        $now = date("Y-m-d H:i:s");
        $this->db->where('cas_id', $cas_id);
        $this->db->update($this->table_name, array('last_login' => $now));
    }

    /**
     * Finds a CAS user by ID.
     *
     * @param $cas_id
     * @return mixed False if not found, otherwise an array with the resulting row.
     */
    public function find_by_cas_id($cas_id) {
        $query = $this->db->query("SELECT cas_id, email, fullname, created, user_id, is_active FROM {$this->table_name} WHERE cas_id = ?", array($cas_id));
        $result = $query->result_array();
        if(empty($result)) {
            return false;
        }
        return $result[0];
    }

}