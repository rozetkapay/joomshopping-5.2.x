<?php

namespace Joomla\Plugin\JShopping\RozetkaPay\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseDriver;
use Joomla\Plugin\JShopping\RozetkaPay\Helper\ApiHelper;

class RozetkaPay extends CMSPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  CMSApplication
	 *
	 * @since  1.0.0
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  DatabaseDriver
	 *
	 * @since  1.0.0
	 */
	protected $db = null;

	public function onAfterChangeOrderStatusAdmin(&$order_id, &$status, &$sendmessage, &$comments)
	{
		$order = \JSFactory::getTable('order');
		$order->load($order_id);
		$payment_method_id = (int) $order->payment_method_id;
		if ($params = ApiHelper::getParamsByPaymentId($payment_method_id))
		{
			if ((int) $params['transaction_refunded_status'] === (int) $status)
			{
				if (!empty($id = $order->payment_params_data))
				{
					$info = ApiHelper::infoPayment($order_id, $params);
					if (!empty($info) && !empty($info['id']) && $id == $info['id'])
					{
						if (empty($info['refunded']))
						{
							$refund = ApiHelper::refundPayment(
								[
									'external_id' => (string) $info['external_id'],
									'amount'      => (float) $info['amount'],
								], $params);

							if ($refund === false)
							{
								$this->app->enqueueMessage(Text::_('PLG_JSHOPPING_ROZETKAPAY_ERROR_REQUEST_MESSAGE'),
									'error');

								return;
							}

							if (!empty($refund['code']) && !empty($refund['message']))
							{
								$this->app->enqueueMessage($refund['message'], 'error');
							}
							else
							{
								$this->app->enqueueMessage(Text::_('PLG_JSHOPPING_ROZETKAPAY_REFUND_SUCCESS'),
									'info');
							}

						}
					}
				}
			}
		}
	}
}