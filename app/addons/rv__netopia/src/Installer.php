<?php

namespace Tygh\Addons\RvNetopia;

use Tygh\Addons\InstallerInterface;
use Tygh\Addons\RvNetopia\Payments\NetopiaGateway;
use Tygh\Core\ApplicationInterface;

class Installer implements InstallerInterface
{
    /** @var ApplicationInterface */
    protected $application;

    public function __construct(ApplicationInterface $app)
    {
        $this->application = $app;
    }

    /**
     * @inheritDoc
     */
    public static function factory(ApplicationInterface $app)
    {
        return new self($app);
    }

    /**
     * @inheritDoc
     */
    public function onBeforeInstall()
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function onInstall()
    {
        $db = $this->application['db'];

        if ($db->getField(
            'SELECT type FROM ?:payment_processors WHERE processor_script = ?s',
            NetopiaGateway::getScriptName()
        )) {
            return;
        }

        $db->query('INSERT INTO ?:payment_processors ?e', [
            'processor'          => 'Netopia Payments',
            'processor_script'   => NetopiaGateway::getScriptName(),
            'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
            'admin_template'     => 'rv__netopia.tpl',
            'callback'           => 'Y',
            'type'               => 'P',
            'addon'              => NetopiaGateway::getAddonName(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function onUninstall()
    {
        $db = $this->application['db'];

        $processor_id = $db->getField(
            'SELECT processor_id FROM ?:payment_processors WHERE processor_script = ?s',
            NetopiaGateway::getScriptName()
        );

        if (!$processor_id) {
            return;
        }

        $db->query('DELETE FROM ?:payment_processors WHERE processor_id = ?i', $processor_id);
        $db->query(
            'UPDATE ?:payments SET ?u WHERE processor_id = ?i',
            [
                'processor_id'     => 0,
                'processor_params' => '',
                'status'           => 'D',
            ],
            $processor_id
        );
    }
}
