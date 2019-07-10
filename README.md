# Scalar CAS Authentication 

A plugin for [anvc/scalar](https://github.com/anvc/scalar). 

This plugin allows users to authenticate using CAS in addition to the existing email/password authentication.

![Login Selection](docs/login_select.png)
<br />
_Select login method_

![Login Registration Key](docs/login_regkey.png)
<br />
_If configured, require a registration key after logging in with the CAS server_



## Requirements

1. Scalar must be installed and configured.
1. Scalar must be registered with the CAS server.
2. The CAS server must supply the following attributes:
    - `eduPersonPrincipalName`: uniquely identifies the user
    - `mail`: email address required to register a Scalar account (or link to pre-existing account)
    - `displayName`: full name required to register a Scalar account

## Quickstart

1. Download and unzip to `system/application/plugins/casauth`. 
2. Rename `config.ini.sample` to `config.ini` and update the CAS settings to point to your CAS server.
3. Create database table(s) for this plugin by running the SQL in `plugin.sql` against your Scalar database.
4. Activate the plugin in `system/application/config/plugins.php`:
    ```
    $config['plugins']['auth'] = 'casauth';
    ```
5. Visit http://localhost:8080/system/login

