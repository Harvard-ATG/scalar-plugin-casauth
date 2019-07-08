<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';
require_once dirname(__FILE__).'/casauth_model.php';
require_once dirname(__FILE__).'/casauth_exception.php';

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

        $this->model = new Casauth_model();
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        $CI =& get_instance();
        $this->ci = $CI;
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
     * Displays a page for the user to select the type of login.
     * The page contains links to either the default login page (email/password) or CAS login page.
     */
    public function select_login() {
        $redirect_url = isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '';
        $default_login_url = confirm_slash(base_url())."system/login?type=default&redirect_url=" . urlencode($redirect_url);
        $cas_login_url = confirm_slash(base_url())."system/login?type=cas&redirect_url=" . urlencode($redirect_url);
        $cas_button_text = $this->config['cas_button_text'];
        include(dirname(__FILE__).'/login_select.php');
    }

    /**
     * This method handles the CAS authentication flow.
     * Uses the phpCas library (https://github.com/apereo/phpCAS).
     *
     * This method will be called in two ways:
     *
     * 1) When a new authentication request is initiated. The user will be redirected to the CAS
     *    server by phpCAS, where the user will authenticate.
     *
     * 2) When a user is returning from the CAS server with a service ticket. The users's
     *    ticket will be validated by phpCAS.
     *
     * Assuming the user has been authenticated, the method will proceed to automatically
     * register the user (if necessary) and log them in to Scalar.
     */
    public function cas_login() {
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

        try {
            list($auth_success, $user_id) = $this->authenticate();
            if($auth_success) {
                $user = $this->ci->users->get_by_user_id($user_id);
                if($user) {
                    $this->login_and_redirect($user);
                } else {
                    show_404("Authenticated successfully, but error loading user data [user_id=$user_id]");
            }
            } else {
                show_error("Access denied. You are not authorized to access this site.", 403);
            }
        } catch(CasauthException $e) {
            error_log($e->getMessage());
            show_error($e->getMessage());
        }
    }

    public function authenticate() {
        $attributes = phpCas::getAttributes();
        error_log("authenticate attributes:".var_export($attributes,1));

        // Save the attributes returned from the CAS server (e.g. name, email, and EPPN).
        $this->model->save_user($attributes);
        $casuser = $this->model->find_by_cas_id($attributes[Casauth_model::$cas_id_attribute]);
        if(!$casuser) {
            throw new CasauthException("Error retrieving CAS login information");
        }

        // Check if the CAS User is active and therefore allowed to proceed.
        // By default, new users are active.
        if(!$casuser['is_active']) {
            return array(false, -1);
        }

        // Check if the CAS User is connected to a Scalar User Account yet.
        // For a first time CAS login, there are two options on how to proceed:
        //      Option #1: link to an existing scalar user account by email
        //      Option #2: register a new scalar user account
        if(!$casuser['user_id']) {
            $existing_scalaruser = $this->ci->users->get_by_email($casuser['email']);
            if($existing_scalaruser) {
                $this->model->link_to_scalar_user($casuser['cas_id'], $existing_scalaruser->user_id);
                return array(true, $existing_scalaruser->user_id);
            }
            $registered_scalaruser = $this->register_scalar_user($casuser);
            return array(true, $registered_scalaruser->user_id);
        }

        return array(true, $casuser['user_id']);
    }

    public function register_scalar_user($casuser) {
        $userdata = array(
            'email' => $casuser['email'],
            'fullname' => $casuser['fullname'],
            'password' => 'DISABLED_PASSWORD',
        );
        $this->ci->users->db->insert($this->ci->users->users_table, $userdata);
        return $this->ci->users->get_by_email($casuser['email']);
    }

    public function login_and_redirect($user) {
        $login_basename = confirm_slash(base_url());
        $user->is_logged_in = true;
        $this->ci->session->set_userdata(array($login_basename => (array) $user));
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