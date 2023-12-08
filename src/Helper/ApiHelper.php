<?php

namespace Joomla\Plugin\JShopping\RozetkaPay\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\Http;
use Joomla\Component\Jshopping\Site\Table\PaymentMethodTable;
use Joomla\Registry\Registry;

class ApiHelper
{
	/**
	 * Url api
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected static string $urlApi = 'https://api.rozetkapay.com/api/';
	/**
	 * Params method
	 *
	 * @var array|null
	 *
	 * @since 1.0.0
	 *
	 */
	protected static ?array $_config = null;

	/**
	 * JoomShopping currency rate
	 *
	 * @var float|null
	 *
	 * @since 1.0.0
	 */
	protected static ?float $_currency_rate = null;

	/**
	 * Method create payment
	 *
	 * @param $data   array Request data.
	 * @param $params array Method payment params.
	 *
	 * @return array|false
	 *
	 * @since 1.0.0
	 */
	public static function createPayment($data = [], $params = [])
	{
		if (empty($data) || empty($params))
		{
			return false;
		}

		return self::request($data, 'payments/v1/new', $params);
	}

	/**
	 * Method info payment
	 *
	 * @param   string  $external_id  Transaction external_id.
	 * @param           $params       array Method payment params.
	 *
	 * @return array|false
	 *
	 * @since 1.0.0
	 */
	public static function infoPayment($external_id = null, $params = [])
	{
		if (empty($external_id) || empty($params))
		{
			return false;
		}

		return self::request(['external_id' => $external_id], 'payments/v1/info', $params, 'get');
	}

	/**
	 * Method refund payment
	 *
	 * @param $data   array Request data.
	 * @param $params array Method payment params.
	 *
	 * @return array|false
	 *
	 * @since 1.0.0
	 */
	public static function refundPayment($data = null, $params = [])
	{
		if (empty($data) || empty($data['external_id']) || empty($params))
		{
			return false;
		}

		return self::request($data, 'payments/v1/refund', $params);
	}

	/**
	 * Method get params payment method by id
	 *
	 * @param $payment_method_id int|null Payment id.
	 *
	 * @return array|bool
	 *
	 * @since  1.0.0
	 */
	public static function getParamsByPaymentId($payment_method_id = null)
	{
		if ((int) $payment_method_id <= 0)
		{
			return false;
		}

		if (self::$_config === null)
		{
			self::$_config = [];
		}

		if (!isset(self::$_config[$payment_method_id]))
		{
			/** @var PaymentMethodTable $paym_method */
			$paym_method = \JSFactory::getTable('paymentmethod');
			if ($paym_method->load(['payment_id' => $payment_method_id, 'scriptname' => 'pm_rozetkapay']))
			{
				self::$_config[$payment_method_id] = $paym_method->getConfigs();

				return self::$_config[$payment_method_id];
			}
		}

		return false;
	}

	public static function convertPrice($price, $currency_exchange, $params)
	{
		if (self::$_currency_rate === null)
		{
			$currencyTable = \JSFactory::getTable('currency');
			$currencies    = $currencyTable->getAllCurrencies('1');
			foreach ($currencies as $currency)
			{
				if ((int) $params['currency'] === (int) $currency->currency_id)
				{
					self::$_currency_rate = (float) $currency->currency_value;

					break;
				}

			}
		}
		if (self::$_currency_rate === null)
		{
			return $price;
		}

		return ((float) $price / $currency_exchange) * self::$_currency_rate;

	}

	/**
	 * Method send request.
	 *
	 * @param $data    array Request data
	 * @param $apiPath string Api method path. Exemple: "payments/v1/new"
	 * @param $params  array Method payment params.
	 * @param $method  string Type request. Available parameters "post", "get", "delete"
	 *
	 * @return array|false
	 *
	 * @since 1.0.0
	 *
	 */
	protected static function request($data = null, $apiPath = null, $params = null, $method = 'post')
	{
		if (empty($data) || empty($apiPath) || empty($params))
		{
			return false;
		}

		$method   = strtolower($method);
		$login    = 'a6a29002-dc68-4918-bc5d-51a6094b14a8';
		$password = 'XChz3J8qrr';
		if (isset($params['sandbox']) && (int) $params['sandbox'] === 0)
		{
			$params['login']    = trim($params['login']);
			$params['password'] = trim($params['password']);
			if (empty($params['login']) || empty($params['password']))
			{
				return false;
			}
			$login    = $params['login'];
			$password = $params['password'];
		}
		$headers = [
			'Authorization' => 'Basic ' . base64_encode($login . ":" . $password),
		];
		$url     = self::$urlApi . $apiPath;
		// Send request
		$http = new Http();
		$http->setOption('transport.curl', array(
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0
		));
		$response = false;
		if ($method === 'post')
		{
			$headers['Content-Type'] = 'application/json';
			$response                = $http->post($url, json_encode($data), $headers);
		}
		elseif ($method === 'get')
		{
			$url      .= '?' . http_build_query($data);
			$response = $http->get($url, $headers);

		}
		elseif ($method === 'delete')
		{
			$url      .= '?' . http_build_query($data);
			$response = $http->delete($url, $headers);

		}

		if ($response)
		{
			return (new Registry($response->body))->toArray();
		}

		return false;


	}
}
