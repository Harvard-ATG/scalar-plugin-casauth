<!DOCTYPE html>
<html>
    <head>
        <title>Scalar: Login</title>
        <link type="text/css" rel="stylesheet" href="<?= confirm_slash(base_url());?>/system/application/views/modules/dashboot/css/bootstrap.min.css">
        <link type="text/css" rel="stylesheet" href="<?= confirm_slash(base_url());?>/system/application/views/arbors/admin/admin.css" />
        <link type="text/css" rel="stylesheet" href="<?= confirm_slash(base_url());?>/system/application/plugins/casauth/plugin.css" />
        <script src="<?= confirm_slash(base_url());?>/system/application/views/modules/dashboot/js/jquery-3.1.0.min.js"></script>
        <script src="<?= confirm_slash(base_url());?>/system/application/views/modules/dashboot/js/bootstrap.min.js"></script>
    </head>
    <body>
        <div class="well" style="width: 25%; min-width: 400px; margin: 10% auto;">
            <div style="width: 100%;">
                <img style="margin-left: calc(50% - 37.5px); margin-bottom: 20px;" src="<?= confirm_slash(base_url()); ?>/system/application/views/modules/login/scalar_logo.png" alt="scalar_logo" width="75" height="68">
            </div>
            <div class="row" style="margin-bottom: 1em;">
                <div class="col-lg-12">
                    <a href="<?= $cas_login_url ?>" class="btn btn-default btn-login-select btn-login-cas"><?= $cas_button_text ?></a>
                </div>
            </div>
            <div class="row" style="margin-bottom: 1em;">
                <div class="col-lg-12">
                    <a href="<?= $default_login_url ?>" class="btn btn-default btn-login-select" style="display:block;">Login with email/password</a>
                </div>
            </div>
        </div>
    </body>
</html>