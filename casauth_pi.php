<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';

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
    }

    public function init() {}

    /**
     * Called by the system controller's _remap() method.
     * This is intended to override the system::login() method.
     */
    public function hook_system_login() {
        $CI =& get_instance();

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
                $this->authenticate();
                break;
            case 'default':
            default:
                $CI->login();
                break;
        }
    }

    /**
     * Called by the system controller's _remap() method.
     * This is intended to provide a separate endpoint for the CAS server
     * to call back to with the service ticket.
     */
    public function hook_system_cas_login() {
        $this->authenticate();
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
     * This method handles the CAS authentication flow, delegating the work to the phpCAS library.
     *
     * The intent is to call this when a user is initiating a new CAS login
     * and when they are returning back from the CAS server with a ticket that needs authenticating.
     */
    public function authenticate() {
        phpCAS::setDebug();
        phpCAS::setVerbose(true);
        phpCAS::client(CAS_VERSION_2_0, $this->config['cas_host'], (int) $this->config['cas_port'], $this->config['cas_context']);
        $this->_debugAuthenticate();
        phpCAS::forceAuthentication();
        echo phpCAS::getUser();
    }

    // For debugging locally.
    private function _debugAuthenticate() {
        // For debugging/testing only with local mock cas server (https://github.com/veo-labs/cas-server-mock)
        // Using this to explicitly set HTTP URLs
        // See also https://github.com/apereo/phpCAS/issues/27
        $ip = "localhost";
        $port = 3004;
        $cas_server = "$ip:$port";
        $service = confirm_slash(base_url())."system/cas_login";
        phpCAS::setServerLoginURL("http://$cas_server/login?service=".urlencode($service));
        phpCAS::setServerServiceValidateURL("http://$cas_server/serviceValidate");
        phpCAS::setNoCasServerValidation();
    }

}