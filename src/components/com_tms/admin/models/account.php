<?php
/**
 * @package    TMS
 * @author     Ankushkumar Maherwal <ankush.maherwal@gmail.com>
 * @copyright  Copyright (c) 2018-2018 Ankushkumar Maherwal. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;

/**
 * TMS - Account Model
 *
 * @since  1.0.0
 */
class TmsModelAccount extends JModelAdmin
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
	public function getTable($type = 'Account', $prefix = 'TmsTable', $config = array())
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
			'com_tms.account',
			'account',
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
			'com_tms.edit.account.data',
			array()
		);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get the account balance.
	 * 
	 * @param   array  $accountId  Account Id.
	 *
	 * @return  INT  Account balance.
	 *
	 * @since   1.0.0
	 */
	public function getBalance($accountId)
	{
		$accontTable = $this->getTable();
		$accontTable->load($accountId);

		if (empty($accontTable->id))
		{
			return false;
		}

		JLoader::import('components.com_tms.models.transactions', JPATH_ADMINISTRATOR);
		$transactionsModel = BaseDatabaseModel::getInstance('Transactions', 'TmsModel', array('ignore_request' => true));
		$transactionsModel->setState('filter.account_id', $accountId);
		$transactionsModel->setState('filter.published', 1);
		$transactions = $transactionsModel->getItems();
		$balance = 0;

		foreach ($transactions as $transaction)
		{
			foreach ($transaction->details as $detail)
			{
				if ($detail->account_id == $accountId)
				{
					if (!empty($detail->credit))
					{
						$balance += $detail->credit;
					}
					else
					{
						$balance -= $detail->debit;
					}
				}
			}
		}

		return $balance;
	}
}
