<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($client)){ ?>
    <h4 class="customer-profile-group-heading">WHMCS Services</h4>
    <div class="col-md-12">
        <div id="whmcs-services-loader" class="text-center mtop20">
            <i class="fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i>
            <span class="sr-only">Loading...</span>
        </div>
        <div id="whmcs-services-content"></div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var client_id = <?php echo $client->userid; ?>;
            var url = admin_url + 'whmcs_bridge/client_services/' + client_id;
            
            $.get(url, function(response) {
                $('#whmcs-services-loader').hide();
                $('#whmcs-services-content').html(response);
            });
        });
    </script>
<?php } ?>
