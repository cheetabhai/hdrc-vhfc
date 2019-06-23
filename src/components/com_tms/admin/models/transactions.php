<?php
/**
 * @package    TMS
 * @author     Ankushkumar Maherwal <ankush.maherwal@gmail.com>
 * @copyright  Copyright (c) 2018-2018 Ankushkumar Maherwal. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Model\ListModel;

/**
 * TMS Transactions Model
 *
 * @since  1.0.0
 */
class TmsModelTransactions extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     JController
	 * @since   1.0
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id',
				'account_id',
				'category_id',
				'type',
				'date',
				'published'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to build an SQL query to load the list datt.
	 *
	 * @return  string  An SQL query
	 */
	protected function getListQuery()
	{
		// Initialize variables.
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Create the base select statement.
		$query->select('t.*');
		$query->from($db->quoteName('#__transport_transaction', 't'));

		// Join over the categories.
		$query->select($db->quoteName('ac.title', 'account_title'));
		$query->join('LEFT', $db->quoteName('#__transport_account', 'ac') . ' ON ' . $db->quoteName('ac.id') . '=' . $db->quoteName('t.account_id'));

		$query->where($db->quoteName('ac.published') . '=1');

		// Filter: search
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where($db->quoteName('t.id') . ' = ' . (int) $search);
			}
			else
			{
				$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
				$query->where('(ac.title LIKE ' . $search . ' OR t.description LIKE ' . $search . ')');
			}
		}

		// Filter by published state
		$published = $this->getState('filter.published');

		if (is_numeric($published))
		{
			$query->where($db->quoteName('t.published') . ' = ' . (int) $published);
		}
		elseif ($published === '')
		{
			$query->where($db->quoteName('t.published') . ' IN (0, 1)');
		}

		// Filter by transaction category
		$category = $this->getState('filter.category_id');

		if (!empty($category))
		{
			$query->where($db->quoteName('t.category_id') . ' = ' . (int) $category);
		}

		// Filter by account
		$account = $this->getState('filter.account_id');

		if (!empty($account))
		{
			$query->where($db->quoteName('t.account_id') . ' = ' . (int) $account);
		}

		// Filter by account
		$transactionType = $this->getState('filter.transaction_type');

		if (!empty($transactionType))
		{
			if ($transactionType == 'credit')
			{
				$query->where($db->quoteName('t.debit') . ' != 0');
			}
			else
			{
				$query->where($db->quoteName('t.credit') . ' != 0');
			}
		}

		// Add the list ordering clause.
		$orderCol = $this->state->get('list.ordering', $db->quoteName('t.id'));
		$orderDirn = $this->state->get('list.direction', 'desc');

		$query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

		return $query;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function populateState($ordering = 't.id', $direction = 'DESC')
	{
		$published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
		$this->setState('filter.published', $published);

		$category = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id', '');
		$this->setState('filter.category_id', $category);

		$type = $this->getUserStateFromRequest($this->context . '.filter.transaction_type', 'filter_transaction_type', '');
		$this->setState('filter.transaction_type', $type);

		// List state information.
		parent::populateState($ordering, $direction);
	}
}
