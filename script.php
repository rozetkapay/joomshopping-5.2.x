<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class, new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
			/**
			 * The application object
			 *
			 * @var  AdministratorApplication
			 *
			 * @since  1.0.0
			 */
			protected AdministratorApplication $app;

			/**
			 * The Database object.
			 *
			 * @var   DatabaseDriver
			 *
			 * @since  1.0.0
			 */
			protected DatabaseDriver $db;


			/**
			 * Table  addon.
			 *
			 * @var  \Joomla\Component\Jshopping\Site\Table\AddonTable
			 *
			 * @since  1.0.0
			 */
			protected ?\Joomla\Component\Jshopping\Site\Table\AddonTable $addonTable = null;

			/**
			 * Path plugin
			 *
			 * @var string
			 *
			 * @since 1.0.0
			 */
			protected string $pathTmp = JPATH_PLUGINS . '/jshopping/rozetkapay/src';
			/**
			 * Extension files.
			 *
			 * @var  array
			 *
			 * @since  1.0.0
			 */
			protected array $externalFiles = [
				[
					'src'  => 'tmp/pm_rozetkapay',
					'dest' => JPATH_ROOT . '/components/com_jshopping/payments/pm_rozetkapay',
					'type' => 'folder',
				],
			];


			/**
			 * Constructor.
			 *
			 * @param   AdministratorApplication  $app  The applications object.
			 *
			 * @since 1.0.0
			 */
			public function __construct(AdministratorApplication $app)
			{
				$this->app = $app;
				$this->db  = Factory::getContainer()->get('DatabaseDriver');
			}

			/**
			 * Function called after the extension is installed.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function install(InstallerAdapter $adapter): bool
			{
				$this->enablePlugin($adapter);

				return true;
			}

			/**
			 * Function called after the extension is updated.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function update(InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Function called after the extension is uninstalled.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function uninstall(InstallerAdapter $adapter): bool
			{
				// Remove external files
				$this->removeExternalFiles();

				return true;
			}

			/**
			 * Method to delete external files.
			 *
			 * @return  boolean  True on success.
			 *
			 * @since  1.0.0
			 */
			protected function removeExternalFiles(): bool
			{
				// Process each file in the $files array (children of $tagName).
				foreach ($this->externalFiles as $path)
				{
					// Actually delete the files/folders
					if (is_dir($path['dest'])) $val = Folder::delete($path['dest']);
					else $val = File::delete($path['dest']);

					if ($val === false)
					{
						Log::add('Failed to delete ' . $path, Log::WARNING, 'jerror');

						return false;
					}
				}

				return true;
			}

			/**
			 * Function called before extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				if ($this->addonTable === null)
				{
					$this->addonTable = $this->app->bootComponent('com_jshopping')
						->getMVCFactory()->createTable('Addon', 'Site');
				}

				return true;
			}

			/**
			 * Function called after extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function postflight(string $type, InstallerAdapter $adapter): bool
			{
				if ($type !== 'uninstall')
				{
					// Copy external files
					if ($this->copyExternalFiles($adapter->getParent()))
					{
						$tmp = $this->pathTmp . '/tmp';
						if (is_dir($tmp)) Folder::delete($tmp);
					}

					if ($this->addonTable)
					{
						$data      = [
							'name_en-GB'      => 'RozetkaPay',
							'payment_code'    => 'rozetkapay',
							'payment_class'   => 'pm_rozetkapay',
							'scriptname'      => 'pm_rozetkapay',
							'payment_publish' => 0,
							'payment_type'    => 2,
						];
						$languages = $this->app->bootComponent('com_jshopping')
							->getMVCFactory()->createModel('Languages', 'Administrator');
						if ($languages)
						{
							foreach ($languages->getAllLanguages(false) as $language)
							{
								$data['name_' . $language->language] = 'RozetkaPay';
							}
						}

						$this->addonTable->installPayment($data);
					}
				}
				else
				{
					if ($this->addonTable)
					{
						$query = $this->db->getQuery(true)
							->select(['id'])
							->from($this->db->quoteName('#__jshopping_payment_method'))
							->where($this->db->quoteName('scriptname') . ' = ' . $this->db->quote('pm_rozetkapay'));
						$ids   = $this->db->setQuery($query)->loadColumn();
						if (!empty($ids))
						{
							$query = $this->db->getQuery(true)
								->delete($this->db->quoteName('#__jshopping_payment_method'))
								->whereIn($this->db->quoteName('id'), $ids);
							$this->db->setQuery($query)->execute();
						}
					}
				}

				return true;
			}

			/**
			 * Method to copy external files.
			 *
			 * @param   Installer  $installer  Installer calling object.
			 *
			 * @return  bool True on success, False on failure.
			 *
			 * @since  1.0.0
			 */
			public function copyExternalFiles(Installer $installer): bool
			{
				$copyFiles = array();
				foreach ($this->externalFiles as $path)
				{
					$path['src']  = Path::clean($this->pathTmp . '/' . $path['src']);
					$path['dest'] = Path::clean($path['dest']);
					if (basename($path['dest']) !== $path['dest'])
					{
						$newDir = dirname($path['dest']);
						if (!Folder::create($newDir))
						{
							Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_CREATE_DIRECTORY', $newDir), Log::WARNING, 'jerror');

							return false;
						}
					}

					$copyFiles[] = $path;
				}

				return $installer->copyFiles($copyFiles, true);
			}

			/**
			 * Enable plugin after installation.
			 *
			 * @param   InstallerAdapter  $adapter  Parent object calling object.
			 *
			 * @since  1.0.0
			 */
			protected function enablePlugin(InstallerAdapter $adapter)
			{
				// Prepare plugin object
				$plugin          = new \stdClass();
				$plugin->type    = 'plugin';
				$plugin->element = $adapter->getElement();
				$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
				$plugin->enabled = 1;

				// Update record
				$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
			}

		});
	}
};
