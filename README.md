# Scalar CAS Authentication 

A plugin for [anvc/scalar](https://github.com/anvc/scalar). This plugin allows users to authenticate using CAS in addition to the existing email/password authentication.

_Currently a work in progress and will require some minor modifications to Scalar core. This will be updated when it is ready for production use._

## Quickstart

1. Download and unzip to `system/application/plugins/CasAuth`
2. Update `system/application/config/local_settings.php` to use this plugin:
    ```
    $config['use_auth_plugin'] = true;
    $config['auth_plugin'] = 'CasAuth';
    ```
3. Visit http://localhost:8080/system/login and you should see an option to login with CAS.

