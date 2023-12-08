<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Plugin\JShopping\RozetkaPay\Helper\ApiHelper;

class pm_rozetkapay extends PaymentRoot
{
	public function showAdminFormParams($pmconfigs)
	{
		Factory::getApplication()->getLanguage()->load('com_jshopping.pm_rozetkapay', JPATH_ROOT);
		/* @var Form $form */
		$form = Factory::getContainer()->get(FormFactoryInterface::class)->createForm('src\tmp\pm_rozetkapay\pm_rozetkapay',
			['control' => 'pm_params']);

		$form->loadFile(__DIR__ . '/config.xml');


		if (!empty($pmconfigs))
		{
			foreach ($pmconfigs as $name => $value)
			{
				$form->setValue($name, '', $value);
			}
		}


		include_once(__DIR__ . '/adminparamsform.php');
	}

	public function showEndForm($pmconfigs, $order)
	{

		$pay = $this->getPaymentUrl($pmconfigs, $order);
		if ($pay !== false)
		{
			echo '<script>location.href = "' . $pay . '"</script>';
		}

	}

	public function checkTransaction($pmconfigs, $order, $act)
	{
		if ($act != 'notify')
		{
			return;
		}

		$data = json_decode(file_get_contents('php://input'));
		if (empty($data) || $data->external_id != $order->order_id)
		{
			return array(0, 'Error data');
		}
		$status = $data->details->status;
		if (!empty($status))
		{
			if ((int) $order->order_status !== (int) $pmconfigs['transaction_end_status']
				&& (int) $order->order_status !== (int) $pmconfigs['transaction_failed_status'])
			{
				if (!empty($order->payment_params_data) && !empty($data->id)
					&& $data->id === $order->payment_params_data)
				{
					if (($status == 'success') && (int) $order->order_status != (int) $pmconfigs['transaction_end_status'])
					{
						return array(1, '');
					}
					elseif ($status == 'failure'
						&& (int) $order->order_status != (int) $pmconfigs['transaction_failed_status'])
					{
						return array(3, 'Status Failed. Order ID ' . $order->order_id);
					}
				}
			}
		}

		return array(0, 'Error data');
	}

	public function getUrlParams($pmconfigs)
	{
		$params = array();

		$params['order_id']          = Factory::getApplication()->input->getInt('order_id');
		$params['checkReturnParams'] = 0;
		$params['hash']              = '';
		$params['checkHash']         = 0;

		return $params;
	}

	protected function getPaymentUrl($params, $order)
	{
		$app    = Factory::getApplication();
		$config = \JSFactory::getConfig();
		$rate   = false;
		if ($order->currency_code_iso != 'UAH') $rate = true;

		if ($rate === true && empty($params['currency']))
		{
			Factory::getApplication()->enqueueMessage(Text::_('PLG_JSHOPPING_ROZETKAPAY_ERROR_CURRENCY_MESSAGE'), 'error');

			return false;
		}


		$uri          = Uri::getInstance();
		$liveurlhost  = $uri->toString(array('scheme', 'host', 'port'));
		$callback_url = $liveurlhost . \JSHelper::SEFLink('index.php?option=com_jshopping&controller=checkout&task=step7&act=notify&js_paymentclass=pm_rozetkapay&no_lang=1&order_id=' . $order->order_id);
		$result_url   = $liveurlhost . \JSHelper::SEFLink('index.php?option=com_jshopping&controller=checkout&task=step7&act=return&js_paymentclass=pm_rozetkapay&order_id=' . $order->order_id);
		$error        = false;

		$total = ($rate) ? ApiHelper::convertPrice($order->order_total, $order->currency_exchange, $params)
			: (float) $order->order_total;
		$data  = [
			'amount'       => $total,
			'external_id'  => (string) $order->order_id,
			'currency'     => 'UAH',
			'mode'         => 'hosted',
			'description'  => Text::sprintf('JSHOP_PAYMENT_NUMBER', $order->order_number),
			'callback_url' => $callback_url,
			'result_url'   => $result_url,
		];

		if (isset($params['customer']) && (int) $params['customer'] === 1)
		{
			$first_name = (empty ($order->f_name)) ? $order->f_name : $order->d_f_name;
			$last_name  = (empty ($order->l_name)) ? $order->l_name : $order->d_l_name;
			$phone      = (empty ($order->phone)) ? $order->phone : $order->d_phone;;
			if (!empty($order->email) || !empty($first_name) || !empty($last_name) || !empty($phone))
			{
				$customer             = new \stdClass();
				$customer->email      = $order->email;
				$customer->first_name = $first_name;
				$customer->last_name  = $last_name;
				$customer->phone      = $phone;

				$data['customer'] = $customer;
			}
		}
		if (isset($params['products']) && (int) $params['products'] === 1)
		{
			$products   = array();
			$image_path = $config->image_product_live_path;
			foreach ($order->getAllItems() as $item)
			{
				;
				$product       = new \stdClass();
				$product->id   = (string) $item->product_id;
				$product->name = $item->product_name;
				if (!empty($item->thumb_image))
				{
					$product->image = $image_path . '/' . $item->thumb_image;
				}
				$sum                 = (float) $item->product_item_price * (int) $item->product_quantity;
				$product->net_amount = $sum;
				if ($rate)
				{
					$product->net_amount = ApiHelper::convertPrice($sum, $order->currency_exchange, $params);
				}
				$product->quantity = (string) $item->product_quantity;
				$product->url      = $liveurlhost . \JSHelper::SEFLink('index.php?option=com_jshopping&controller=product&task=view&category_id=' . $item->category_id . '&product_id=' . $item->product_id, 1);
				$products[]        = $product;
			}

			if (!empty($products))
			{
				$data['products'] = $products;
			}
		}
		$data['amount'] = (int) $data['amount'];
		$result         = ApiHelper::createPayment($data, $params);
		if ($result === false)
		{
			$app->enqueueMessage(Text::_('PLG_JSHOPPING_ROZETKAPAY_ERROR_REQUEST_MESSAGE'), 'error');

			return false;
		}

		if (!empty($result['code']) && !empty($result['message']))
		{
			$app->enqueueMessage($result['message'], 'error');

			return false;
		}
		else
		{
			if (!empty($result['action']))
			{
				$this->setOrderPaymentID($order->order_id, $result['id']);

				return $result['action']['value'];
			}
		}

		return false;

	}
	
	protected function setOrderPaymentID($order_id, $payment_id)
	{
		if (empty($order_id) || empty($payment_id)) return;
		$db    = Factory::getContainer()->get(DatabaseDriver::class);
		$query = $db->getQuery(true)
			->update($db->quoteName('#__jshopping_orders'))
			->set($db->quoteName('payment_params_data') . ' = ' . $db->quote($payment_id))
			->where($db->quoteName('order_id') . ' = ' . (int) $order_id);
		$db->setQuery($query)->execute();

	}
}