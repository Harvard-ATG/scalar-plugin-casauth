<?php

class Casauth_utils {

    /**
     * Checks if one URL is a subdomain of another.
     *
     * @param $child_url subdomain url
     * @param $parent_url parent domain url
     * @return bool
     */
    public static function is_subdomain($child_url, $parent_url) {
        $parsed_child = parse_url($child_url);
        $parsed_parent = parse_url($parent_url);
        if(!isset($parsed_child['host']) || !isset($parsed_parent['host'])) {
            return false;
        }

        $child_host = $parsed_child['host'];
        $parent_host = $parsed_parent['host'];
        if(strlen($child_host) > strlen($parent_host) && ".$parent_host" == substr($child_host, strlen($child_host) - strlen($parent_host) - 1)) {
            return true;
        }

        return false;
    }

    /**
     * Replaces the hostname part of the login base url.
     *
     * A scalar session typically maps the login base_url to logged-in user object,
     * so in order to ensure a user is "logged in" on a subdomain, it's necessary
     * to set the appropriate login_basename containing the subdomain host.
     *
     * This function is intended to be used to swap out the "host" part of the
     * login base_url from the parent domain to provide a valid login_basename
     * for that subdomain.
     *
     * @param $login_basename base URL of scalar system
     * @param $subdomain_host the subdomain host
     * @return string the base URL with the host replaced
     */
    public static function subdomain_login_basename($login_basename, $subdomain_host) {
        $parsed_login_basename = parse_url($login_basename);
        $scheme = isset($parsed_login_basename['scheme']) ? $parsed_login_basename['scheme'].'://' : 'http://';
        $host = isset($subdomain_host) ? $subdomain_host : 'localhost';
        $port = isset($parsed_login_basename['port']) ? ':'.$parsed_login_basename['port'] : '';
        $path = isset($parsed_login_basename['path']) ? $parsed_login_basename['path'] : '/';
        return $scheme.$host.$port.$path;
    }
}