<input type="hidden" name="{$tokenFieldName|escape:'html'}" id="{$tokenFieldName|escape:'html'}">

<trustcaptcha-component
        id="trustcaptchaComponent"
        sitekey="{$siteKey|escape:'html'}"
        width="{$width|escape:'html'}"
        language="{$language|escape:'html'}"
        theme="{$theme|escape:'html'}"
        autostart="{$autostart|escape:'html'}"
        license="{$license|escape:'html'}"
        hide-branding="{$hideBranding|escape:'html'}"
        custom-translations="{$customTranslations|escape:'html'}"
        custom-design="{$customDesign|escape:'html'}"
        privacy-url="{$privacyUrl|escape:'html'}"
        invisible="{$invisible|escape:'html'}"
        invisible-hint="{$invisibleHint|escape:'html'}"
        token-field-name="{$tokenFieldName|escape:'html'}"
        mode="{$mode|escape:'html'}"
></trustcaptcha-component>

<script>
    (function() {
        const c = document.getElementsByTagName("trustcaptcha-component")[0];

        c.addEventListener("captchaSolved", function(event) {
            document.getElementById("{$tokenFieldName|escape:'js'}").value = event.detail;
        });

        c.addEventListener("captchaFailed", function() {
            document.getElementById("{$tokenFieldName|escape:'js'}").value = "";
        });
    })();
</script>
