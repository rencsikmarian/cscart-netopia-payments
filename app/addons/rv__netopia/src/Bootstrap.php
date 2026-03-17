<?php

namespace Tygh\Addons\RvNetopia;

use Tygh\Core\ApplicationInterface;
use Tygh\Core\BootstrapInterface;
use Tygh\Core\HookHandlerProviderInterface;

class Bootstrap implements BootstrapInterface, HookHandlerProviderInterface
{
    public function boot(ApplicationInterface $app)
    {
        $app->register(new ServiceProvider());
    }

    /**
     * @return array
     */
    public function getHookHandlerMap()
    {
        return [
            'update_payment_pre' => [
                'addons.rv__netopia.hook_handlers.payments',
                'onUpdatePaymentPre',
            ],
            'update_payment_post' => [
                'addons.rv__netopia.hook_handlers.payments',
                'onUpdatePaymentPost',
            ],
        ];
    }
}
