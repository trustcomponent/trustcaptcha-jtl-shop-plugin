<?php

declare(strict_types=1);

namespace Plugin\trustcaptcha;

use Exception;
use JTL\Helpers\Request;
use JTL\Plugin\PluginInterface;
use JTL\Shop;

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

    public function validate(array $requestData): bool
    {
        if (empty($requestData['trustcaptcha_token'])) {
            return false;
        }

        $plugin = $this->getPlugin();
        $config = $plugin->getConfig();
        $secretKey = $config->getValue('trustcaptcha_secret_key') ?? '';
        return $this->verifyKey($secretKey, $requestData['trustcaptcha_token']);
    }

    private function verifyKey(string $secretKey, string $trustcaptcha_token): bool {

        $decodedJson = base64_decode($trustcaptcha_token);
        $tokenData = json_decode($decodedJson, true);

        if (!$tokenData || empty($tokenData['verificationId'])) {
            return false;
        }

        $url = 'https://api.trustcomponent.com/verifications/' . urlencode($tokenData["verificationId"]) . '/assessments';


        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "Content-Type: application/json\r\n" .
                    "tc-authorization: {$secretKey}\r\n",
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if (!$response) {
            return false;
        }

        $result = json_decode($response, true);

        // TODO welche anderen Werte wären wichtig?
        $threshold = (float) ($this->plugin->getConfig()->getValue('trustcaptcha_threshold') ?? 0.5);
        if (isset($result['score']) && $result['score'] > $threshold) {
            return false;
        }

        // TODO Offizielle Library einbinden
        // TODO Kontakt-Seite rechts vielleicht?
        return true;
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
