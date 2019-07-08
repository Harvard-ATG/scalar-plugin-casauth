<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';
require_once dirname(__FILE__).'/casauth_model.php';
require_once dirname(__FILE__).'/casauth_exception.php';

/**
 * Class Casauth
 *
 * Scalar Plugin that implements CAS authentication.
 */
class Casauth {

    /**
     * @var string The machine-readable name of the plugin. Used to construct file paths.
     */
    public $plugin_name = "";

    /**
     * @var string The filesystem path to the plugin.
     */
    public $plugin_path = "";

    /**
     * @var array Contains plugin configuration such as CAS server info.
     */
    public $config = array();

    /**
     * @var object Holds controller instance via get_instance()
     */
    public $ci = null;

    /**
     * @var object Holds CI_Model instance
     */
    public $model = null;

    /**
     * Casauth constructor.
     *
     * Loads configuration from config.ini or throw an exception.
     *
     * @throws Exception
     */
    public function __construct() {
        $this->plugin_name = strtolower(get_class($this));
        $this->plugin_path = "system/application/plugins/{$this->plugin_name}";

        $this->config = parse_ini_file(dirname(__FILE__).'/config.ini');
        if($this->config === FALSE) {
            throw new Exception("Casauth plugin misconfigured: config.ini required");
        }

        $this->model = $this->get_model();
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        $CI =& get_instance();
        $this->ci = $CI;
    }

    /**
     * Get an instance of the model.
     */
    public function get_model() {
        return new Casauth_model();
    }

    /**
     * Called by the system controller's _remap() method.
     * This is intended to override the system::login() method.
     */
    public function hook_system_login() {
        if(isset($_GET['type'])) {
            $login_type = $_GET['type'];
        } else {
            $login_type = 'select';
        }

        switch($login_type) {
            case 'select':
                $this->select_login();
                break;
            case 'cas':
                $this->cas_login();
                break;
            case 'default':
            default:
                $this->ci->login();
                break;
        }
    }

    /**
     * Called by the system controller's _remap() method.
     * This is intended to provide a separate endpoint for the CAS server
     * to call back to with the service ticket.
     */
    public function hook_system_cas_login() {
        $this->cas_login();
    }

    /**
     * Select the login type.
     *
     * This should display an HTML page with two choices to login:
     *
     * 1) Login with email/password
     * 2) Login with CAS
     *
     * Clicking on the link should direct the user to the appropriate login page.
     */
    public function select_login() {
        $redirect_url = isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '';
        $default_login_url = confirm_slash(base_url())."system/login?type=default&redirect_url=" . urlencode($redirect_url);
        $cas_login_url = confirm_slash(base_url())."system/login?type=cas&redirect_url=" . urlencode($redirect_url);
        $cas_button_text = $this->config['cas_button_text'];
        include(dirname(__FILE__).'/login_select.php');
    }

    /**
     * Cas Login Method.
     *
     * This method should be called:
     *
     * 1) To initiate authentication (e.g. send users to CAS server to login).
     * 2) To process a returning user who has logged in with the CAS server.
     *
     * These steps are handled by the phpCas Library (https://github.com/apereo/phpCAS).
     *
     * After a user has been authenticated by phpCas (e.g. service ticket validated),
     * we can proceed to authenticate the user with Scalar itself.
     *
     * Assuming a user has an existing account in Scalar, this method will try to
     * link the CAS login to the Scalar account using the email provided in the CAS attributes.
     * If it's not possible to link to an existing account, then a new account will
     * be registered automatically, and the user will be logged in.
     */
    public function cas_login() {
        // Handle CAS authentication flow
        // See also: https://apereo.github.io/cas/5.1.x/protocol/CAS-Protocol-Specification.html
        try {
            phpCAS::setDebug();
            phpCAS::setVerbose(true);
            phpCAS::client(CAS_VERSION_2_0, $this->config['cas_host'], (int)$this->config['cas_port'], $this->config['cas_context']);
            $this->_debugAuthenticate();
            phpCAS::forceAuthentication();
        } catch(CAS_Exception $e) {
            error_log($e->getMessage());
            show_error($e->getMessage());
        }

        // If we get here, the user has been authenticated with the CAS server,
        // and we can proceed to log them in to Scalar.
        try {
            list($auth_success, $user_id) = $this->authenticate();
            if($auth_success) {
                $scalaruser = $this->ci->users->get_by_user_id($user_id);
                if($scalaruser) {
                    $this->login_and_redirect($scalaruser);
                } else {
                    show_404("Login failed. Scalar user $user_id not found!");
                }
            } else {
                show_error("Access denied. You are not authorized to access this site.", 403);
            }
        } catch(CasauthException $e) {
            error_log($e->getMessage());
            show_error($e->getMessage());
        }
    }

