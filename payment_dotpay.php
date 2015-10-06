<?php
/*
 * --------------------------------------------------------------------------------
   Dotpay Payment Plugin
 * --------------------------------------------------------------------------------
 * @package		Joomla! 2.5x
 * @subpackage	J2 Store
 * @author    	Dotpay (Dawid Pych)
 * @copyright	Copyright (c) 2015 Dotpay. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link		http://www.dotpay.com
 * --------------------------------------------------------------------------------
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

//include necessary libraries for plugin
require_once JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php';
require_once JPATH_ADMINISTRATOR . '/components/com_j2store/helpers/j2store.php';

/**
 * Class plgJ2StorePayment_dotpay
 */
class plgJ2StorePayment_dotpay extends J2StorePaymentPlugin {

    /**
     * Name of plugin
     * @var string
     */
    var $_element   = 'payment_dotpay';

    /**
     * Log errors
     * @var bool
     */
    var $_isLog     = false;

    /**
     * URLs for development and test environment
     * @var array
     */
    protected $_url = array(
        0 => 'https://ssl.dotpay.pl/',
        1 => 'https://ssl.dotpay.pl/test_payment/'
    );

    /**
     * Default config for plugin
     * @var array
     */
    protected $_default = array(
        'api_version' => 'dev',
        'channel'   => '',
        'ch_lock'   => 0,
        'type'      => 0,
    );

    /**
     * Available currencies
     * @var array
     */
    protected $_currency = array(
        'PLN', 'EUR', 'USD', 'GBP', 'JPY', 'CZK', 'SEK'
    );

    /**
     * Status OK
     */
    const STATUS_OK = 'OK';

    /**
     * Status FAIL
     */
    const STATUS_FAIL = 'FAIL';

    /**
     * Status operation completed
     */
    const OPERATION_STATUS_COMPLETED = 'completed';

    /**
     * Status operation rejected
     */
    const OPERATION_STATUS_REJECTED = 'rejected';

    const VALIDATION_PRICE = 'miss match price';

    const VALIDATION_IP = 'wrong ip';

    const VALIDATION_TOKEN = 'miss match token';

    const VALIDATION_CURRENCY = 'miss match currency';

    /**
     * @inheritdoc
     * @param object $subject
     * @param array $config
     */
    public function __construct($subject, $config) {
        parent::__construct($subject, $config);
        $this->loadLanguage( 'plg_j2store_payment_dotpay', JPATH_ADMINISTRATOR );
    }

    /**
     * @inheritdoc
     * Prepare for payment
     *
     * @param array $data
     * @return string
     */
    public function _prePayment( $data ) {
        $vars   = new JObject();
        $info   = $this->getOrderInformation($data);

        //Needed for Dotpay
        $vars->id           = $this->params->get('accountId');
        $vars->amount       = $data['orderpayment_amount'];
        $vars->currency     = $data['order']->currency_code;
        $vars->description  = JText::_('J2STORE_PLUGIN_DOTPAY_ORDER') . $data['order_id'];
        $vars->lang         = $this->getLanguage();
        $vars->api_version  = $this->_default['api_version'];

        //Additional data
        $vars->channel      = (string) $this->_default['channel'];
        $vars->ch_lock      = (string) $this->_default['ch_lock'];
        $vars->url          = $this->getBaseUrl(). "index.php?option=com_j2store&task=checkout.confirmPayment&orderpayment_type=payment_dotpay";
        $vars->type         = $this->_default['type'];
        $vars->urlc         = $this->getBaseUrl() . "index.php?option=com_j2store&task=checkout.confirmPayment&orderpayment_type=payment_dotpay&notification=true";
        $vars->control      = $data['order_id'];
        $vars->firstname    = $info->billing_first_name;
        $vars->lastname     = $info->billing_last_name;
        $vars->email        = $data['order']->user_email;
        $vars->street       = $info->billing_address_1;
        $vars->city         = $info->billing_city;
        $vars->postcode     = $info->billing_zip;
        $vars->phone        = $info->billing_phone_1!='' ? $info->billing_phone_1 : $info->billing_phone_2;
        $vars->country      = $this->getCountryById($info->billing_country_id)->country_isocode_2;
        $vars->p_info       = $this->params->get('accountId');

        $vars->payment_url  = $this->_url[$this->params->get('sandbox', 0)];

        if (in_array($data['order']->currency_code, $this->_currency)) {
            $html = $this->_getLayout('prepayment', $vars);
        } else {
            $html = $this->_getLayout('wrongcurrency');
        }

        return $html;
    }

    /**
     * Get base url depend on option. This method override original base url
     * with https if it is necessary
     *
     * @return mixed|string
     */
    private function getBaseUrl()
    {
        if($this->params->get('ssl', 0) == 0){
            return JURI::base();
        }
        return  str_replace("http://", "https://", JURI::base());
    }

    /**
     * This method is responsible both form processing notification and
     *  generate 'thank you' message. Depend on notification parameter getting from
     *  $_GET it trigger processing notification or generateing 'thank you' message
     *
     * @inheritdoc
     * Response status
     * @param array $data
     * @throws Exception
     */
    public function _postPayment( $data ) {

        $app = JFactory::getApplication();
        $isNotification = $app->input->get->get('notification');

        if($isNotification){
            $this->processNotification($app->input);
            $app->close();
        }

        $status = $app->input->get->getString('status');
        $message =$this->createConfirmMessage($status);
        return  $this->_getLayout('postpayment', $message);

    }

