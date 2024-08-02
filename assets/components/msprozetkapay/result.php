<?php
/**
 * @var modX $modx
 * @var MspRozetkaPay $msprozetkapay
 */

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/index.php';

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');

$msprozetkapay = $modx->getService('msprozetkapay', 'MspRozetkaPay');
$returnUrlId = $modx->getOption('site_start', null, 1, true);
$failureId = $modx->getOption('msprozetkapay_failure_id', null, 0, true);
$orderId = $msprozetkapay->explodeAndClean($_GET['external_id'], '_');

if($orderId[0]) {
    $successId = $modx->getOption('msprozetkapay_success_id', null, 0, true);
    $orderStatus = $modx->getObject('msOrder', $orderId[0])->get('status');

    if($orderStatus == 2) {
        if ($successId) {
            $returnUrlId = $successId;
            $params = array('msorder' => $orderId[0]);
        }
    } else  {
        if ($failureId) {
            $returnUrlId = $failureId;
            $params = array('msorder' => $orderId[0]);
        }
    }

    $redirect = $modx->makeUrl($returnUrlId, '', $params, 'full');

} else {
    if ($failureId) {
        $returnUrlId = $failureId;
    }

    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] No Order ID: ' . print_r($orderId,true));
    $redirect = $modx->makeUrl($returnUrlId, '', '', 'full');
}

$modx->sendRedirect($redirect);