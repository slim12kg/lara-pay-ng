<?php


namespace LaraPayNG;

use LaraPayNG\Exceptions\UnspecifiedPayItemIdException;
use LaraPayNG\Exceptions\UnspecifiedTransactionAmountException;
use LaraPayNG\Exceptions\UnknownPaymentGatewayException;
use Illuminate\Contracts\Config\Repository as Config;

class Helpers
{
    /**
     * @var Repository
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $transactionId
     * @param array $transactionData
     * @param string $class
     * @param string $buttonTitle
     *
     * Render Pay Button For Particular Product
     *
     * @return string
     * @throws UnknownPaymentGatewayException
     */
    public function payButton($transactionId, $transactionData = [], $class = '', $buttonTitle = 'Pay Now')
    {
        $gateway = $this->getConfig('driver');

        return $this->generateSubmitButton($transactionId, $transactionData, $class, $buttonTitle, $gateway);
    }

    /**
     * @param float $amount
     *
     * @return string
     */
    public function inNaira($amount)
    {
        return '&#8358; ' . number_format($amount, 2);
    }

    /**
     * @param float $amount
     *
     * @return string
     */
    public function inDollars($amount)
    {
        return '$ ' . number_format($amount, 2);
    }

    /**
     * Building HTML Form
     *
     * Gateways use this method to build hidden form for product Buy Button.
     *
     * @param string $transactionId
     * @param array $transactionData
     * @param string $class
     * @param string $buttonTitle
     * @param string $gateway
     *
     * @throws UnknownPaymentGatewayException
     *
     * @return string HTML
     */
    protected function generateSubmitButton($transactionId, $transactionData, $class, $buttonTitle, $gateway)
    {
        switch (strtolower($gateway)) {
            case 'gtpay':
                return $this->generateSubmitButtonForGTPay($transactionId, $transactionData, $class, $buttonTitle);

                break;

            case 'webpay':
                return $this->generateSubmitButtonForWebPay($transactionId, $transactionData, $class, $buttonTitle);

                break;

            case 'voguepay':
                return $this->generateSubmitButtonForVoguePay($transactionId, $transactionData, $class, $buttonTitle);

                break;

            case 'simplepay':
                return $this->generateSubmitButtonForSimplePay($transactionId, $transactionData, $class, $buttonTitle);

                break;

            case 'cashenvoy':
                return $this->generateSubmitButtonForCashEnvoy($transactionId, $transactionData, $class, $buttonTitle);

                break;



            default:
                throw new UnknownPaymentGatewayException;

                break;
        }
    }



    /**
     * Generate Transaction Id For Product
     * @param $transactionId
     *
     * @return string
     */
    public function generateTransactionId($transactionId)
    {
        return $this->getConfig('transactionIdPrefix') . $transactionId;
    }

    /**
     * Generate Merchant Reference For Transaction
     * @param $transactionId
     *
     * @return string
     */
    public function generateMerchantReference($transactionId)
    {
        return $this->getConfig('MerchantReferencePrefix') . $transactionId;
    }

    /**
     * @param $transactionId
     * @param $transactionAmount
     * @param string $gateway
     *
     * GTPay:  gtpay_tranx_id + gtpay_tranx_amt + gtpay_tranx_noti_url + hashkey
     * WebPay: tnx_ref + product_id + pay_item_id + amount + site_redirect_url + <the provided MAC key>
     *
     * @param null $payItemId
     *
     * @return string
     * @throws UnknownPaymentGatewayException
     * @internal param $productId
     */
    public function generateTransactionHash($transactionId, $transactionAmount, $gateway = 'gtpay', $payItemId = null)
    {

        switch ($gateway) {
            case 'gtpay':
                return hash(
                    'sha512',
                    $transactionId . $transactionAmount .
                    route($this->getConfig('gtpay', 'gtpay_tranx_noti_url')) .
                    $this->getConfig('gtpay', 'hashkey'),
                    false
                );

                break;

            case 'webpay':
                return hash(
                    'sha512',
                    $this->generateTransactionId($transactionId) . $transactionId .
                    $payItemId . $transactionAmount .
                    route($this->getConfig('webpay', 'site_redirect_url')) .
                    $this->getConfig('webpay', 'hashkey'),
                    false
                );

                break;

            case 'voguepay':
                return true;
                break;

            default:
                throw new UnknownPaymentGatewayException;
                break;
        }
    }