    /**
     * Authenticates CAS user.
     *
     * Given a successful CAS authentication, when attributes are provided (e.g. name, email),
     * then those attributes can be used to authenticate the user in Scalar.
     *
     * The email will be used to link to an existing Scalar user account on the first login,
     * otherwise a new account will be created.
     *
     * Note that this DOES NOT perform the actual login (e.g update the session).
     *
     * @return array Returns a 2-element array with a boolean to indicate a successful authentication,
     *               and the Scalar user_id if authentication was successful.
     */
    public function authenticate() {
        // Retrieve the attributes
        $attributes = phpCas::getAttributes();
        error_log("phpCas attributes:".var_export($attributes,1));

        // Ensure the CAS user has been added to the database.
        $this->model->save_user($attributes);
        $casuser = $this->model->find_by_cas_id($attributes[Casauth_model::$cas_id_attribute]);
        if(!$casuser) {
            throw new CasauthException("Error retrieving CAS login information");
        }
        $this->model->update_last_login($casuser['cas_id']);

        // Check if the CAS User is active and therefore allowed to proceed.
        // By default, new users are active.
        if(!$casuser['is_active']) {
            return array(false, -1);
        }

        // Check if the CAS User is linked to a Scalar User Account yet.
        // For a first time CAS login, there are two options on how to proceed:
        //      Option #1: link to an existing scalar user account by email
        //      Option #2: register a new scalar user account
        if(!$casuser['user_id']) {
            $existing_scalaruser = $this->ci->users->get_by_email($casuser['email']);
            if($existing_scalaruser) {
                $this->model->link_to_scalar_user($casuser['cas_id'], $existing_scalaruser->user_id);
                return array(true, $existing_scalaruser->user_id);
            }
            $registered_scalaruser = $this->register($casuser);
            return array(true, $registered_scalaruser->user_id);
        }

        // If we get here, the user has logged in before so we simply return the linked Scalar account.
        return array(true, $casuser['user_id']);
    }

    /**
     * Registers a new Scalar user account.
     *
     * Note: the password is disabled automatically to prevent password-based logins.
     *
     * @param $casuser
     * @return mixed Returns false if the user is not found, otherwise an array.
     */
    public function register($casuser) {
        $data = array(
            'email' => $casuser['email'],
            'fullname' => $casuser['fullname'],
            'password' => 'DISABLEDPASSWORD', // not a valid hash, so should prevent password logins
        );
        $this->ci->users->db->insert($this->ci->users->users_table, $data);
        return $this->ci->users->get_by_email($casuser['email']);
    }

    /**
     * Performs the actual login by updating the session and redirecting.
     *
     * @param $scalaruser
     */
    public function login_and_redirect($scalaruser) {
        $login_basename = confirm_slash(base_url());
        $scalaruser->is_logged_in = true;
        $this->ci->session->set_userdata(array($login_basename => (array) $scalaruser));
        header("Location: ".$login_basename, TRUE);
        exit();
    }

    // For debugging locally.
    private function _debugAuthenticate() {
        // For debugging/testing only with local mock cas server (https://github.com/veo-labs/cas-server-mock)
        // Using this to explicitly set HTTP URLs
        // See also https://github.com/apereo/phpCAS/issues/27
        $cas_server = "140.247.36.150:3004";
        $service = confirm_slash(base_url())."system/cas_login";
        phpCAS::setServerLoginURL("http://$cas_server/login?service=".urlencode($service));
        phpCAS::setServerServiceValidateURL("http://$cas_server/serviceValidate");
        phpCAS::setNoCasServerValidation();
    }

}