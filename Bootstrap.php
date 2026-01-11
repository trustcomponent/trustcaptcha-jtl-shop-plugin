<?php declare(strict_types=1);

namespace Plugin\trustcaptcha;

use JTL\Alert\Alert;
use JTL\Consent\Item;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Link\LinkInterface;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Helpers\Log;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use Laminas\Diactoros\ServerRequestFactory;

class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        // Admin-Tab rendern
        $dispatcher->listen('plugin.adminmenu.render', function ($menuItem, JTLSmarty $smarty): void {
            $this->renderAdminTab($menuItem, $smarty);
        });

        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_CONFIGURED, [$this, 'tcConfigured']);
        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_MARKUP, [$this, 'tcMarkup']);
        $dispatcher->listen('shop.hook.' . \HOOK_CAPTCHA_VALIDATE, [$this, 'tcValidate']);
    }

    /**
     * Admin-Tab rendern
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $plugin     = $this->getPlugin();

        // Admin-URL ermitteln
        $backendURL = \method_exists($plugin->getPaths(), 'getBackendURL')
            ? $plugin->getPaths()->getBackendURL()
            : Shop::getAdminURL() . '/plugin.php?kPlugin=' . $plugin->getID();

        $smarty->assign('menuID', $menuID)
            ->assign('posted', null)
            ->assign('backendURL', $backendURL)
            ->assign('jtl_token', Form::getTokenInput());

        $template = 'testtab.tpl';

        if ($tabName === 'Ein Testtab') {
            $template = 'testtab.tpl';

            if (Request::postInt('clear-cache') === 1) {
                $alert = Shop::Container()->getAlertService();
                if (Form::validateToken()) {
                    $this->getCache()->flushTags($plugin->getCache()->getGroup());
                    $alert->addAlert(Alert::TYPE_SUCCESS, \__('Cache successfully flushed.'), 'succCacheFlush');
                } else {
                    $alert->addAlert(Alert::TYPE_ERROR, \__('CSRF error!'), 'failedCsrfCheck');
                }
            }
        }

        if ($tabName === 'Tab2') {
            $template = 'tab2.tpl';
            if (Form::validateToken()) {
                $posted = Request::postVar('tab2_input');
                if ($posted !== null) {
                    $smarty->assign('posted', $posted);
                }
            }
        }

        if ($tabName === 'Einstellungen') {
            $template = 'settings.tpl';
        }

        return $smarty->fetch(
            $plugin->getPaths()->getAdminPath() . '/templates/' . $template
        );
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

        // z.B. beide Schlüssel vorhanden?
        $args['isConfigured'] = true; // TODO
    }


    public function tcMarkup(array &$args): void
    {
        $args['markup'] = (isset($args['getBody']) && $args['getBody'])
            ? $this->getCaptcha()->getMarkup()
            : '<script src="https://cdn.trustcomponent.com/trustcaptcha/2.1.x/trustcaptcha.umd.min.js"></script>';

    }


    public function tcValidate(array &$args): void
    {
        // TODO Validierung
        $plugin = $this->getPlugin();

        $captcha = new \Plugin\trustcaptcha\TrustCaptcha($plugin);

        // Token lesen
        $token = $captcha->getToken();

        if ($token === null || $token === '') {
            $args['isValid'] = false;
            $args['error']   = 'no-token';
            return;
        }

        // API prüfen
        $result = $captcha->verify($token);

        // Score-Schwelle aus Plugin-Einstellungen
        $minScore = (float)($plugin->getConfig()->getValue('trustcaptcha_min_score') ?? 0.5);

        // Decide
        if ($result['valid'] === true && $result['score'] !== null && $result['score'] >= $minScore) {
            $args['isValid'] = true;
            $args['score']   = $result['score'];
            return;
        }

        // Fail
        $args['isValid'] = false;
        $args['score']   = $result['score'] ?? null;
        $args['error']   = $result['error'] ?? 'low-score-or-invalid';
    }

}
