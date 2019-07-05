# Scalar CAS Authentication 

A plugin for [anvc/scalar](https://github.com/anvc/scalar). This plugin allows users to authenticate using CAS in addition to the existing email/password authentication.

_Currently a work in progress and will require some minor modifications to Scalar core. This will be updated when it is ready for production use._

## Quickstart

1. Download and unzip to `system/application/plugins/casauth`. You should have a directory that looks like this:
    ```
    casauth/
    ├── README.md
    ├── casauth_pi.php
    ├── config.ini.sample
    ├── lib
    ├── login_select.php
    ├── plugin.css
    ├── plugin.ini
    └── plugin.sql
    
    1 directory, 7 files
    ```
2. Rename `config.ini.sample` to `config.ini` and update the CAS settings to point to your CAS server.
3. Run the SQL in `plugin.sql` to create the database tables (links CAS ID to a Scalar user ID). 
4. Register plugin in `system/application/config/plugins.php` and add or update this line:
    ```
    $config['plugins']['auth'] = 'casauth';
    ```
5. Visit http://localhost:8080/system/login and you should see an option to login with the CAS server or with an email and password.

