<?php

if (!class_exists('msPaymentInterface')) {
    $file = MODX_CORE_PATH . 'components/minishop2/handlers/mspaymenthandler.class.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        require_once MODX_CORE_PATH . 'components/minishop2/model/minishop2/mspaymenthandler.class.php';
    }
}
class RozetkaPay extends msPaymentHandler implements msPaymentInterface
{
    /** @var mspRozektaPay $rpay */
    public $rpay;

    function __construct(xPDOObject $object, $config = array())
    {
        parent::__construct($object, $config);
        $this->rpay = $this->modx->getService('msprozetkapay', 'MspRozetkaPay');
    }

    /* @inheritdoc} */
    public function send(msOrder $order)
    {
        $redirect = $this->rpay->getPaymentUrl($order);
        return $this->success('', array('redirect' => $redirect));
    }
}