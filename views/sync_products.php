<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (isset($api_error)): ?>
                            <div class="alert alert-danger">
                                WHMCS API Error: <?php echo $api_error; ?> <br>
                                Please check your <a href="<?php echo admin_url('whmcs_bridge/settings'); ?>">Settings</a>.
                            </div>
                        <?php else: ?>

                            <div class="alert alert-info">
                                Select products to import into Perfex CRM as "Items". Duplicates (by name) will be skipped.
                            </div>

                            <?php echo form_open(admin_url('whmcs_bridge/sync_products')); ?>
                            
                            <div class="form-group">
                                <label for="group_id">Import into Perfex Item Group</label>
                                <select name="group_id" id="group_id" class="form-control selectpicker" data-live-search="true">
                                    <option value="">-- Create New "Hosting" Group --</option>
                                    <?php foreach($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="table-responsive">
                                <table class="table dt-table" data-order-col="1" data-order-type="asc">
                                    <thead>
                                        <tr>
                                            <th width="5%"><input type="checkbox" id="mass_select_all" data-to-table="whmcs_products"></th>
                                            <th>Product Name</th>
                                            <th>Group/Category</th>
                                            <th>Type</th>
                                            <th>Pricing (TRY)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($whmcs_products)): ?>
                                            <?php foreach($whmcs_products as $product): 
                                                // Try to find pricing matching Base Currency
                                                $price = '-';
                                                
                                                // Handle cases where pricing array might be missing or empty
                                                $pricing_raw = $product['pricing'] ?? [];
                                                
                                                if (!empty($pricing_raw) && is_array($pricing_raw)) {
                                                    // Try to get Base Currency (passed from controller)
                                                    $p = $pricing_raw[$base_currency] ?? $pricing_raw['TRY'] ?? array_values($pricing_raw)[0] ?? [];
                                                    
                                                    if (isset($p['annually']) && floatval(str_replace(',', '', $p['annually'])) > 0) {
                                                        $price = $p['annually'] . ' ' . ($p['currency'] ?? $base_currency) . ' /yr'; // WHMCS doesn't send currency code inside pricing array usually, only key
                                                    } elseif (isset($p['monthly']) && floatval(str_replace(',', '', $p['monthly'])) > 0) {
                                                        $price = $p['monthly'] . ' ' . ($p['currency'] ?? $base_currency) . ' /mo';
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="checkbox">
                                                        <input type="checkbox" name="products[]" value="<?php echo $product['pid']; ?>" id="p_<?php echo $product['pid']; ?>">
                                                        <label for="p_<?php echo $product['pid']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td><span class="font-medium-xs"><?php echo $product['name']; ?></span></td>
                                                <td><?php echo $product['groupname'] ?? '-'; ?></td>
                                                <td><?php echo $product['type']; ?></td>
                                                <td><?php echo $price; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-success mtop15">Import Selected Products</button>
                            <?php echo form_close(); ?>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function(){
        // Simple select all implementation if dt-table doesn't handle it automatically for this custom layout
        $('#mass_select_all').click(function(){
            var checked = $(this).prop('checked');
            $('input[name="products[]"]').prop('checked', checked);
        });
    });
</script>
</body>
</html>
