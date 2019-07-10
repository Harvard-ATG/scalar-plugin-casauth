<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';
require_once dirname(__FILE__).'/casauth_model.php';
require_once dirname(__FILE__).'/casauth_exception.php';

/**
 * Class Casauth
 *
 * Scalar Plugin that implements CAS authentication.
 *
 * @author Arthur Barrett <abarrett@fas.harvard.edu>
 */
class Casauth_pi {

    /**
     * @var string The machine-readable name of the plugin. Used to construct file paths.
     */
    public $plugin_name = "casauth";

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

    /**#@+
     * @var string Login states.
     */
    const LOGIN_STATE_SELECT = "select";
    const LOGIN_STATE_CAS = "cas";
    const LOGIN_STATE_REGKEY = "regkey";
    const LOGIN_STATE_DEFAULT = "default";
    /**#@-*/


    /**
     * Casauth constructor.
     *
     * Loads configuration from config.ini or throw an exception.
     *
     * @throws Exception
     */
    public function __construct() {
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
        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $login_type = isset($_GET['state']) ? $_GET['state'] : self::LOGIN_STATE_SELECT;
                break;
            case 'POST':
            default:
                $login_type = isset($_GET['state']) ? $_GET['state'] : self::LOGIN_STATE_DEFAULT;
                break;
        }

