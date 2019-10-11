<?php

use PHPUnit\Framework\TestCase;

class Casauth_utilsTest extends TestCase {

    public function testIsSubdomain() {
        $tests = array(
            array(
                'parent_url' => 'http://scalar.localdomain/',
                'child_url' => 'http://scalar.localdomain/',
                'expected' => false,
            ),
            array(
                'parent_url' => 'http://scalar.localdomain/',
                'child_url' => 'http://foo.scalar.localdomain/',
                'expected' => true,
            ),
            array(
                'parent_url' => 'http://scalar.localdomain/',
                'child_url' => 'http://bar.scalar.localdomain/',
                'expected' => true,
            ),
            array(
                'parent_url' => 'http://scalar.localdomain/',
                'child_url' => 'http://anotherscalar.localdomain/',
                'expected' => false,
            ),
            array(
                'parent_url' => 'http://scalar.localdomain/',
                'child_url' => '/',
                'expected' => false
            ),
            array(
                'parent_url' => '/',
                'child_url' => 'http://scalar.localdomain/',
                'expected' => false
            )
        );
        foreach ($tests as $test) {
            $this->assertEquals($test['expected'], Casauth_utils::is_subdomain($test['child_url'], $test['parent_url']));
        }
    }

    public function testSubdomainLoginUrl() {
        $tests = array(
            array(
                'login_basename' => 'http://scalar.localdomain/',
                'redirect_url' => 'http://foo.scalar.localdomain/index',
                'expected' => 'http://foo.scalar.localdomain/'
            ),
            array(
                'login_basename' => 'http://scalar.localdomain/',
                'redirect_url' => 'http://bar.scalar.localdomain/index',
                'expected' => 'http://bar.scalar.localdomain/'
            ),
            array(
                'login_basename' => 'http://scalar.localdomain/',
                'redirect_url' => 'http://scalar.localdomain/foo/index',
                'expected' => 'http://scalar.localdomain/',
            ),
        );

        foreach ($tests as $test) {
            $redirect_host = parse_url($test['redirect_url'], PHP_URL_HOST);
            $url = Casauth_utils::subdomain_login_basename($test['login_basename'], $redirect_host);
            $this->assertEquals($test['expected'], $url);
        }
    }
}