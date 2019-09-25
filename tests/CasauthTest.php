<?php

use PHPUnit\Framework\TestCase;

class Mock_User_model {
    public function __construct() {}
    public function get_by_user_id($user_id) { return $user_id; }
    public function get_by_email($email) { return $email; }
}

class Mock_CI_Config {
    public $register_key;
    public function __construct() {}
    public function item($key) {
        return null;
    }
}

class Mock_CI_Session {
    public function __construct() {}
    public function set_userdata($userdata) {}
    public function userdata($key) { return null; }
    public function unset_userdata($key) {}
}

class Mock_CI_Controller {
    public $config;
    public $session;
    public $users;
    public function __construct() {}
    public function login() {}
}

class Mock_Casauth_model {
    public function __construct() {}
    public function find_by_cas_id($cas_id) {}
}


class CasauthTest extends TestCase {

    protected function setUp() {
    }

    public function testPluginInit() {
        $plugin = new Casauth();
        $plugin->init(array(
            'config' => array(),
            'model' => new Mock_Casauth_model(),
            'ci' => new Mock_CI_Controller(),
        ));
        $this->assertEquals("casauth", $plugin->plugin_name);
        $this->assertTrue(is_array($plugin->config));
        $this->assertTrue($plugin->model instanceof Mock_Casauth_model);
        $this->assertTrue($plugin->ci instanceof Mock_CI_Controller);
    }

    public function testUserPreauthorized() {
        $test_user_record = array('user_id'=>1,'email'=>'somebody@local.domain');
        $tests = array(
            array(
                'find_by_cas_id' => true,
                'get_by_email' => $test_user_record,
                'config_register_keys' => array('REGKEY'),
                'session_register_key' => null,
                'expected' => array(true, "User previously authenticated; registration key not required"),
            ),
            array(
                'find_by_cas_id' => false,
                'get_by_email' => $test_user_record,
                'config_register_keys' => array('REGKEY'),
                'session_register_key' => null,
                'expected' => array(true, "User already exists with email; registration key not required"),
            ),
            array(
                'find_by_cas_id' => false,
                'get_by_email' => false,
                'config_register_keys' => array(),
                'session_register_key' => null,
                'expected' => array(true, "Registration keys not configured"),
            ),
            array(
                'find_by_cas_id' => false,
                'get_by_email' => false,
                'config_register_keys' => array('REGKEY'),
                'session_register_key' => '123KEY',
                'expected' => array(false, "Invalid registration key: 123KEY"),
            ),
            array(
                'find_by_cas_id' => false,
                'get_by_email' => false,
                'config_register_keys' => array('REGKEY'),
                'session_register_key' => null,
                'expected' => array(false, null),
            ),

        );

        foreach($tests as $test) {
            $model_stub = $this->createMock('Mock_Casauth_model');
            $model_stub->method('find_by_cas_id')->willReturn($test['find_by_cas_id']);
            $user_stub = $this->createMock('Mock_User_model');
            $user_stub->method('get_by_email')->willReturn($test['get_by_email']);
            $config_stub = $this->createMock('Mock_CI_Config');
            $config_stub->method('item')->willReturn($test['config_register_keys']);
            $session_stub = $this->createMock('Mock_CI_Session');
            $session_stub->method('userdata')->willReturn($test['session_register_key']);
            $ci = new Mock_CI_Controller();
            $ci->users = $user_stub;
            $ci->config = $config_stub;
            $ci->session = $session_stub;

            $attributes = new Casauth_attributes();
            $attributes->set_cas_id('123')
                ->set_email($test_user_record['email'])
                ->set_fullname('some body');

            $plugin = new Casauth();
            $plugin->init(array('config' => array(), 'model' => $model_stub, 'ci' => $ci));

            $actual = $plugin->preauthorize($attributes);
            $this->assertEquals($test['expected'], $actual);
        }
    }
}