        switch($login_type) {
            case self::LOGIN_STATE_SELECT:
                $this->action_login_select();
                break;
            case self::LOGIN_STATE_CAS:
                $this->action_login_cas();
                break;
            case self::LOGIN_STATE_REGKEY:
                $this->action_login_regkey();
                break;
            case self::LOGIN_STATE_DEFAULT:
            default:
                $this->ci->login();
                break;
        }
    }

    /**
     * Called by system controller's _remap() method.
     * This is intended to override the system::logout() method.
     *
     * TODO: Logout is never called because scalar's MY_Controller does it in __construct()
     *       and then immediately redirects, so the controller logout() method is never actually called.
     *       We need to find a way to resolve this so that we can logout from CAS too.
     */
    public function hook_system_logout() {
        $this->phpCAS();
        phpCas::logout();
        $this->ci->logout();
    }

    /**
     * Called by the system controller's _remap() method.
     * This is intended to provide a separate endpoint for the CAS server
     * to call back to with the service ticket.
     */
    public function hook_system_cas_login() {
        $this->action_login_cas();
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
    public function action_login_select() {
        $redirect_url = isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '';
        $default_login_url = confirm_slash(base_url())."system/login?state=".self::LOGIN_STATE_DEFAULT."&redirect_url=" . urlencode($redirect_url);
        $cas_login_url = confirm_slash(base_url())."system/login?state=".self::LOGIN_STATE_CAS;
        $cas_button_text = $this->config['cas_button_text'];
        include(dirname(__FILE__).'/login_select.php');
    }

    /**
     * Prompt for registration key before authenticating with CAS.
     *
     * This should display an HTML page that prompts for a registration key,
     * which is the same key that would be required to signup with an email/password.
     */
    public function action_login_regkey() {
        $register_keys = $this->ci->config->item('register_key');
        $action_url = $_SERVER['REQUEST_URI'];

        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                include(dirname(__FILE__) . '/registration_key.php');
                exit;
            case 'POST':
                $registration_key = $_POST['registration_key'];
                $is_allowed = empty($register_keys) || in_array($registration_key, $register_keys);
                if($is_allowed) {
                    $session_data = array("{$this->plugin_name}_registration_key" => $registration_key);
                    $this->ci->session->set_userdata($session_data);
                    $this->_redirect(self::LOGIN_STATE_CAS);
                    exit;
                } else {
                    $has_error = true;
                    $error_msg = "Invalid registration key: $registration_key";
                    include(dirname(__FILE__) . '/registration_key.php');
                    exit;
                }
                break;
            default:
                throw new Casauth_Exception("Invalid request method: ".$_SERVER['REQUEST_METHOD']);
                break;
        }
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
    public function action_login_cas() {
        // Handle CAS authentication flow
        // See also: https://apereo.github.io/cas/5.1.x/protocol/CAS-Protocol-Specification.html
        try {
            $this->_phpCAS_init();
            $this->_phpCAS_debug();
            phpCAS::forceAuthentication();
        } catch(CAS_Exception $e) {
            error_log($e->getMessage());
            show_error($e->getMessage());
        }

        // Authorize user with Scalar (check registration key)
        list($authorized, $registration_key) = $this->authorize();
        if(!$authorized) {
            if($registration_key === NULL) {
                $this->_redirect(self::LOGIN_STATE_REGKEY);
            } else {
                show_error("Access denied. Registration key [$registration_key] is not valid.", 403);
            }
        }

        // Authenticate user with Scalar (create/link to account)
        try {
            list($auth_success, $user_id) = $this->authenticate();
            if($auth_success) {
                $scalaruser = $this->ci->users->get_by_user_id($user_id);
                if($scalaruser) {
                    $this->_login($scalaruser);
                } else {
                    show_404("Login failed. Scalar user $user_id not found!");
                }
            } else {
                show_error("Access denied. You are not authorized to access this site.", 403);
            }
        } catch(Casauth_Exception $e) {
            error_log($e->getMessage());
            show_error("Login failed. Error: ".$e->getMessage());
        }
    }

    /**
     * Authenticates CAS user.
     *
     * Given a successful CAS authentication, when attributes are provided (e.g. name, email),
     * then those attributes can be used to authenticate the user in Scalar.
     *
     * The email provided by CAS will be used to link to an existing Scalar account on the first
     * login, otherwise a new Scalar account will be created automatically.
     *
     * Note that this DOES NOT perform the actual login (e.g update the session).
     *
     * @return array Returns a 2-element array: (boolean, string)
     */
    public function authenticate() {
        $attributes = phpCas::getAttributes();
        $cas_id = $attributes[Casauth_model::$cas_id_attribute];
        error_log("authenticate(): cas_id: $cas_id attributes:".var_export($attributes,1));

        // Save CAS User attributes
        $this->model->save_user($attributes);
        $casuser = $this->model->find_by_cas_id($cas_id);
        if(!$casuser) {
            throw new Casauth_Exception("Error retrieving CAS user from database");
        }
        $this->model->update_last_login($cas_id);

        // Deny CAS User if they are considered inactive
        if(!$casuser['is_active']) {
            return array(false, -1);
        }

        // Link CAS User to a Scalar account, or create a Scalar account if necessary
        if(!$casuser['user_id']) {
            $existing_scalaruser = $this->ci->users->get_by_email($casuser['email']);
            if($existing_scalaruser) {
                $this->model->link_to_scalar_user($cas_id, $existing_scalaruser->user_id);
                return array(true, $existing_scalaruser->user_id);
            }
            $registered_scalaruser = $this->_register_account($casuser);
            return array(true, $registered_scalaruser->user_id);
        }

        // If we get here, user has been successfully authenticated with a Scalar account
        return array(true, $casuser['user_id']);
    }

    /**
     * Authorizes CAS user.
     *
     * If a registration key is required for new users, this method will check that it is valid.
     *
     * @return array Returns a 2-element array: (boolean, string)
     *               When true, returns a success message
     *               When false, returns either the invalid registration key or null.
     */
    public function authorize() {
        $attributes = phpCas::getAttributes();
        $cas_id = $attributes[Casauth_model::$cas_id_attribute];
        error_log("authorize(): cas_id: $cas_id attributes:".var_export($attributes,1));

        $casuser = $this->model->find_by_cas_id($cas_id);
        if($casuser) {
            return array(true, "User already registered; registration key not required");
        }

        $register_keys = $this->ci->config->item('register_key');
        if(empty($register_keys)) {
            return array(true, "Registration keys not configured");
        }
        
        $registration_key = $this->ci->session->userdata("{$this->plugin_name}_registration_key");
        if($registration_key) {
            if(!in_array($registration_key, $register_keys)) {
                return array(false, $registration_key);
            }
        } else {
            return array(false, null);
        }
        
        return array(true, $registration_key);
    }

    /**
     * Registers a new Scalar user account.
     *
     * Note: the password is disabled by default.
     *
     * @param $casuser
     * @return mixed Returns false if the user is not found, otherwise an array.
     */
    protected function _register_account($casuser) {
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
    protected function _login($scalaruser) {
        $login_basename = confirm_slash(base_url());
        $scalaruser->is_logged_in = true;
        $this->ci->session->set_userdata(array($login_basename => (array) $scalaruser));
        header("Location: ".$login_basename, TRUE);
        exit();
    }

    /**
     * Redirects to a plugin-provided URL.
     */
    protected function _redirect($login_state) {
        header('Location: '.confirm_slash(base_url())."system/login?state=$login_state");
        exit;
    }

    /**
     * Initialize the phpCas client.
     */
    protected function _phpCAS_init() {
        phpCAS::setDebug();
        phpCAS::setVerbose(true);
        phpCAS::client(CAS_VERSION_2_0, $this->config['cas_host'], (int)$this->config['cas_port'], $this->config['cas_context']);
        phpCAS::setNoCasServerValidation(); // TODO: Fix this for production
    }

    /**
     * Set debugging URLs for phpCAS (local development)
     */
    protected function _phpCAS_debug() {
        // For debugging/testing only with local mock cas server (https://github.com/veo-labs/cas-server-mock)
        // Using this to explicitly set HTTP URLs
        // See also https://github.com/apereo/phpCAS/issues/27
        if($this->config['cas_debug']) {
            $cas_server = $this->config['cas_debug_server'];
            $service = confirm_slash(base_url())."system/cas_login";
            phpCAS::setServerLoginURL("http://$cas_server/login?service=".urlencode($service));
            phpCAS::setServerServiceValidateURL("http://$cas_server/serviceValidate");
        }
    }

}
