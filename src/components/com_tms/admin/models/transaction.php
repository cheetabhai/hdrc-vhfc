<?php
/**
 * @package    TMS
 * @author     Ankushkumar Maherwal <ankush.maherwal@gmail.com>
 * @copyright  Copyright (c) 2018-2018 Ankushkumar Maherwal. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

/**
 * TMS - Transaction Model
 *
 * @since  1.0.0
 */
class TmsModelTransaction extends JModelAdmin
{
	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table  A Table object
	 *
	 * @since   1.0.0
	 */
	public function getTable($type = 'Transaction', $prefix = 'TmsTable', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed    A JForm object on success, false on failure
	 *
	 * @since   1.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm(
			'com_tms.transaction',
			'transaction',
			array(
				'control' => 'jform',
				'load_data' => $loadData
			)
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState(
			'com_tms.edit.transaction.data',
			array()
		);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get transaction data.
	 *
	 * @param   INT  $pk  Transaction id
	 *
	 * @return  OBJECT  Transaction data.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem();

		if (!empty($item->id))
		{
			// Get reference transactions
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('*');
			$query->from($db->quoteName('#__transport_transaction_reference'));
			$query->where($db->quoteName('reference_id') . ' = ' . $item->id);
			$db->setQuery($query);
			$transactionReferences = $db->loadObjectList();

			$item->debit_accounts = array();
			$item->credit_accounts = array();

			foreach ($transactionReferences as $transactionReference)
			{
				if ($transactionReference->debit != 0)
				{
					$item->debit_accounts[] = array("debit_account_id" => $transactionReference->account_id, "debit_amount" => $transactionReference->debit);
				}
				else
				{
					$item->credit_accounts[] = array("credit_account_id" => $transactionReference->account_id, "credit_amount" => $transactionReference->credit);
				}
			}

			$item->debit_accounts = json_encode($item->debit_accounts);
			$item->credit_accounts = json_encode($item->credit_accounts);
		}

		return $item;
	}

	/**
	 * Method to add transaction.
	 *
	 * @param   ARRAY  $data  Transaction data
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		$db = Factory::getDbo();
		$accountsInvolvedInTransaction = array();

		$debitAmount = 0;
		$creditAmount = 0;

		foreach ($data['debit_accounts'] as $debitAccount)
		{
			// In one transaction one account can be used once eighter for credit or debit
			if (in_array($debitAccount['debit_account_id'] ,$debitAccounts))
			{
				$this->setError(Text::_('COM_TMS_TRANSACTION_ERROR_ACCOUNT_USED_MULTIPLE_TIMES'));

				return false;
			}

			// Check if transaction is done against valid account
			$table = Table::getInstance('Account', 'TmsTable', array('dbo', $db));
			$table->load(array('id' => $debitAccount['debit_account_id']));

			if (empty($table->id))
			{
				$this->setError(Text::_('COM_TMS_WARNING_TRANSACTION_AGAINST_INVALID_ACCOUNT'));

				return false;
			}

			$accountsInvolvedInTransaction[] = $debitAccount['debit_account_id'];
			$debitAmount += abs($debitAccount['debit_amount']);
		}

		foreach ($data['credit_accounts'] as $creditAccount)
		{
			// In one transaction one account can be used once eighter for credit or debit
			if (in_array($creditAccount['credit_account_id'] ,$creditAccounts))
			{
				$this->setError(Text::_('COM_TMS_TRANSACTION_ERROR_ACCOUNT_USED_MULTIPLE_TIMES'));

				return false;
			}

			// Check if transaction is done against valid account
			$table = Table::getInstance('Account', 'TmsTable', array('dbo', $db));
			$table->load(array('id' => $creditAccount['credit_account_id']));

			if (empty($table->id))
			{
				$this->setError(Text::_('COM_TMS_WARNING_TRANSACTION_AGAINST_INVALID_ACCOUNT'));

				return false;
			}

			$accountsInvolvedInTransaction[] = $creditAccount['credit_account_id'];
			$creditAmount += abs($creditAccount['credit_amount']);
		}

		// If debit amount is not equal to credit amount the do not process the transaction
		if ($creditAmount != $debitAmount)
		{
			$this->setError(Text::_('COM_TMS_TRANSACTION_ERROR_DEBIT_CREDIT_AMOUNT_MISSMATCH'));

			return false;
		}

		if (parent::save($data))
		{
			$transactionId = empty($data['id']) ? (int) $this->getState($this->getName() . '.id') : $data['id'];

			if (!empty($transactionId))
			{
				// In case of update delete all the reference transactions
				$db = JFactory::getDbo();
				$query = $db->getQuery(true);
				$query->delete($db->quoteName('#__transport_transaction_reference'));
				$query->where($db->quoteName('reference_id') . ' = ' . $transactionId);
				$db->setQuery($query);
				$db->execute();
			}

			// Add entry in reference table for the transaction
			foreach ($data['debit_accounts'] as $debitAccount)
			{
				$referenceTransaction = new stdClass();
				$referenceTransaction->account_id = $debitAccount['debit_account_id'];
				$referenceTransaction->reference_id = $transactionId;
				$referenceTransaction->debit = $debitAccount['debit_amount'];
				$referenceTransaction->credit = '';
				$db->insertObject('#__transport_transaction_reference', $referenceTransaction);
			}

			// Add entry in reference table for the transaction
			foreach ($data['credit_accounts'] as $creditAccount)
			{
				$referenceTransaction = new stdClass();
				$referenceTransaction->account_id = $creditAccount['credit_account_id'];
				$referenceTransaction->reference_id = $transactionId;
				$referenceTransaction->debit = '';
				$referenceTransaction->credit = $creditAccount['credit_amount'];
				$db->insertObject('#__transport_transaction_reference', $referenceTransaction);
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method to delete one or more records.
	 *
	 * @param   array  &$pks  An array of record primary keys.
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 *
	 * @since   1.6
	 */
	public function delete(&$pks)
	{
		$pks   = (array) $pks;
		$table = $this->getTable();
		$db    = Factory::getDbo();
		$app   = Factory::getApplication();
		$forceDelete = $this->getState('forceDelete', 0, 'INT');

		foreach ($pks as $pk)
		{
			$table->load($pk);

			if ($forceDelete == 0)
			{
				// Check if transaction is associated with paid entry, if soo then dont allow to delete the entry
				Table::addIncludePath(JPATH_ROOT . '/administrator/components/com_tms/tables');
				$paidTable = Table::getInstance('BilltPaid', 'TmsTable', array('dbo', $db));
				$paidTable->load(array('transaction_id' => $table->id));

				if (!empty($paidTable->id))
				{
					$app->enqueueMessage(Text::sprintf("COM_TMS_TRANSACTION_DELETE_ERROR_PAID_TRANSACTION", $paidTable->chalan_id) , 'error');

					return false;
				}
			}

			if (!parent::delete($pk))
			{
				return false;
			}
			else
			{
				// Delete all the reference transactions
				$query = $db->getQuery(true);
				$query->delete($db->quoteName('#__transport_transaction_reference'));
				$query->where($db->quoteName('reference_id') . ' = ' . $transactionId);
				$db->setQuery($query);
				$db->execute();
			}
		}

		return true;
	}
}
