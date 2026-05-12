<trustcaptcha-component
        id="trustcaptchaComponent"
        sitekey="{$siteKey|escape:'html'}"
        {if $fullWidth}full-width="true"{/if}
        language="{$language|escape:'html'}"
        theme="{$theme|escape:'html'}"
        {if $autostartDisabled}autostart-disabled="true"{/if}
        {if $license}license-key="{$license|escape:'html'}"{/if}
        {if $whiteLabel}white-label="true"{/if}
        {if $customTranslations}translations="{$customTranslations|escape:'html'}"{/if}
        {if $customDesign}design="{$customDesign|escape:'html'}"{/if}
        {if $privacyUrl}privacy-url="{$privacyUrl|escape:'html'}"{/if}
        {if $invisible}invisible="true"{/if}
        invisible-hint="{$invisibleHint|escape:'html'}"
        {if $minimalDataMode}minimal-data-mode="true"{/if}
        {if $failoverEnabled}failover-enabled="true"{/if}
        framework="jtl5"
></trustcaptcha-component>

<style>
    .simple-captcha-wrapper {
        text-align: left;
    }
</style>
