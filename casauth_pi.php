<?php

require_once dirname(__FILE__).'/autoload.php';

/**
 * Class Casauth
 *
 * Scalar Plugin that implements CAS authentication.
 *
 * @author Arthur Barrett <abarrett@fas.harvard.edu>
 */
class Casauth {

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
    }

    /**
     * Initialize plugin.
     */
    public function init($options=array()) {
        if(isset($options['config'])) {
            $this->config = $options['config'];
        } else {
            $this->config = parse_ini_file(dirname(__FILE__).'/config.ini');
            if($this->config === FALSE) {
                throw new Casauth_Exception("Casauth plugin misconfigured: config.ini required");
            }
        }

        if(isset($options['model'])) {
            $this->model = $options['model'];
        } else {
            $this->model = new Casauth_model();
        }

        if(isset($options['ci'])) {
            $this->ci =& $options['ci'];
        } else {
            $CI =& get_instance(); // reference to main CI_Controller
            $this->ci = $CI;
        }
        return $this;
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
        $default_login_url = $this->_get_base_url()."system/login?state=".self::LOGIN_STATE_DEFAULT."&redirect_url=" . urlencode($redirect_url);
        $cas_login_url = $this->_get_base_url()."system/login?state=".self::LOGIN_STATE_CAS."&redirect_url=" . urlencode($redirect_url);
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
        // Save redirect URL if one is present
        if(isset($_GET['redirect_url'])) {
            $session_data = array("{$this->plugin_name}_redirect_url" => $_GET['redirect_url']);
            $this->ci->session->set_userdata($session_data);
        }

        // Handle CAS authentication flow
        // See also: https://apereo.github.io/cas/5.1.x/protocol/CAS-Protocol-Specification.html
        $authenticated_attributes = null;
        try {
            $this->_phpCAS_init();
            phpCAS::forceAuthentication();
            $authenticated_attributes = phpCas::getAttributes();
            error_log("authenticated attributes: ".var_export($authenticated_attributes,1));
        } catch(CAS_Exception $e) {
            error_log($e->getMessage());
            show_error($e->getMessage());
        }

        try {
            // Validate attributes
            $attributes = new Casauth_attributes($authenticated_attributes);
            $attributes->validate();

            // Preauthorize user with Scalar (check registration key)
            list($preauthorized, $reason) = $this->preauthorize($attributes);
            error_log("preauthorize(): ".var_export(array($preauthorized, $reason),1));
            if(!$preauthorized) {
                if($reason === NULL) {
                    $this->_redirect(self::LOGIN_STATE_REGKEY);
                } else {
                    show_error("Access denied. Reason: $reason", 403);
                }
            }

            // Authenticate user with Scalar (create/link to account)
            list($auth_success, $user_id) = $this->authenticate($attributes);
            if($auth_success) {
                $scalaruser = $this->ci->users->get_by_user_id($user_id);
                if($scalaruser) {
                    $this->_login($scalaruser);
                } else {
                    show_404("Scalar user $user_id not found!");
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
     * Pre-Authorizes a CAS user.
     *
     * This method is used to verify whether or not the user is permitted to register an account. It is expected
     * to be called after the user has been redirected back to Scalar from the CAS server.
     *
     * The user is considered verified when any of the following is true:
     *
     * 1) The user has previously authenticated via CAS. True if there is a record linking their CAS ID
     *    (e.g. eduPersonPrincipalName) to a scalar user ID.
     * 2) An account already exists in Scalar, either because they registered themselves or someone created it for them.
     *    True if the CAS-supplied email matches a Scalar user account email.
     * 3) The user supplies the correct registration key, or no registration key is required.
     *
     * Note that the registration key mechanism is the same mechanism used by the standard Scalar signup
     * form (email/password).
     *
     * @param Casauth_attributes $attributes The attributes returned by the CAS server
     * @return array Returns a 2-element array: (boolean, string)
     *               When true, returns a success message
     *               When false, returns either the invalid registration key or null.
     */
    public function preauthorize($attributes) {
        $cas_id = $attributes->get_cas_id();
        $cas_email = $attributes->get_email();

        $casuser = $this->model->find_by_cas_id($cas_id);
        if($casuser) {
            return array(true, "User previously authenticated; registration key not required");
        }

        if($cas_email) {
            $scalaruser = $this->ci->users->get_by_email($cas_email);
            if($scalaruser) {
                return array(true, "User already exists with email; registration key not required");
            }
        }

        $register_keys = $this->ci->config->item('register_key');
        if(empty($register_keys)) {
            return array(true, "Registration keys not configured");
        }

        $registration_key = $this->ci->session->userdata("{$this->plugin_name}_registration_key");
        if($registration_key) {
            if(!in_array($registration_key, $register_keys)) {
                return array(false, "Invalid registration key: $registration_key");
            }
        } else {
            return array(false, null);
        }

        return array(true, $registration_key);
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
     * @param Casauth_attributes $attributes The attributes returned by the CAS server
     * @return array Returns a 2-element array: (boolean, string)
     */
    public function authenticate($attributes) {
        $cas_id = $attributes->get_cas_id();
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
            error_log("authenticate(): cas_id: $cas_id denied because inactive");
            return array(false, -1);
        }

        // Link CAS User to a Scalar account, or create a Scalar account if necessary
        if(!$casuser['user_id']) {
            $existing_scalaruser = $this->ci->users->get_by_email($casuser['email']);
            if($existing_scalaruser) {
                $this->model->link_to_scalar_user($cas_id, $existing_scalaruser->user_id);
                return array(true, $existing_scalaruser->user_id);
            } else {
                $registered_scalaruser = $this->_register_account($casuser);
                if($registered_scalaruser) {
                    $this->model->link_to_scalar_user($cas_id, $registered_scalaruser->user_id);
                    return array(true, $registered_scalaruser->user_id);
                } else {
                    error_log("authenticate(): failed to register account for cas_id: $cas_id");
                    return array(false, -1);
                }
            }
        }

        // If we get here, user has been successfully authenticated with a Scalar account
        return array(true, $casuser['user_id']);
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
        $redirect_url = $this->ci->session->userdata("{$this->plugin_name}_redirect_url");
        $login_base_url = $this->_get_base_url();

        $scalaruser->is_logged_in = true;
        $this->ci->session->set_userdata($login_base_url, (array) $scalaruser);

        if(Casauth_utils::is_subdomain($redirect_url, $login_base_url)) {
            $redirect_host = parse_url($redirect_url, PHP_URL_HOST);
            $redirect_base_url = Casauth_utils::subdomain_login_basename($login_base_url, $redirect_host);
            $this->ci->session->set_userdata($redirect_base_url, (array) $scalaruser);
        }

        if($redirect_url) {
            $this->ci->session->unset_userdata("{$this->plugin_name}_redirect_url");
            header("Location: ".$redirect_url, TRUE);
        } else {
            header("Location: ".$login_base_url, TRUE);
        }
        exit;
    }

    /**
     * Redirects to a plugin-provided URL.
     */
    protected function _redirect($login_state) {
        header('Location: '.$this->_get_base_url()."system/login?state=$login_state");
        exit;
    }

    /**
     * Returns the base url of the scalar system.
     *
     * @return string
     */
    protected function _get_base_url($uri='') {
        return confirm_slash(base_url($uri));
    }

    /**
     * Initialize the phpCas client.
     */
    protected function _phpCAS_init() {
        phpCAS::setDebug();
        phpCAS::setVerbose(true);
        phpCAS::client(CAS_VERSION_2_0, $this->config['cas_host'], (int)$this->config['cas_port'], $this->config['cas_context']);

        $service_url = null;
        if(isset($this->config['cas_service_url'])) {
            $service_url = $this->config['cas_service_url'];
        } else if(getenv('SCALAR_CASAUTH_SERVICE_URL')) {
            $service_url = getenv('SCALAR_CASAUTH_SERVICE_URL');
        }

        if($service_url) {
            phpCAS::setFixedServiceURL($service_url);
        }

        phpCAS::setNoCasServerValidation(); // TODO: Fix this for production

        if(isset($this->config['cas_debug'])) {
            // For debugging/testingwith local mock cas server (https://github.com/veo-labs/cas-server-mock)
            // Using this to explicitly set HTTP URLs. See also https://github.com/apereo/phpCAS/issues/27
            $cas_server = $this->config['cas_debug_server'];
            $service = $this->_get_base_url()."system/cas_login";
            phpCAS::setServerLoginURL("http://$cas_server/login?service=".urlencode($service));
            phpCAS::setServerServiceValidateURL("http://$cas_server/serviceValidate");
        }
    }

}
