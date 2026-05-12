<?php declare(strict_types=1);

namespace Plugin\trustcomponent_trustcaptcha_jtl;

use JTL\Alert\Alert;
use JTL\Events\Dispatcher;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Backend\Notification;
use JTL\Backend\NotificationEntry;

class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        $autoload = $this->getPlugin()->getPaths()->getBasePath() . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        parent::boot($dispatcher);

        $dispatcher->listen('backend.notification', [$this, 'checkNotification']);
        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_CONFIGURED, [$this, 'tcConfigured']);
        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_MARKUP, [$this, 'tcMarkup']);
        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_VALIDATE, [$this, 'tcValidate']);
    }

    public function checkNotification(Notification $notification): void
    {

        if (!$this->getCaptcha()->isConfigured()) {
            $notification->add(
                NotificationEntry::TYPE_WARNING,
                $this->getPlugin()->getMeta()->getName(),
                'Sie müssen einen Site-Key und einen Secret-Key angeben, um TrustCaptcha zu aktivieren.',
                'plugin.php?kPlugin=' . $this->getPlugin()->getID()
            );
        }
    }



    protected function getCaptcha(): TrustCaptcha
    {
        static $captcha;

        if ($captcha === null) {
            $plugin  = $this->getPlugin();
            $captcha = new TrustCaptcha($plugin);
        }

        return $captcha;
    }


    public function tcConfigured(array &$args): void
    {
        $captcha = $this->getCaptcha();
        $args['isConfigured'] = $captcha->isConfigured();
    }


    public function tcMarkup(array &$args): void
    {
        if (isset($args['getBody']) && $args['getBody']) {
            $args['markup'] = $this->getCaptcha()->getMarkup();
            return;
        }
        $scriptUrl = $this->getPlugin()->getPaths()->getFrontendURL() . 'js/trustcaptcha-3.0.1.umd.min.js';
        $args['markup'] = '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES) . '" defer></script>';
    }


    public function tcValidate(array &$args): void
    {
        $isValid = $this->getCaptcha()->validate($args['requestData'] ?? []);

        if (!$isValid) {
            $plugin = $this->getPlugin();
            $message  = $plugin->getLocalization()->getTranslation('captcha_failed_alert');

            Shop::Container()->getAlertService()->addAlert(
                Alert::TYPE_DANGER,
                $message,
                'trustcaptcha_fail'
            );
        }

        $args['isValid'] = $isValid;
    }

}
