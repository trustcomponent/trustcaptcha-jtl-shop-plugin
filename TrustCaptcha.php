<?php

declare(strict_types=1);

namespace Plugin\trustcomponent_trustcaptcha_jtl;

use Exception;
use JTL\Helpers\Request;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use TrustComponent\TrustCaptcha\TrustCaptcha as TrustCaptchaClient;
use TrustComponent\TrustCaptcha\ApiKeyInvalidException;
use TrustComponent\TrustCaptcha\ServerUnreachableException;
use TrustComponent\TrustCaptcha\ClientReportedServerUnreachableException;

class TrustCaptcha
{

    private $plugin;

    public function __construct(PluginInterface $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getPlugin(): PluginInterface
    {
        return $this->plugin;
    }

    public function isConfigured(): bool
    {
        $config = $this->plugin->getConfig();

        $siteKey   = $config->getValue('trustcaptcha_site_key') ?? '';
        $secretKey = $config->getValue('trustcaptcha_secret_key') ?? '';

        if (empty($siteKey) || empty($secretKey)) {
            return false;
        }

        return true;
    }

    public function validate(array $requestData): bool
    {
        if (empty($requestData['tc-verification-token'])) {
            return false;
        }

        $plugin = $this->getPlugin();
        $config = $plugin->getConfig();
        $apiKey = $config->getValue('trustcaptcha_secret_key') ?? '';
        return $this->verifyKey($apiKey, $requestData['tc-verification-token']);
    }

    private function verifyKey(string $apiKey, string $trustcaptcha_token): bool {

        $threshold = max(0.2, (float) ($this->plugin->getConfig()->getValue('trustcaptcha_threshold') ?? 0.5));
        $failoverEnabled = (bool) ($this->plugin->getConfig()->getValue('trustcaptcha_failover_enabled') ?? false);

        try {
            $trustCaptcha = new TrustCaptchaClient($apiKey);
            $verificationResult = $trustCaptcha->getVerificationResult($trustcaptcha_token);

            if (!$verificationResult->verificationPassed || $verificationResult->score > $threshold) {
                return false;
            }
            return true;
        } catch (ServerUnreachableException $e) {
            if ($failoverEnabled) {
                Shop::Container()->getLogService()->warning(
                    'TrustCaptcha: API unreachable from server — allowed via failover. ' . $e->getMessage()
                );
                return true;
            }
            Shop::Container()->getLogService()->error(
                'TrustCaptcha: API unreachable from server, failover disabled. ' . $e->getMessage()
            );
            return false;
        } catch (ClientReportedServerUnreachableException $e) {
            // Always reject — low-trust signal.
            Shop::Container()->getLogService()->warning(
                'TrustCaptcha: Client reported failover blocked. ' . $e->getMessage()
            );
            return false;
        } catch (ApiKeyInvalidException | Exception $e) {
            Shop::Container()->getLogService()->error(
                'TrustCaptcha verification error: ' . $e->getMessage()
            );
            return false;
        }
    }

    public function getMarkup(): string
    {
        $plugin = $this->getPlugin();

        $config = $plugin->getConfig();
        $siteKey            = $config->getValue('trustcaptcha_site_key') ?? '';
        $language           = $config->getValue('trustcaptcha_language') ?? 'auto';
        $theme              = $config->getValue('trustcaptcha_theme') ?? 'light';
        $width              = $config->getValue('trustcaptcha_width') ?? 'fixed';
        // Plugin setting "autostart" is stored as "Y"/"N" in JTL. Invert to the v3 "autostart-disabled" attribute.
        $autostartDisabled  = $config->getValue('trustcaptcha_autostart') === 'N';
        $license            = $config->getValue('trustcaptcha_license_key') ?? '';
        $hideBranding       = (bool) $config->getValue('trustcaptcha_hide_branding');
        $invisible          = (bool) $config->getValue('trustcaptcha_invisible');
        $invisibleHint      = $config->getValue('trustcaptcha_invisible_hint') ?? 'right-border';
        $mode               = $config->getValue('trustcaptcha_mode') ?? 'standard';
        $privacyUrl         = $config->getValue('trustcaptcha_privacy_url') ?? '';
        $customTranslations = $config->getValue('trustcaptcha_custom_translations') ?? '';
        $customDesign       = $config->getValue('trustcaptcha_custom_design') ?? '';
        $failoverEnabled    = (bool) $config->getValue('trustcaptcha_failover_enabled');

        try {
            return Shop::Smarty()
                ->assign([
                    'siteKey'             => $siteKey,
                    'language'            => $language,
                    'theme'               => $theme,
                    'fullWidth'           => ($width === 'full'),
                    'autostartDisabled'   => $autostartDisabled,
                    'license'             => $license,
                    'whiteLabel'          => $hideBranding,
                    'invisible'           => $invisible,
                    'invisibleHint'       => $invisibleHint,
                    'minimalDataMode'     => ($mode === 'minimal'),
                    'privacyUrl'          => $privacyUrl,
                    'customTranslations'  => $customTranslations,
                    'customDesign'        => $customDesign,
                    'failoverEnabled'     => $failoverEnabled,
                ])
                ->fetch($plugin->getPaths()->getFrontendPath() . '/template/trustcaptcha_widget.tpl');
        } catch (Exception $e) {
            return \__('Cannot render captcha');
        }
    }

}
