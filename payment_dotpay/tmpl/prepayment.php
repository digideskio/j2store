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

//no direct access
defined('_JEXEC') or die('Restricted access');

$config = JFactory::getConfig();
?>
<style type="text/css">
    #dotpay_form { width: 100%; }
    #dotpay_form td { padding: 5px; }
    #dotpay_form .field_name { font-weight: bold; }
</style>
<form id="j2store_dotpay_form"
      action="<?php echo $vars->payment_url; ?>"
      method="post"
      name="adminForm"
    >
    <input type="hidden" name="id" value="<?php echo $vars->id; ?>" />
    <input type="hidden" name="amount" value="<?php echo $vars->amount; ?>" />
    <input type="hidden" name="currency" value="<?php echo $vars->currency; ?>" />
    <input type="hidden" name="description" value="<?php echo $vars->description; ?>" />
    <input type="hidden" name="lang" value="<?php echo $vars->lang; ?>" />
    <input type="hidden" name="api_version" value="<?php echo $vars->api_version; ?>" />
    <input type="hidden" name="channel" value="<?php echo $vars->channel; ?>" />
    <input type="hidden" name="ch_lock" value="<?php echo $vars->ch_lock; ?>" />
    <input type="hidden" name="url" value="<?php echo $vars->url; ?>" />
    <input type="hidden" name="type" value="<?php echo $vars->type; ?>" />
    <input type="hidden" name="urlc" value="<?php echo $vars->urlc; ?>" />
    <input type="hidden" name="control" value="<?php echo $vars->control; ?>" />
    <input type="hidden" name="firstname" value="<?php echo $vars->firstname; ?>" />
    <input type="hidden" name="lastname" value="<?php echo $vars->lastname; ?>" />
    <input type="hidden" name="email" value="<?php echo $vars->email; ?>" />
    <input type="hidden" name="street" value="<?php echo $vars->street; ?>" />
    <input type="hidden" name="city" value="<?php echo $vars->city; ?>" />
    <input type="hidden" name="postcode" value="<?php echo $vars->postcode; ?>" />
    <input type="hidden" name="phone" value="<?php echo $vars->phone; ?>" />
    <input type="hidden" name="country" value="<?php echo $vars->country; ?>" />
    <input type="hidden" name="p_info" value="<?php echo $config->get( 'sitename' ); ?>" />

    <button type="submit" class="button btn btn-success"><?php echo JText::_('J2STORE_CHECKOUT_CONTINUE'); ?></button>
</form>