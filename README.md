# Scalar CAS Authentication 

A plugin for [anvc/scalar](https://github.com/anvc/scalar). This plugin allows users to authenticate using CAS in addition to the existing email/password authentication.

![Login Screen](login_select.png)

## Quickstart

1. Download and unzip to `system/application/plugins/casauth`. 
2. Rename `config.ini.sample` to `config.ini` and update the CAS settings to point to your CAS server.
3. Run the SQL in `plugin.sql` against the database to setup the required tables.
4. Register the plugin in `system/application/config/plugins.php`. To register, add or update this line:
    ```
    $config['plugins']['auth'] = 'casauth';
    ```
5. Visit http://localhost:8080/system/login and you should see an option to login with the CAS server or with an email and password.

