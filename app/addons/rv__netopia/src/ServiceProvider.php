<?php

namespace Tygh\Addons\RvNetopia;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\RvNetopia\HookHandlers\PaymentsHookHandler;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Container $app)
    {
        $app['addons.rv__netopia.hook_handlers.payments'] = static function (Container $app) {
            return new PaymentsHookHandler();
        };
    }
}
