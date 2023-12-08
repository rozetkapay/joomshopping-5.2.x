<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;

/**
 * Layout variables
 * -----------------
 *
 * @var     Form $form Form admin.
 *
 */

?>

<div class="row mt-3">
	<div class="adminform col-12 col-md-6">
		<?php echo $form->renderFieldset('config'); ?>
	</div>
</div>