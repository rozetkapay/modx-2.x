<?php
/** @var xPDOTransport $transport */
/** @var array $options */
if ($transport->xpdo) {
    /** @var modX $modx */
    $modx = &$transport->xpdo;

    /** @var miniShop2 $miniShop2 */
    if (!$miniShop2 = $modx->getService('miniShop2')) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[mspRozetkaPay] Could not load miniShop2');

        return false;
    }
    if (!property_exists($miniShop2, 'version') || version_compare($miniShop2->version, '2.4.0-pl', '<')) {
        $modx->log(modX::LOG_LEVEL_ERROR,
            '[mspRozetkaPay] You need to upgrade miniShop2 at least to version 2.4.0-pl');

        return false;
    }

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx->addExtensionPackage('msprozetkapay', '[[++core_path]]components/msprozetkapay/model/');
            $miniShop2->addService('payment', 'msprozetkapay', '{core_path}components/msprozetkapay/handlers/payment/rozetkapay.class.php');
            /** @var msPayment $payment */
            if (!$payment = $modx->getObject('msPayment', array('class' => 'RozetkaPay'))) {
                $payment = $modx->newObject('msPayment');
                $payment->fromArray(array(
                    'name' => 'RozetkaPay',
                    'active' => false,
                    'class' => 'RozetkaPay',
                    'rank' => $modx->getCount('msPayment'),
                    'logo' => MODX_ASSETS_URL . 'components/msprozetkapay/rpay.png',
                ), '', true);
                $payment->save();
            }

        case xPDOTransport::ACTION_UNINSTALL:
            $miniShop2->removeService('payment', 'msPayment');
            $modx->removeCollection('msPayment', array('class' => 'msPayment'));
            break;
    }
}

return true;