<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php echo form_open(admin_url('whmcs_bridge/settings')); ?>
                        
                        <div class="form-group">
                            <label for="whmcs_bridge_url">WHMCS System URL</label>
                            <input type="text" class="form-control" name="settings[whmcs_bridge_url]" value="<?php echo get_option('whmcs_bridge_url'); ?>" placeholder="https://my.whmcs.com">
                            <p class="help-block">Enter the full URL to your WHMCS installation (without trailing slash).</p>
                        </div>

                        <div class="form-group">
                            <label for="whmcs_bridge_identifier">API Identifier</label>
                            <input type="text" class="form-control" name="settings[whmcs_bridge_identifier]" value="<?php echo get_option('whmcs_bridge_identifier'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="whmcs_bridge_secret">API Secret</label>
                            <input type="password" class="form-control" name="settings[whmcs_bridge_secret]" value="<?php echo get_option('whmcs_bridge_secret'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="whmcs_bridge_default_gateway">Default WHMCS Payment Gateway</label>
                            <input type="text" class="form-control" name="settings[whmcs_bridge_default_gateway]" value="<?php echo get_option('whmcs_bridge_default_gateway'); ?>" placeholder="mailin">
                            <p class="help-block">Enter the system name of the gateway in WHMCS (e.g., paytr, mailin, banktransfer).</p>
                        </div>

                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="settings[whmcs_bridge_debug_mode]" id="whmcs_bridge_debug_mode" <?php if(get_option('whmcs_bridge_debug_mode') == '1'){echo 'checked';} ?>>
                                <label for="whmcs_bridge_debug_mode">Enable Debug Mode (Logs to Activity Log)</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-info pull-right">Save Settings</button>
                        <button type="button" id="test-connection" class="btn btn-default pull-left">Test Connection</button>
                        
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function(){
        $('#test-connection').on('click', function(){
            var btn = $(this);
            btn.button('loading');
            $.get(admin_url + 'whmcs_bridge/test_connection', function(response){
                response = JSON.parse(response);
                if(response.success) {
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response.message);
                }
                btn.button('reset');
            });
        });
    });
</script>
</body>
</html>
