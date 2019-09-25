<?php

class Casauth_attributes {

    /**#@+
     * @var string CAS attribute
     */
    public $id_attribute = 'eduPersonPrincipalName'; // A unique but opaque identifier for a user; generally of the form user@domain
    public $email_attribute = 'mail';
    public $fullname_attribute = 'displayName';
    /**#@-*/

    /**
     * @var array Required CAS attributes
     */
    public $required_attributes = array('eduPersonPrincipalName', 'mail', 'displayName');

    /**
     * @var array Holds attributes returned by CAS
     */
    public $attributes = array();

    /**
     * Casauth_attributes constructor.
     *
     * @param $attributes
     */
    public function __construct($attributes=null) {
        if(is_array($attributes)) {
            $this->attributes = $attributes;
        }
    }

    /**
     * String representation of the object.
     *
     * @return string
     */
    public function __toString () {
        return var_export($this->attributes,1);
    }

    /**
     * Returns the CAS user ID
     *
     * @return string
     */
    public function get_cas_id() {
        if(!isset($this->attributes[$this->id_attribute])) {
            return '';
        }
        return trim($this->attributes[$this->id_attribute]);
    }

    /**
     * Set the CAS user ID.
     *
     * @param string $cas_id
     */
    public function set_cas_id($cas_id) {
        $this->attributes[$this->id_attribute] = $cas_id;
        return $this;
    }

    /**
     * Returns the CAS user email address
     *
     * @return string
     */
    public function get_email() {
        if(!isset($this->attributes[$this->email_attribute])) {
            return '';
        }
        return trim($this->attributes[$this->email_attribute]);
    }

    /**
     * Set the CAS user email.
     *
     * @param string $cas_email
     */
    public function set_email($cas_email) {
        $this->attributes[$this->email_attribute] = $cas_email;
        return $this;
    }

    /**
     * Returns the CAS user full name
     *
     * @return string
     */
    public function get_fullname() {
        if(!isset($this->attributes[$this->fullname_attribute])) {
            return '';
        }
        return trim($this->attributes[$this->fullname_attribute]);
    }

    /**
     * Set the CAS user full name.
     *
     * @param string $fullname
     */
    public function set_fullname($fullname) {
        $this->attributes[$this->fullname_attribute] = $fullname;
        return $this;
    }

    /**
     * Validates the attributes.
     *
     * @throws Casauth_Exception
     */
    public function validate() {
        $this->check($this->required_attributes, $this->attributes);
    }

    /**
     * Checks if required attributes are present.
     * Throws an exception if any required attributes are missing.
     *
     * @param $attributes
     * @throws Casauth_Exception
     */
    public function check($required_attributes, $attributes) {
        $actual_attributes = array_keys($attributes);
        $missing_attributes = array_diff($required_attributes, $actual_attributes);
        $has_attributes = empty($missing_attributes);
        if(!$has_attributes) {
            $errmsg = "Missing required CAS attributes: ".implode(",", $missing_attributes);
            throw new Casauth_Exception($errmsg);
        }
    }
}