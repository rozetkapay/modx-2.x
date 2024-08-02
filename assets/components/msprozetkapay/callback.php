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

/**  @var \RozetkaPay\Model\Payment\Responses $result */
/**  @var \RozetkaPay\Model\ResponsesError $error */
list($result, $error) = $msprozetkapay->rpay->callbacks();

if($result !== false) {
    $orderId = $msprozetkapay->explodeAndClean($result->external_id, '_');

    if($result->details->status == "success") {
        if ($order = $modx->getObject('msOrder', $orderId[0])) {
            if ($order->get('status') != 2 && $ms2 = $msprozetkapay->getMs2()) {
                if (!$ms2->changeOrderStatus($order->get('id'), 2)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] Error Change Order Status! ' . $orderId);
                }
            }
        } else {
            $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] Could not retrieve order with id ' . $orderId);
        }
    }
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] SDK exception: ' . $error->message . $error->code);
//    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] SDK exception 1: ' . $msprozetkapay->rpay->getHeaderSignature());
//    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] SDK exception 2: ' . $msprozetkapay->rpay->getSignature(file_get_contents('php://input')));
//    $modx->log(modX::LOG_LEVEL_ERROR, '[MspRozetkaPay] SDK exception 3: ' . print_r(file_get_contents('php://input'),true));
    header("HTTP/1.0 400 Bad Request");
}

session_write_close();