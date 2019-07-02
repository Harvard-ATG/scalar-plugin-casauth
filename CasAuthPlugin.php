<?php

require_once dirname(__FILE__).'/lib/phpCAS/CAS.php';

class CasAuthPlugin {

    public $plugin_name = "CasAuth";

    public $controller = null;

    public $cas_host = "localhost";

    public $cas_port = 3004;

    public $cas_context = "/cas";

    public function __construct() { }

    public function initialize($controller) {
        $this->CI =& get_instance();
        $this->controller = $controller;
        return $this;
    }

    public function login() {

        if(isset($_GET[$this->plugin_name]) || isset($_GET['ticket'])) {
            phpCAS::setDebug();
            phpCAS::setVerbose(true);
            phpCAS::client(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context);

            // For debugging/testing only with local mock cas server (https://github.com/veo-labs/cas-server-mock)
            // Using this to explicitly set HTTP URLs
            // See also https://github.com/apereo/phpCAS/issues/27
            {
                $service = confirm_slash(base_url()) . 'system/login';
                phpCAS::setServerLoginURL('http://localhost:3004/login?service='.urlencode($service));
                phpCAS::setServerServiceValidateURL('http://localhost:3004/serviceValidate');
                phpCAS::setNoCasServerValidation();
            }

            phpCAS::forceAuthentication();
            echo phpCAS::getUser();
        } else {
            $this->controller->login->do_logout(true);

            $this->controller->data['login'] = $this->controller->login->get();
            $this->controller->data['title'] = $this->controller->lang->line('install_name').': Login';
            $this->controller->data['norobots'] = true;
            $this->controller->data['secondary_auth'] = $this->login_form();

            $this->controller->template->set_template('admin');
            $this->controller->template->write_view('content', 'modules/login/login_box', $this->controller->data);
            $this->controller->template->render();
        }
    }

    public function login_form() {
        $this->template = $this->controller->template;
        $this->template->add_css("system/application/plugins/{$this->plugin_name}/login.css");
        $login_url = confirm_slash(base_url()) . "system/login?{$this->plugin_name}&redirect_url=" . urlencode($_SERVER['REQUEST_URI']);

        ob_start();
        include(dirname(__FILE__).'/login_form.php');
        $contents = ob_get_contents();
        ob_end_clean();

        return $contents;
    }

}