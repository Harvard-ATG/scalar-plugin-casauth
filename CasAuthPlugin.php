<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';

class CasAuthPlugin {

    public $plugin_name = "CasAuth";

    public $controller = null;

    public $config = array();

    public function __construct() {
        $this->config = parse_ini_file(dirname(__FILE__).'/config.ini');
        if($this->config === FALSE) {
            throw new Exception("CasAuthPlugin misconfigured: config.ini required");
        }
    }

    public function init($controller) {
        $this->CI =& get_instance();
        $this->controller = $controller;
        return $this;
    }

    public function hook_system_login() {
        if(isset($_GET['cas']) || isset($_GET['ticket'])) {
            phpCAS::setDebug();
            phpCAS::setVerbose(true);
            phpCAS::client(CAS_VERSION_2_0, $this->config['cas_host'], (int) $this->config['cas_port'], $this->config['cas_context']);

            // For debugging/testing only with local mock cas server (https://github.com/veo-labs/cas-server-mock)
            // Using this to explicitly set HTTP URLs
            // See also https://github.com/apereo/phpCAS/issues/27
            {
                $service = confirm_slash(base_url()) . 'system/login';
                phpCAS::setServerLoginURL('http://10.0.0.186:3004/login?service='.urlencode($service));
                phpCAS::setServerServiceValidateURL('http://10.0.0.186:3004/serviceValidate');
                phpCAS::setNoCasServerValidation();
            }

            phpCAS::forceAuthentication();
            echo phpCAS::getUser();
        } else {
            $this->controller->data['secondary_auth'] = $this->login_form();
            $this->controller->login();
        }
    }

    public function login_form() {
        $this->template = $this->controller->template;
        $this->template->add_css("system/application/plugins/{$this->plugin_name}/login.css");
        $redirect_url = isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '';
        $login_url = confirm_slash(base_url()) . "system/login?cas&redirect_url=" . urlencode($redirect_url);

        ob_start();
        include(dirname(__FILE__).'/login_form.php');
        $contents = ob_get_contents();
        ob_end_clean();

        return $contents;
    }

}