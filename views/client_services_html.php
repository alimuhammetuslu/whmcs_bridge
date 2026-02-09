<?php if (empty($whmcs_client_id)): ?>
    <div class="alert alert-warning text-center">
        <p>No WHMCS account is linked to this client yet.</p>
        <!-- Future: Add 'Link WHMCS Account' button here if needed -->
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Products/Services -->
    <h4 class="bold mbot15">Hosting & Services</h4>
    <?php if (empty($products)): ?>
        <p class="text-muted">No active services found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Product/Service</th>
                        <th>Domain</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $service): ?>
                        <tr>
                            <td><?php echo $service['name']; ?></td>
                            <td><a href="http://<?php echo $service['domain']; ?>" target="_blank"><?php echo $service['domain']; ?></a></td>
                            <td><?php echo $service['recurringamount'] . ' ' . $service['billingcycle']; ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'default';
                                    if($service['status'] == 'Active') $statusClass = 'success';
                                    if($service['status'] == 'Suspended') $statusClass = 'danger';
                                    if($service['status'] == 'Pending') $statusClass = 'warning';
                                ?>
                                <span class="label label-<?php echo $statusClass; ?>"><?php echo $service['status']; ?></span>
                            </td>
                            <td>
                                <?php if($service['status'] == 'Active'): ?>
                                    <button class="btn btn-danger btn-xs service-action" data-id="<?php echo $service['id']; ?>" data-cmd="suspend">Suspend</button>
                                <?php elseif($service['status'] == 'Suspended'): ?>
                                    <button class="btn btn-success btn-xs service-action" data-id="<?php echo $service['id']; ?>" data-cmd="unsuspend">Unsuspend</button>
                                <?php elseif($service['status'] == 'Pending'): ?>
                                    <button class="btn btn-success btn-xs service-action" data-id="<?php echo $service['id']; ?>" data-cmd="create" title="Activate/Create">Activate</button>
                                    <button class="btn btn-warning btn-xs service-action" data-id="<?php echo $service['id']; ?>" data-cmd="cancel_service" title="Set status to Cancelled">Cancel</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Domains -->
    <h4 class="bold mtop25 mbot15">Domains</h4>
    <?php if (empty($domains)): ?>
        <p class="text-muted">No domains found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Registration Date</th>
                        <th>Next Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td><?php echo $domain['domainname']; ?></td>
                            <td><?php echo $domain['registrationdate'] ?? '-'; ?></td>
                            <td><?php echo $domain['nextduedate'] ?? '-'; ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'default';
                                    if($domain['status'] == 'Active') $statusClass = 'success';
                                    if($domain['status'] == 'Expired') $statusClass = 'danger';
                                ?>
                                <span class="label label-<?php echo $statusClass; ?>"><?php echo $domain['status']; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Orders -->
    <h4 class="bold mtop25 mbot15">Recent Orders</h4>
    <?php if (empty($orders)): ?>
        <p class="text-muted">No orders found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Invoice</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo $order['date']; ?></td>
                            <td><?php echo $order['amount']; ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'default';
                                    if($order['status'] == 'Active') $statusClass = 'success';
                                    if($order['status'] == 'Pending') $statusClass = 'warning';
                                    if($order['status'] == 'Cancelled') $statusClass = 'danger';
                                    if($order['status'] == 'Fraud') $statusClass = 'danger';
                                ?>
                                <span class="label label-<?php echo $statusClass; ?>"><?php echo $order['status']; ?></span>
                            </td>
                            <td>
                                <?php if(isset($order['invoiceid']) && $order['invoiceid'] > 0): ?>
                                    <a href="<?php echo rtrim(get_option('whmcs_bridge_url'), '/') . '/viewinvoice.php?id=' . $order['invoiceid']; ?>" target="_blank" class="btn btn-default btn-xs">
                                        View Invoice #<?php echo $order['invoiceid']; ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($order['status'] == 'Pending'): ?>
                                    <button class="btn btn-warning btn-xs order-action" data-id="<?php echo $order['id']; ?>" data-invoice-id="<?php echo $order['invoiceid']; ?>" data-cmd="cancel_order">Cancel</button>
                                    <button class="btn btn-danger btn-xs order-action" data-id="<?php echo $order['id']; ?>" data-cmd="delete_order">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <script>
    $(function(){
        $('.service-action').on('click', function(){
            var btn = $(this);
            var id = btn.data('id');
            var cmd = btn.data('cmd');
            
            if(!confirm('Are you sure you want to ' + cmd + ' this service?')) return;

            btn.button('loading');
            
            $.post(admin_url + 'whmcs_bridge/service_action', {
                service_id: id,
                command: cmd,
                [csrfData.token_name]: csrfData.hash // Include CSRF Token
            }, function(response){
                response = JSON.parse(response);
                if(response.success) {
                    alert_float('success', response.message);
                    // Reload tab content
                    location.reload(); 
                } else {
                    alert_float('danger', response.message);
                }
                btn.button('reset');
            });
        });

        $('.order-action').on('click', function(){
            var btn = $(this);
            var id = btn.data('id');
            var invoiceId = btn.data('invoice-id'); // Get associated WHMCS Invoice ID
            var cmd = btn.data('cmd');
            
            if(!confirm('Are you sure you want to ' + cmd.replace('_', ' ') + '?')) return;

            btn.button('loading');
            
            $.post(admin_url + 'whmcs_bridge/order_action', {
                order_id: id,
                whmcs_invoice_id: invoiceId, // Send to controller
                command: cmd,
                [csrfData.token_name]: csrfData.hash // Include CSRF Token
            }, function(response){
                response = JSON.parse(response);
                if(response.success) {
                    alert_float('success', response.message);
                    location.reload(); 
                } else {
                    alert_float('danger', response.message);
                }
                btn.button('reset');
            });
        });
    });
    </script>

<?php endif; ?>
