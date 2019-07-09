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
<div class="well login" style="margin-top: 5%; background: #fff;">
    <div style="width: 100%;">
        <a href="<?= confirm_slash(base_url()); ?>"><img style="margin-left: calc(50% - 37.5px); margin-bottom: 20px;" src="<?= confirm_slash(base_url()); ?>/system/application/views/modules/login/scalar_logo.png" alt="scalar_logo" width="75" height="68"></a>
    </div>
    <div class="row" style="margin-bottom: 1em;">
        <div class="col-lg-12">
            <?php if(isset($error_msg) && $error_msg): ?>
            <div class="alert alert-danger"><?= $error_msg; ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $action_url; ?>">
                <div class="form-group">
                    <label for="registration_key">Registration key required to login:</label>
                    <input type="text" class="form-control" name="registration_key" id="registration_key" required>
                </div>
                <button type="submit" class="btn btn-default">Submit Registration Key</button>
            </form>
        </div>
    </div>
</div>
<div class="login" style="margin-top: 10px;">
    <a href="<?= confirm_slash(base_url()); ?>">Return to index</a>
</div>
</body>
</html>