    /**
     * This method process notification from dotpay. First it trigger few validation
     * and then based on validation status change status of order
     *
     * @param $data
     */
    private function processNotification($data)
    {
        $status = $this->getValidation($data);
        $orderId = $data->getString('control');
        switch($status){
            case self::OPERATION_STATUS_COMPLETED;
                $this->setCompleteStatus($orderId);
                echo self::STATUS_OK;
                break;
            case self::OPERATION_STATUS_REJECTED;
                $this->setWrongStatus($orderId, 6); // 6 status = canceled
                echo self::STATUS_OK;
                break;
            default:
                $this->setWrongStatus($orderId, 3); // 3 status = wrong
                echo $status;
        }
    }

    /**
     * Set complete status. This method is internal j2store method which should
     * set everything what is necessary after complete payment
     *
     * @param $orderId
     */
    private function setCompleteStatus($orderId)
    {
        $order = $this->getOrder($orderId);
        $order->payment_complete ();
        $this->save($order);
    }


    /**
     * This method change order status. Status is defined as $order_state_id
     * allowed id are:
     *  1 confirmed
     *  2 processing
     *  3 something goes wrong
     *  4 pending
     *  5 new
     *  6 canceled
     *
     * @param $orderId
     * @param $order_state_id
     */
    private function setWrongStatus($orderId , $order_state_id)
    {
        $order = $this->getOrder($orderId);
        $order->update_status( $order_state_id, true );
        $order->reduce_order_stock();
        $this->save($order);
    }

    /**
     * Saveing order model and trigger remove item from card if everything goes ok
     *
     * @param $order
     */
    private function save($order)
    {
        if($order->store()){
            $order->empty_cart();
        }
    }

    /**
     * Get order object from  model
     *
     * @param $orderId
     * @return static
     */
    private function getOrder($orderId)
    {
        F0FTable::addIncludePath ( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
        $order = F0FTable::getInstance ( 'Order', 'J2StoreTable' )->getClone();
        $order->load(array('order_id' => $orderId));
        return $order;
    }

    /**
     * Based on status set error or ok message displaying to customer
     *
     * @param $status
     * @return JObject
     */
    private function createConfirmMessage($status)
    {
        $vars = new JObject();

        switch ($status){
            case self::STATUS_OK:
                $vars->message = JText::_('J2STORE_CONFIRMED');
                break;
            default:
                $vars->message = JText::_('J2STORE_FAILED');
        }
        return $vars;
    }

    /**
     * Get actual language for page
     * @return mixed
     */
    private function getLanguage() {
        $lang   = JFactory::getLanguage();
        $lang   = explode( '-', $lang->getTag() );
        return $lang[0];
    }

    /**
     * Get order information
     * @param $data
     * @return mixed
     */
    private function getOrderInformation( $data ) {
        $order = $data['order']->getOrderInformation();
        return $order;
    }

    /**
     * Get price from order
     * @param $order_id
     * @return int
     */
    private function getPrice($order_id) {
        $order = $this->getOrder($order_id);
        if($order){
            return $order->order_total;
        }
        return 0;
    }

    /**
     * Get currency code from order
     *
     * @param $order_id
     * @return string
     */
    private function getCurrencyFromOrder($order_id) {
        $order = $this->getOrder($order_id);
        if($order){
            return $order->currency_code;
        }
        return '';
    }

    /**
     * Validation for response status
     * @param $data
     * @return string
     */
    private function getValidation($data) {
        $total_price = $this->getPrice($data->getString('control'));

        $string = $this->params->get('pid') .
            ($data->getString('id') ? $data->getString('id') : '') .
            ($data->getString('operation_number') ? $data->getString('operation_number') : '') .
            ($data->getString('operation_type') ? $data->getString('operation_type') : '') .
            ($data->getString('operation_status')? $data->getString('operation_status') : '') .
            ($data->getString('operation_amount') ? $data->getString('operation_amount') : '') .
            ($data->getString('operation_currency') ? $data->getString('operation_currency') : '') .
            ($data->getString('operation_withdrawal_amount') ? $data->getString('operation_withdrawal_amount') : '') .
            ($data->getString('operation_commission_amount') ? $data->getString('operation_commission_amount') : '') .
            ($data->getString('operation_original_amount') ? $data->getString('operation_original_amount') : '') .
            ($data->getString('operation_original_currency') ? $data->getString('operation_original_currency') : '') .
            ($data->getString('operation_datetime') ? $data->getString('operation_datetime') : '') .
            ($data->getString('operation_related_number') ? $data->getString('operation_related_number') : '') .
            ($data->getString('control') ? $data->getString('control') : '') .
            ($data->getString('description') ? $data->getString('description') : '') .
            ($data->getString('email') ? $data->getString('email') : '') .
            ($data->getString('p_info') ? $data->getString('p_info') : '') .
            ($data->getString('p_email') ? $data->getString('p_email') : '') .
            ($data->getString('channel') ? $data->getString('channel') : '') .
            ($data->getString('channel_country') ? $data->getString('channel_country') : '') .
            ($data->getString('geoip_country') ? $data->getString('geoip_country') : '');


        if($_SERVER['REMOTE_ADDR'] <> '195.150.9.37'
            && !(bool) $this->params->get('sandbox', 0)) {
                return self::VALIDATION_IP;
        }
        if (hash('sha256', $string) != $data->getString('signature')){
                return self::VALIDATION_TOKEN;
        }
        if ((float) $data->getString('operation_amount') != (float) $total_price){
                return self::VALIDATION_PRICE;
        }
        if ($data->getString('operation_currency') != $this->getCurrencyFromOrder($data->getString('control'))){
                return self::VALIDATION_CURRENCY;
        }
        return $data->getString('operation_status');
    }
}