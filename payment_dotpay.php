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

class plgJ2StorePayment_dotpay extends J2StorePaymentPlugin {

    var $_element   = 'payment_dotpay';
    var $_isLog     = false;

    protected $_url = array(
        0 => 'https://ssl.dotpay.pl/',
        1 => 'https://ssl.dotpay.pl/test_payment/'
    );
    protected $_default = array(
        'api_version' => 'dev',
        'channel'   => '',
        'ch_lock'   => 0,
        'type'      => 0,
    );

    const STATUS_OK = 'OK';
    const STATUS_FAIL = 'FAIL';
    const OPERATION_STATUS_COMPLETED = 'completed';
    const OPERATION_STATUS_REJECTED = 'rejected';

    public function _prePayment( $data ) {
        $vars   = new JObject();
        $info   = $this->getOrderInformation($data);

        //Needed for Dotpay
        $vars->id           = $this->params->get('accountId');
        $vars->amount       = $data['orderpayment_amount'];
        $vars->currency     = $data['order']->currency_code;
        $vars->description  = $data['order_id'];
        $vars->lang         = $this->getLanguage();
        $vars->api_version  = $this->_default['api_version'];

        //Additional data
        $vars->channel      = (string) $this->_default['channel'];
        $vars->ch_lock      = (string) $this->_default['ch_lock'];
        $vars->url          = JURI::base(). "index.php?option=com_j2store&view=checkout";
        $vars->type         = $this->_default['type'];
        $vars->urlc         = JURI::base() . "index.php?option=com_j2store&task=checkout.confirmPayment&orderpayment_type=payment_dotpay";
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

        $html = $this->_getLayout('prepayment', $vars);

        return $html;
    }

    public function _postPayment( $data ) {
        $app =JFactory::getApplication();

        $status = $this->getValidation($app->input);

        if($status === self::STATUS_OK) {
            if($this->setOrderStatus($app->input->getString('control'))) {
                echo self::STATUS_OK;
            } else {
                echo self::STATUS_FAIL;
            }
        } else {
            echo self::STATUS_FAIL;
        }

        $app->close();
    }

    private function getLanguage() {
        $lang   = JFactory::getLanguage();
        $lang   = explode( '-', $lang->getTag() );
        return $lang[0];
    }

    private function getOrderInformation( $data ) {
        $order = $data['order']->getOrderInformation();
        return $order;
    }

    private function getPrice($order_id) {
        F0FTable::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_j2store/tables' );
        $order = F0FTable::getInstance('Order', 'J2StoreTable');
        var_dump($order_id);
        if($order->load( array('order_id'=>$order_id))) {
            return $order->order_total;
        } else {
            return 0;
        }
    }

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


        if($_SERVER['REMOTE_ADDR'] <> '195.150.9.37') {
            return self::STATUS_FAIL;
        }


        if (hash('sha256', $string) === $data->getString('signature')
            && (float) $data->getString('operation_amount') === (float) $total_price
            && ( $data->getString('operation_status') === self::OPERATION_STATUS_COMPLETED
                || $data->getString('operation_status') === self::OPERATION_STATUS_REJECTED ) ) {
            return self::STATUS_OK;
        } else {
            return self::STATUS_FAIL;
        }
    }

    private function setOrderStatus($order_id) {

        F0FTable::addIncludePath ( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
        $order = F0FTable::getInstance ( 'Order', 'J2StoreTable' )->getClone();

        $order->load(array('order_id' => $order_id));

        $order->payment_complete();

        if ($order->store()) {
            $order->empty_cart();
            return true;
        } else {
            return false;
        }
    }
}