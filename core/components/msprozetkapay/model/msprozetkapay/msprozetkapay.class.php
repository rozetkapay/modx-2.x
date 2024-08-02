<?php

require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');

class MspRozetkaPay
{
    /** @var modX $modx */
    public $modx;

    /** @var \RozetkaPay\Api\Payment */
    public $rpay;

    /** @var miniShop2 $ms2 */
    public $ms2;

    /**
     * MspRozetkaPay constructor.
     * @param modX $modx
     * @param array $config
     */
    function __construct(modX &$modx, array $config = array())
    {
        $this->modx = &$modx;
        $this->modx->lexicon->load('msprozektapay:default');
        $siteUrl = $this->modx->getOption('site_url');
        $corePath = $modx->getOption('msprozetkapay.core_path', $config, $modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/msprozetkapay/');
        $assetsUrl = $modx->getOption('msprozetkapay.assets_url', $config, $modx->getOption('assets_url') . 'components/msprozetkapay/');
        $assetsPath = $modx->getOption('msprozetkapay.assets_path', $config, $modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/msprozetkapay/');
        $assetsUrlFull = rtrim($siteUrl, '/\\') . $assetsUrl;

        $this->config = array_merge(array(
            'corePath' => $corePath,
            'assetsUrl' => $assetsUrl,
            'assetsUrlFull' => $assetsUrlFull,
            'assetsPath' => $assetsPath,
            'modelPath' => $corePath . 'model/',
            'login' => $modx->getOption('msprozetkapay_login', null, 'a6a29002-dc68-4918-bc5d-51a6094b14a8'),
            'password' => $modx->getOption('msprozetkapay_password', null, 'XChz3J8qrr'),
            'currency' => $modx->getOption('msprozetkapay_currency', null, 'UAH'),
            'refund_status_id' => $modx->getOption('msprozetkapay_refound_status_id', null, 0),
            'resultUrl' => $assetsUrlFull . 'result.php',
            'callbackUrl' => $assetsUrlFull . 'callback.php'
        ), $config);
        $this->modx->addPackage('msprozektapay', $this->config['modelPath']);

        \RozetkaPay\Configuration::setBasicAuth($this->config['login'], $this->config['password']);
        //\RozetkaPay\Configuration::setResultUrl($this->config['resultUrl']);
        \RozetkaPay\Configuration::setCallbackUrl($this->config['callbackUrl']);
        $this->rpay = new \RozetkaPay\Api\Payment();
    }

    /**
     * @param string $ctx
     * @param array $config
     * @return  miniShop2|null
     */
    public function getMs2($ctx = '', $config = array())
    {
        if (!$this->hasAddition('minishop2')) return null;
        $ctx = $ctx ? $ctx : $this->modx->context->key;
        if (class_exists('miniShop2') && (!isset($this->ms2) || !is_object($this->ms2))) {
            $this->ms2 = $this->modx->getService('miniShop2');
            $this->ms2->initialize($ctx, $config);
        }

        return empty($this->ms2) ? null : $this->ms2;
    }

    /**
     * @param msOrder $order
     * @return void
     */
    public function getPaymentUrl(msOrder $order)
    {
        $dataRequest = new \RozetkaPay\Model\Payment\RequestCreatePay();
        $address = $order->getOne('Address');

        $dataRequest->amount = $order->get('cost');
        $dataRequest->external_id = $order->get('id') . '_' . $order->get('num');
        $dataRequest->description = 'Номер замовлення: №' . $order->get('num');
        $dataRequest->confirm = true;
        $dataRequest->currency = $this->config['currency'];

        if ($list = $order->getMany('Products')) {
            foreach ($list as $product) {
                $productName = $this->prepareProductName($product->get('name'));
                $productCount = $product->get('count');
                $products[] = [
                    'currency' => $this->config['currency'],
                    'name' => $productName,
                    'quantity' => strval($productCount),
                    'net_amount' => $product->get('price'),
                    'vat_amount' => $product->get('price') * $productCount
                ];
            }
        }

        $dataRequest->products = $products;

        $receiver = $this->explodeAndClean($address->get('receiver'), ' ');
        $lastName = !empty($receiver[0]) ? $receiver[0] : '';
        $firstName = !empty($receiver[1]) ? $receiver[1] : '';

        $dataRequest->customer = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $order->getOne('UserProfile')->get('email'),
            'phone' => $this->preparePhone($address->get('phone'))
        ];

        \RozetkaPay\Configuration::setResultUrl($this->config['resultUrl'] . "?external_id=" . $dataRequest->external_id);

        list($data, $error) = $this->rpay->create($dataRequest);
        if ($data !== false) {
            return $data->getCheckoutUrl();
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[mspRozetkaPay] Error create Paymants ' . $error->message . ' code: ' . $error->code);
        }
    }

    public function refund(msOrder $order)
    {
        $dataRequest = new \RozetkaPay\Model\Payment\RequestRefund();

        $dataRequest->external_id = $order->get('id') . '_' . $order->get('num');
        $dataRequest->amount = $order->get('cost');

        /**  @var \RozetkaPay\Model\Payment\Responses $result */
        /**  @var \RozetkaPay\Model\ResponsesError $error */
        list($result, $error) = $this->rpay->refund($dataRequest);

        if($result === false){
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[mspRozetkaPay] Error refund Paymants: ' . $error->message . ' code: ' . $error->code);
        }
    }

    /**
     * @param string $addition
     * @return bool
     */
    public function hasAddition($addition = '')
    {
        $addition = strtolower($addition);
        return file_exists(MODX_CORE_PATH . 'components/' . $addition . '/model/' . $addition . '/');
    }

    /**
     * @param string $name
     * @return string
     */
    public function prepareProductName($name)
    {
        return str_replace(array("'", '"', '&#39;', '&'), '', htmlspecialchars_decode($name));
    }

    /**
     * @param string $phone
     * @return string
     */
    public function preparePhone($phone)
    {
        $phone = str_replace(array('+', ' ', '(', ')'), array('', '', '', ''), $phone);
        if (strlen($phone) == 10) {
            $phone = '38' . $phone;
        } elseif (strlen($phone) == 11) {
            $phone = '3' . $phone;
        }
        return $phone;
    }

    /**
     * @param string $str
     * @param string $delimiter
     * @return array
     */
    public function explodeAndClean($str, $delimiter = ',')
    {
        $array = explode($delimiter, $str);
        $array = array_map('trim', $array);
        $array = array_keys(array_flip($array));
        $array = array_filter($array);

        return $array;
    }
}