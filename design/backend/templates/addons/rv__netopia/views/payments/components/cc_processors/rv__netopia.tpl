{* IPN Callback URL (read-only) *}
<div class="control-group">
    <label class="control-label">{__("rv__netopia.ipn_url")}:</label>
    <div class="controls">
        <input type="text" value="{fn_url("payment_notification.notify?payment=rv__netopia", "C", "current")}" readonly="readonly" size="80" class="input-text" onclick="this.select();">
        <p class="muted description">{__("rv__netopia.ipn_url_tooltip")}</p>
    </div>
</div>

<hr>

{* Hidden marker to identify this processor in hooks *}
<input type="hidden" name="payment_data[processor_params][is_rv_netopia]" value="Y">

{* Test/Live Mode *}
<div class="control-group">
    <label class="control-label" for="rv_netopia_mode_{$payment_id}">{__("test_live_mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="rv_netopia_mode_{$payment_id}">
            <option value="live" {if $processor_params.mode == "live"}selected="selected"{/if}>{__("live")}</option>
            <option value="test" {if $processor_params.mode == "test"}selected="selected"{/if}>{__("test")}</option>
        </select>
    </div>
</div>

{* Account Signature *}
<div class="control-group">
    <label class="control-label cm-required" for="rv_netopia_signature_{$payment_id}">{__("rv__netopia.account_signature")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][signature]" id="rv_netopia_signature_{$payment_id}" value="{$processor_params.signature}" size="60">
        <p class="muted description">{__("rv__netopia.account_signature_tooltip")}</p>
    </div>
</div>

{* Public Certificate Upload *}
<div class="control-group">
    <label class="control-label" for="rv_netopia_cer_{$payment_id}">{__("rv__netopia.public_certificate")}:</label>
    <div class="controls">
        <input type="file" name="rv_netopia_cer" id="rv_netopia_cer_{$payment_id}" accept=".cer">
        {if $processor_params.public_cert_content}
            <p class="muted"><strong style="color: green;">{__("rv__netopia.certificate_uploaded")}</strong></p>
        {else}
            <p class="muted" style="color: #999;">{__("rv__netopia.no_certificate")}</p>
        {/if}
        <p class="muted description">{__("rv__netopia.public_certificate_tooltip")}</p>
    </div>
</div>

{* Private Key Upload *}
<div class="control-group">
    <label class="control-label" for="rv_netopia_key_{$payment_id}">{__("rv__netopia.private_key")}:</label>
    <div class="controls">
        <input type="file" name="rv_netopia_key" id="rv_netopia_key_{$payment_id}" accept=".key">
        {if $processor_params.private_key_content}
            <p class="muted"><strong style="color: green;">{__("rv__netopia.key_uploaded")}</strong></p>
        {else}
            <p class="muted" style="color: #999;">{__("rv__netopia.no_key")}</p>
        {/if}
        <p class="muted description">{__("rv__netopia.private_key_tooltip")}</p>
    </div>
</div>
