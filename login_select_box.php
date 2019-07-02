<?if (!defined('BASEPATH')) exit('No direct script access allowed')?>
<?$this->template->add_meta('viewport','width=device-width');?>
<?$this->template->add_css('system/application/views/arbors/admin/admin.css')?>
<?$this->template->add_js('system/application/views/arbors/admin/jquery-1.7.min.js')?>
<?$this->template->add_js('system/application/views/arbors/admin/admin.js')?>
<div class="system_wrapper">
    <div class="content">
        <div class="login_wrapper" style="left: calc(50% - 50px); top: 25%; background: #fff; border: 1px solid #ccc; border-radius: 5px; padding: 50px;">
            <div>
                <a href="?select_auth=primary" class="generic_button large default">Login with email/password</a>
            </div>
            <div style="margin: 25px 0;">
                <a href="#" class="generic_button large default"><?= $select_button_text; ?></a>
            </div>

            <div class="login_footer">
                <a href="<?=base_url()?>">Return to index</a> |
                <a href="http://scalar.usc.edu/terms-of-service/" target="_blank">Terms of Service</a> |
                <a href="register">Register an account</a>
            </div>
        </div>
        <br clear="both" />
    </div>
</div>