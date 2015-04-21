<?php

class WayForPay
{
    protected $url = 'https://secure.wayforpay.com/pay';
    protected static $Inst = false;
    protected $merchantAccount;
    protected $secretKey;
    private $data = [];

    protected $keysForResponseSignature = array(
        'merchantAccount',
        'orderReference',
        'amount',
        'currency',
        'authCode',
        'cardPan',
        'transactionStatus',
        'reasonCode'
    );

    /** @var array */
    protected $keysForSignature = array(
        'merchantAccount',
        'merchantDomainName',
        'orderReference',
        'orderDate',
        'amount',
        'currency',
        'productName',
        'productCount',
        'productPrice'
    );

    protected $answer = '';

    private function __construct()
    {
        $this->merchantAccount = get_option('wayforpay_merchantAccount');
        $this->secretKey = get_option('wayforpay_secret_key');
    }

    private function __clone()
    {
    }

    public function __toString()
    {
        return ($this->answer === "") ? "<!-- Answer are not exists -->" : $this->answer;
    }

    /**
     * @return bool|WayForPay
     */
    public static function getInst()
    {
        if (self::$Inst === false) self::$Inst = new WayForPay();
        return self::$Inst;
    }

    /**
     * @param $options
     * @return string
     */
    public function getRequestSignature($options)
    {
        return $this->getSignature($options, $this->keysForSignature);
    }

    /**
     * @param $options
     * @return string
     */
    public function getResponseSignature($options)
    {
        return $this->getSignature($options, $this->keysForResponseSignature);
    }

    /**
     * @param $option
     * @param $keys
     * @return string
     */
    public function getSignature($option, $keys)
    {
        $hash = array();
        foreach ($keys as $dataKey) {
            if (!isset($option[$dataKey])) {
                continue;
            }
            if (is_array($option[$dataKey])) {
                foreach ($option[$dataKey] as $v) {
                    $hash[] = $v;
                }
            } else {
                $hash [] = $option[$dataKey];
            }
        }
        $hash = implode(';', $hash);

        return hash_hmac('md5', $hash, $this->secretKey);
    }

    /**
     * @return $this
     */
    public function fillPayForm($data)
    {
        $data['merchantAccount'] = $this->merchantAccount;
        $data['merchantAuthType'] = 'simpleSignature';
        $data['merchantDomainName'] = $_SERVER['SERVER_NAME'];
        $data['merchantTransactionSecureType'] = 'AUTO';

        $data['merchantSignature'] = $this->getRequestSignature($data);
        $this->answer = $this->generateForm($data);
        return $this;
    }


    /**
     * Generate form with fields
     *
     * @param $data
     * @return string
     */
    protected function generateForm($data)
    {
        $form = '<form method="post" id="form_wayforpay" action="' . $this->url . '" accept-charset="utf-8">';
        foreach ($data as $k => $v) $form .= $this->printInput($k, $v);
        return $form . "<input type='submit' style='display:none;' /></form>";
    }

    /**
     * Print inputs in form
     *
     * @param $name
     * @param $val
     * @return string
     */
    protected function printInput($name, $val)
    {
        $str = "";
        if (!is_array($val)) return '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($val) . '">' . "\n<br />";
        foreach ($val as $v) $str .= $this->printInput($name . '[]', $v);
        return $str;
    }


    /**
     * @param $inputData
     * @return mixed|string|void
     */
    public function checkResponse($inputData)
    {
        global $wpdb;
        $ref = $inputData['orderReference'];
        $sessID = explode("_", $ref);
        $sessionId = $sessID[1];

        $sign = $this->getResponseSignature($inputData);
        if (!empty($inputData["merchantSignature"]) && $inputData["merchantSignature"] == $sign) {
            if ($inputData['transactionStatus'] == 'Approved') {

                $notes = "WayForPay : orderReference:" . $inputData['transactionStatus'] . " \n\n recToken: " . $inputData['recToken'];

                $data = array(
                    'processed' => 3,
                    'transactid' => $ref,
                    'date' => time(),
                    'notes' => $notes
                );


                $where = array('transactid' => $ref);
                $format = array('%d', '%s', '%s', '%s');
                $wpdb->update(WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format);
                transaction_results($sessionId, false, $ref);
                return $this->getAnswerToGateWay($inputData);
            }

        }
        return null;

    }


    /**
     * @param $data
     * @return mixed|string|void
     */
    public function getAnswerToGateWay($data)
    {
        $time = time();
        $responseToGateway = array(
            'orderReference' => $data['orderReference'],
            'status' => 'accept',
            'time' => $time
        );
        $sign = array();
        foreach ($responseToGateway as $dataKey => $dataValue) {
            $sign [] = $dataValue;
        }
        $sign = implode(';', $sign);
        $sign = hash_hmac('md5', $sign, $this->secretKey);
        $responseToGateway['signature'] = $sign;

        return json_encode($responseToGateway);
    }


    /**
     * @param $url
     * @param $data
     * @return bool
     */
    protected function sendRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);

        curl_close($ch);
        return true;
    }
}