    public function generateVerificationHash($tranx_id, $gateway = 'gtpay', $product_id = '4220')
    {
        if ($gateway == 'webpay') {
            //     productid, transactionreference and your hash key
//       $product_id = substr($tranx_id, strpos($tranx_id, 'D') + 1);

            return hash('sha512', $product_id . $tranx_id . $this->getConfig('webpay', 'hashkey'), false);
        }

//    mertid + tranxid + hashkey
        return hash('sha512', $this->getConfig('gtpay', 'mert_id') . $tranx_id . $this->getConfig('gtpay', 'hashkey'), false);
    }

    /**
     * @param $driver
     *
     * @throws UnknownPaymentGatewayException
     * @return string
     */
    protected function determineGatewayUrl($driver)
    {
        switch ($driver) {
            case 'gtpay':
                $gatewayUrl = $this->getConfig('gtpay', 'gatewayUrl');
                break;

            case 'webpay':
                $gatewayUrl = $this->getConfig('webpay', 'gatewayUrl');
                break;

            case 'voguepay':
                $gatewayUrl = $this->getConfig('voguepay', 'gatewayUrl');
                break;

            case 'simplepay':
                $gatewayUrl = $this->getConfig('simplepay', 'gatewayUrl');
                break;

            case 'cashenvoy':
                $gatewayUrl = $this->getConfig('cashenvoy', 'gatewayUrl');
                break;

            case 'default':
                throw new UnknownPaymentGatewayException;
                break;
        }

        return $gatewayUrl;
    }

    /**
     * @param $key
     *
     * Retrieve A Config Key From Default Gateway Array
     *
     * @return mixed
     */
    public function config($key)
    {
        return $this->getConfig($this->config->get('lara-pay-ng.gateways.driver'), $key);
    }

    /**
     * @param string $gateway
     * @param null|string $key
     *
     * @return array|mixed|string
     */
    protected function getConfig($gateway = '', $key = '*')
    {
        $keywithdot = '.' . $key;

        switch ($gateway) {
            case 'driver':
                return $this->config->get('lara-pay-ng.gateways.driver');
                break;

            case 'transactionIdPrefix':
                return $this->config->get('lara-pay-ng.gateways.transactionIdPrefix');
                break;

            case 'MerchantReferencePrefix':
                return $this->config->get('lara-pay-ng.gateways.MerchantReferencePrefix');
                break;


            case 'webpay':
                return $this->getGatewayConfig($gateway, $key, $keywithdot);
                break;

            case 'voguepay':
                return $this->getGatewayConfig($gateway, $key, $keywithdot);
                break;

            case 'gtpay':
                return $this->getGatewayConfig($gateway, $key, $keywithdot);
                break;

            case 'simplepay':
                return $this->getGatewayConfig($gateway, $key, $keywithdot);
                break;

            case 'cashenvoy':
                return $this->getGatewayConfig($gateway, $key, $keywithdot);
                break;

            default:
                return 'Unknown Config Variable Requested!!';
                break;
        }
    }

