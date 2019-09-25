<?php

class Casauth_model extends CI_Model {

    /**
     * @var string The Scalar table name that holds CAS user data.
     */
    protected $table_name = 'plugin_casauth';

    /**
     * @var boolean Set to true to enable debugging
     */
    protected $debug = false;

    /**#@+
     * @var string Active record field.
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
        $this->load->database();
        $this->db->db_debug = $this->db->db_debug || $this->debug;
        $this->table_name = $this->db->dbprefix.$this->table_name; // e.g. prefix table name with "scalar_db_"
    }

    /**
     * Saves a CAS user.
     *
     * @param Casauth_attributes $attributes
     * @throws Casauth_Exception
     */
    public function save_user($attributes) {
        error_log("Saving user: ".var_export($attributes,1));
        $casuser = $this->find_by_cas_id($attributes->get_cas_id());
        if(!$casuser) {
            $this->insert_user($attributes);
        }
    }

    /**
     * Inserts a new CAS user.
     *
     * @param Casauth_attributes $attributes
     * @return array
     * @throws Casauth_Exception
     */
    public function insert_user($attributes) {
        $now = date("Y-m-d H:i:s");
        $cas_id = $attributes->get_cas_id();
        $email = $attributes->get_email();
        $fullname = $attributes->get_fullname();

        if(!$cas_id) {
            throw new Casauth_Exception("CAS attribute ID is empty or invalid.");
        }
        if(!$email) {
            throw new Casauth_Exception("CAS attribute email is empty or invalid");
        }
        if(!$fullname) {
            throw new Casauth_Exception("CAS attribute fullname is empty or invalid");
        }

        $data = array(
            'cas_id' => $cas_id,
            'email' => $email,
            'fullname' => $fullname,
            'created' => $now,
        );
        error_log("Inserting user: ".var_export($data,1));
        $this->db->insert($this->table_name, $data);

        return $data;
    }

    /**
     * Links a CAS user to a Scalar user ID.
     *
     * @param $cas_id
     * @param $scalar_user_id
     */
    public function link_to_scalar_user($cas_id, $scalar_user_id) {
        error_log("Linking $cas_id to scalar user $scalar_user_id");
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