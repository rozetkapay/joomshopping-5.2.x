<?php

namespace Joomla\Plugin\JShopping\RozetkaPay\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jshopping\Site\Helper\SelectOptions;


class CurrencyField extends ListField
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
				$items   = SelectOptions::getCurrencies();
				$find    = false;
				if (!empty($items))
				{
					foreach ($items as $item)
					{
						$option        = new \stdClass();
						$option->value = $item->currency_id;
						$option->text  = $item->currency_name;
						if ($find === false && $item->currency_code_iso == 'UAH')
						{
							if (empty($this->value))
							{
								$this->value = $item->currency_id;
							}
							$find = true;
						}

						$options[] = $option;
					}
				}
				$this->_options = $options;

				if ($find === false){
					Factory::getApplication()->enqueueMessage(Text::_('PLG_JSHOPPING_ROZETKAPAY_ERROR_CURRENCY')
						,'error');
				}
			}
			catch (\Exception $e)
			{

				throw new \Exception(Text::_($e->getMessage()), 404);
			}
		}

		return $this->_options;
	}
}