    /**
     * @param $transactionId
     * @param $transactionData
     * @param $class
     * @param $buttonTitle
     *
     * @throws UnspecifiedTransactionAmountException
     * @throws UnknownPaymentGatewayException
     * @return string
     */
    private function generateSubmitButtonForGTPay($transactionId, $transactionData, $class, $buttonTitle)
    {

//        <input type="hidden" name="gtpay_mert_id" value="17" />
//<input type="hidden" name="gtpay_tranx_id" value="" />
//<input type="hidden" name="gtpay_tranx_amt" value="5000" />
//<input type="hidden" name="gtpay_tranx_curr" value="566" />
//<input type="hidden" name="gtpay_cust_id" value="458742" />
//<input type="hidden" name="gtpay_cust_name" value="Test Customer" />
//<input type="hidden" name="gtpay_tranx_memo" value="Mobow" />
//<input type="hidden" name="gtpay_no_show_gtbank" value="yes" />
//<input type="hidden" name="gtpay_gway_name" value="" />
//<input type="hidden" name="gtpay_tranx_hash" value="" />
//<input type="hidden" name="gtpay_tranx_noti_url" value="" />
//<input type="submit" value="Pay Via GTPay" name="btnSubmit"/>
//<input type="hidden" name="gtpay_echo_data" value="DEQFOOIPP0;REG13762;John Adebisi: 2nd term school and accomodation fees;XNFYGHT325541;1209">

        $formId = 'PayViaGTPay';

        $gatewayUrl = $this->getConfig('gtpay', 'gatewayUrl');

        $hiddens = [ ];
        $addition = [ ];
        foreach ($transactionData as $key => $val) {
            if ((substr($key, 0, 5) == 'item_' OR substr($key, 0, 6) == 'price_' OR substr($key, 0, 12) == 'description_') === false) {
                $hiddens[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        foreach ($this->getConfig('gtpay') as $key => $val) {
            if ($key == 'gtpay_tranx_noti_url') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . route($val, $transactionId) . '" />' . "\n";
            }elseif (!is_null($this->getConfig('gtpay', $key)) and $key != 'gatewayUrl' and $key != 'hashkey'
                and $key != 'table') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        $tranxId = '<input type="hidden" name="gtpay_tranx_id" value="' . $transactionId . '" />' . "\n";
        $echodata = (isset($transactionData['gtpay_echo_data']))
            ? '<input type="hidden" name="gtpay_echo_data" value="' . $transactionData['gtpay_echo_data'] . '" />' . "\n"
            : '<input type="hidden" name="gtpay_echo_data" value="' . $transactionId . ';'. '" />' . "\n";

        if (! isset($transactionData['gtpay_tranx_amt'])) {
            throw new UnspecifiedTransactionAmountException;
        }

        $hash = '<input type="hidden" name="gtpay_tranx_hash" value="' . $this->generateTransactionHash($tranxId, $transactionData['gtpay_tranx_amt'], $gateway = 'gtpay') . '" />' . "\n";

        $addition[] = '<button type="submit"  class="' . $class . '">' . $buttonTitle . '</button>';

        $form = '<form method="POST" action="' . $gatewayUrl . '" id="' . $formId . '">' .
            $tranxId . implode('', $configs) . implode('', $hiddens) . $hash . $echodata . implode('', $addition) .
            '</form>';

        return $form;
    }

    /**
     * @param $transactionId
     * @param $transactionData
     * @param $class
     * @param $buttonTitle
     *
     * @throws UnknownPaymentGatewayException
     * @throws UnspecifiedPayItemIdException
     * @throws UnspecifiedTransactionAmountException
     * @return string
     */
    private function generateSubmitButtonForWebPay($transactionId, $transactionData, $class, $buttonTitle)
    {
        $formId = 'PayViaWebPay';

        $gatewayUrl = $this->getConfig('webpay', 'gatewayUrl') . '/pay';

        $hiddens = [ ];
        $addition = [ ];
        foreach ($transactionData as $key => $val) {
            $hiddens[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
        }

        foreach ($this->getConfig('webpay') as $key => $val) {
            if (!is_null($this->getConfig('webpay', $key)) and $key != 'gatewayUrl' and $key != 'hashkey') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        $transactionId[] = '<input type="hidden" name="txn_ref" value="' . $this->generateTransactionId($transactionId) . '" />' . "\n";
        $transactionId = '<input type="hidden" name="product_id" value="' . $transactionId . '" />' . "\n";

        if (! isset($transactionData['amount'])) {
            throw new UnspecifiedTransactionAmountException;
        }
        if (! isset($transactionData['pay_item_id'])) {
            throw new UnspecifiedPayItemIdException;
        }

        $hash = '<input type="hidden" name="hash" value="' . $this->generateTransactionHash($transactionId, $transactionData['amount'], 'webpay', $transactionData['pay_item_id']) . '" />' . "\n";


        $addition[] = '<button type="submit"  class="' . $class . '">' . $buttonTitle . '</button>';

        $form = '
            <form method="POST" action="' . $gatewayUrl . '" id="' . $formId . '">
                ' . implode('', $transactionId) . '
                ' . $transactionId . '
                ' . implode('', $configs) . '
                ' . implode('', $hiddens) . '
                ' . $hash . '
                ' . implode('', $addition) . '

            </form>
        ';

        return $form;
    }

    /**
     * @param $merchantRef
     * @param array $transactionData
     * @param string $class
     * @param string $buttonTitle
     *
     * @return string
     * @throws UnspecifiedTransactionAmountException
     */
    private function generateSubmitButtonForVoguePay($merchantRef, $transactionData, $class, $buttonTitle)
    {
        $voguePayButtons = [
            'buynow_blue.png', 'buynow_red.png', 'buynow_green.png', 'buynow_grey.png', 'addtocart_blue.png',
            'addtocart_red.png', 'addtocart_green.png', 'addtocart_grey.png', 'checkout_blue.png',
            'checkout_red.png', 'checkout_green.png', 'checkout_grey.png', 'donate_blue.png', 'donate_red.png',
            'donate_green.png', 'donate_grey.png', 'subscribe_blue.png', 'subscribe_red.png',
            'subscribe_green.png', 'subscribe_grey.png', 'make_payment_blue.png', 'make_payment_red.png',
            'make_payment_green.png', 'make_payment_grey.png',
        ];

        $formId = 'PayViaVoguePay';

        $gatewayUrl = $this->getConfig('voguepay', 'gatewayUrl');

        $hiddens = [ ];
        $configs = [ ];
        $addition = [ ];

        foreach ($transactionData as $key => $val) {
            if ($key != 'merchant_ref') {
                $hiddens[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        foreach ($this->getConfig('voguepay') as $key => $val) {
            if ($key == 'notify_url' or $key == 'success_url' or $key == 'fail_url') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . route($val, $merchantRef) . '" />' . "\n";
            } elseif (!is_null($this->getConfig('voguepay', $key)) and $key != 'submitButton' and $key != 'table') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        if ((isset($transactionData['total']) OR array_key_exists('price_1', $transactionData)) === false) {
            throw new UnspecifiedTransactionAmountException;
        }

        $merchantRef = '<input type="hidden" name="merchant_ref" value="' . $merchantRef . '" />' . "\n";

        $defaultButton = $this->getConfig('voguepay', 'submitButton');

        $addition[] = in_array($defaultButton, $voguePayButtons)
            ? '<input type="image"  src="//voguepay.com/images/buttons/' . $defaultButton . '" alt="Submit">'
            : '<input type="submit"  class="' . $class . '">' . $buttonTitle . '</input>';

        $form = '<form method="POST" action="' . $gatewayUrl . '" id="' . $formId . '">' .
            implode('', $configs) . $merchantRef . implode('', $hiddens) . implode('', $addition) .
            '</form>';

        return $form;
    }


    /**
     * @param $merchantRef
     * @param array $transactionData
     * @param string $class
     * @param string $buttonTitle
     *
     * @return string
     */
    private function generateSubmitButtonForSimplePay($merchantRef, $transactionData, $class, $buttonTitle)
    {
        $simplePayButtons = [
            'simplepaylogoescrow.gif', 'simplepaylogo.gif', 'simplepaysubscribe.gif',
            'spaccepted.png', 'simplepaydonatenow.gif',
        ];

        $formId = 'PayViaSimplePay';

        $gatewayUrl = $this->getConfig('simplepay', 'gatewayUrl');

        $hiddens = [ ];
        $configs = [ ];
        $addition = [ ];

        foreach ($transactionData as $key => $val) {
            if ($key != 'member') {
                $hiddens[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        foreach ($this->getConfig('simplepay') as $key => $val) {
            if ($key == 'notify_url' or $key == 'success_url' or $key == 'fail_url') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . route($val, $merchantRef) . '" />' . "\n";
            } elseif (!is_null($this->getConfig('simplepay', $key)) and $key != 'submitButton' and $key != 'table') {
                $configs[] = '<input type="hidden" name="' . $key . '" value="' . $val . '" />' . "\n";
            }
        }

        $merchantRef = '<input type="hidden" name="member" value="' . $merchantRef . '" />' . "\n";

        $defaultButton = $this->getConfig('simplepay', 'submitButton');

        $addition[] = in_array($defaultButton, $simplePayButtons)
            ? '<input type="image"  src="https://simplepay4u.com/hlib/images/client_img/' .
            $defaultButton . '" alt="Submit">'

            : '<input type="submit"  class="' . $class . '">' . $buttonTitle . '</input>';

        $form = '<form method="POST" action="' . $gatewayUrl . '" id="' . $formId . '">
                    ' . implode('', $configs) . '
                    ' . $merchantRef . '
                    ' . implode('', $hiddens) . '
                    ' . implode('', $addition) . '
                </form>';

        return $form;
    }


    /**
     * @param $gateway
     * @param $key
     * @param $keywithdot
     *
     * @return mixed|string
     */
    private function getGatewayConfig($gateway, $key, $keywithdot)
    {
        if ($key == '*') {
            return $this->config->get('lara-pay-ng.gateways.' . $gateway);
        }

        if (! array_key_exists($key, $this->config->get('lara-pay-ng.gateways.' . $gateway))) {
            return 'Trying to get an Unknown ' . $gateway . ' Config';
        }

        return $this->config->get('lara-pay-ng.gateways.'. $gateway . $keywithdot);
    }

    protected function sumItemPrices($transactionData)
    {
        $total = 0;

        foreach ($transactionData as $key => $value) {
            if (strpos($key, 'price_') === 0) {
                $total += $value;
            }
        }

        return $total;
    }

    /**
     * @param $result
     *
     * @return array
     */
    protected function collateResponse($result)
    {
        switch($result){
            case (isset($result->r_gtpay_tranx_id)):
                return [
                    'status'         => $result->gtpay_response_description,
                    'transaction_id' => $result->r_gtpay_tranx_id,
                    'merchant_ref'   => (!empty($result->gtpay_merchant_ref)) ? $result->gtpay_merchant_ref : 'N/A',
                    'amount'         => ($result->gtpay_tranx_curr == '844')
                        ? $this->inDollars($result->r_gtpay_amount)
                        : $this->inNaira($result->r_gtpay_amount),
                    'customer_id'    => $result->gtpay_cust_id
                ];
                break;

            case (isset($result->v_transaction_id)):
                return [
                    'status'         => $result->status,
                    'transaction_id' => $result->v_transaction_id,
                    'merchant_ref'   => $result->merchant_ref,
                    'amount'         => $this->inNaira($result->v_total_paid),
                    'customer_id'    => $result->v_email
                ];
                break;

//            case (isset($result->v_transaction_id)):
//                return [
//                    'status'         => $result->gtpay_response_description,
//                    'transaction_id' => $result->v_transaction_id,
//                    'merchant_ref'   => $result->gtpay_merchant_ref,
//                    'amount'         => $result->r_gtpay_amount,
//                    'customer_id'    => $result->gtpay_cust_id
//                ];
//                break;

//            case (isset($result->v_transaction_id)):
//                return [
//                    'status'         => $result->gtpay_response_description,
//                    'transaction_id' => $result->v_transaction_id,
//                    'merchant_ref'   => $result->gtpay_merchant_ref,
//                    'amount'         => $result->r_gtpay_amount,
//                    'customer_id'    => $result->gtpay_cust_id
//                ];
//                break;

//            case (isset($result->v_transaction_id)):
//                return [
//                    'status'         => $result->gtpay_response_description,
//                    'transaction_id' => $result->v_transaction_id,
//                    'merchant_ref'   => $result->gtpay_merchant_ref,
//                    'amount'         => $result->r_gtpay_amount,
//                    'customer_id'    => $result->gtpay_cust_id
//                ];
//                break;
        }

    }
}
