<?php

/** @var modX $modx */
/** @var mspRozetkaPay $msprozetkapay */
/** @var $status id status */
/** @var $order msOrder */
/** @var msPayment $payment */

switch ($modx->event->name) {
    case 'msOnChangeOrderStatus':
        $msprozetkapay = $modx->getService('msprozetkapay', 'MspRozetkaPay');
        $rerundStatusId = $msprozetkapay->config['refund_status_id'];
        $payment = $modx->getObject('msPayment', array('id' => $order->get('payment'), 'active' => 1));

        if ($payment && $payment->get('class') == 'RozetkaPay') {
            if ($rerundStatusId && $status == $msprozetkapay->config['refund_status_id']) {
                $msprozetkapay->refund($order);
            }
        }

        break;
}