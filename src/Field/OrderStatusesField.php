<?php

namespace Joomla\Plugin\JShopping\RozetkaPay\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;

class OrderStatusesField extends ListField
{
	/**
	 * Cached array of the category items.
	 *
	 * @var    array
	 *
	 * @since  1.0.0
	 */
	protected $_options = null;

	/**
	 * Method to get the options to populate list
	 *
	 * @return  array  The field option objects.
	 *
	 * @since  1.0.0
	 */
	protected function getOptions()
	{
		if ($this->_options === null)
		{
			// Prepare options
			$options = parent::getOptions();
			try
			{
				$orders = \JSFactory::getModel('orders');
				$items  = $orders->getAllOrderStatus();
				if (!empty($items))
				{
					foreach ($items as $item)
					{
						$option        = new \stdClass();
						$option->value = $item->status_id;
						$option->text  = $item->name;

						$options[] = $option;
					}
				}

				$this->_options = $options;
			}
			catch (\Exception $e)
			{

				throw new \Exception(Text::_($e->getMessage()), 404);
			}
		}

		return $this->_options;
	}
}


