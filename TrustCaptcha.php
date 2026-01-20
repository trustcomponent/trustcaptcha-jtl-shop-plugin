<?php

declare(strict_types=1);

namespace Plugin\trustcaptcha;

use Exception;
use JTL\Helpers\Request;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use TrustComponent\TrustCaptcha\CaptchaManager;
use JTL\Helpers\Log;

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
        $secretKey = $config->getValue('trustcaptcha_secret_key') ?? '';
        return $this->verifyKey($secretKey, $requestData['tc-verification-token']);
    }

    private function verifyKey(string $secretKey, string $trustcaptcha_token): bool {

        $threshold = (float) ($this->plugin->getConfig()->getValue('trustcaptcha_threshold') ?? 0.5);
        try {
            $verificationResult = CaptchaManager::getVerificationResult($secretKey, $trustcaptcha_token);

            if (!$verificationResult->verificationPassed || $verificationResult->score > $threshold) {
                return false;
            }
            return true;
        } catch (Exception $e) {

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
        $autostart          = $config->getValue('trustcaptcha_autostart') == "Y" ? 'true' : 'false';
        $license            = $config->getValue('trustcaptcha_license_key') ?? '';
        $hideBranding       = $config->getValue('trustcaptcha_hide_branding') ? 'true' : 'false';
        $invisible          = $config->getValue('trustcaptcha_invisible') ? 'true' : 'false';
        $invisibleHint      = $config->getValue('trustcaptcha_invisible_hint') ?? 'right-border';
        $mode               = $config->getValue('trustcaptcha_mode') ?? 'standard';
        $privacyUrl         = $config->getValue('trustcaptcha_privacy_url') ?? '';
        $customTranslations = $config->getValue('trustcaptcha_custom_translations') ?? '';
        $customDesign       = $config->getValue('trustcaptcha_custom_design') ?? '';

        try {
            return Shop::Smarty()
                ->assign([
                    'siteKey'            => $siteKey,
                    'language'           => $language,
                    'theme'              => $theme,
                    'width'              => $width,
                    'autostart'          => $autostart,
                    'license'            => $license,
                    'hideBranding'       => $hideBranding,
                    'invisible'          => $invisible,
                    'invisibleHint'      => $invisibleHint,
                    'mode'               => $mode,
                    'privacyUrl'         => $privacyUrl,
                    'customTranslations' => $customTranslations,
                    'customDesign'       => $customDesign,
                ])
                ->fetch($plugin->getPaths()->getFrontendPath() . '/template/trustcaptcha_widget.tpl');
        } catch (Exception $e) {
            return \__('Cannot render captcha');
        }
    }

}
