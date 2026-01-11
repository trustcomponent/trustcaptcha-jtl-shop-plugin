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

    /**
     * Liest Token aus Request
     */
    public function getToken(): ?string
    {
        // Token kommt als POST-Parameter vom trustcaptcha-component
        // Namen ggf. anpassen – wichtig ist: nichts erfinden, nur sauber umbauen
        return Request::postVar('trustcaptcha_token');
    }

    /**
     * Ruft TrustCaptcha API auf und liefert:
     * - valid => bool
     * - score => float|null
     * - error => string|null
     */
    public function verify(string $token): array
    {
        $secret = $this->plugin->getConfig()->getValue('trustcaptcha_secret_key');

        if ($secret === null || $secret === '') {
            return [
                'valid' => false,
                'score' => null,
                'error' => 'no-secret-configured'
            ];
        }

        // API-Request vorbereiten
        $payload = [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        $ch = curl_init('https://api.trustcaptcha.com/verify'); // URL ggf. anpassen – du kennst die echte
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

        $result = curl_exec($ch);

        if ($result === false) {
            return [
                'valid' => false,
                'score' => null,
                'error' => 'curl-error'
            ];
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($result, true);

        if ($status !== 200 || !is_array($data)) {
            return [
                'valid' => false,
                'score' => null,
                'error' => 'invalid-api-response'
            ];
        }

        return [
            'valid' => (bool)($data['success'] ?? false),
            'score' => isset($data['score']) ? (float)$data['score'] : null,
            'error' => $data['error-codes'][0] ?? null
        ];
    }



    // neu


    public function getMarkup(): string
    {
        $plugin = $this->getPlugin();

        $config = $plugin->getConfig();
        $siteKey            = $config->getValue('trustcaptcha_site_key') ?? '';
        $language           = $config->getValue('trustcaptcha_language') ?? 'auto';
        $theme              = $config->getValue('trustcaptcha_theme') ?? 'light';
        $tokenFieldName     = 'trustcaptcha_token';
        $width              = $config->getValue('trustcaptcha_width') ?? 'fixed';
        $autostart          = $config->getValue('trustcaptcha_autostart') ? 'true' : 'false';
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
                    'tokenFieldName'     => $tokenFieldName,
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
