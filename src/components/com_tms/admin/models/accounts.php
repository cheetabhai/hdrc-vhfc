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
 * TMS accounts Model
 *
 * @since  1.0.0
 */
class TmsModelAccounts extends ListModel
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
				'category_id',
				'title',
				'contact_number',
				'published'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to build an SQL query to load the list data.
	 *
	 * @return  string  An SQL query
	 */
	protected function getListQuery()
	{
		// Initialize variables.
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Create the base select statement.
		$query->select('a.*');
		$query->from($db->quoteName('#__transport_account', 'a'));

		// Join over the categories.
		$query->select($db->quoteName('c.title', 'category_title'));
		$query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . '=' . $db->quoteName('a.category_id'));

		// Filter: like / search
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $search . '%');
			$query->where($db->quoteName('a.title') . ' LIKE ' . $search);
		}

		// Filter by published state
		$published = $this->getState('filter.published');

		if (is_numeric($published))
		{
			$query->where($db->quoteName('a.published') . ' = ' . (int) $published);
		}
		elseif ($published === '')
		{
			$query->where($db->quoteName('a.published') . ' IN (0, 1)');
		}

		// Add the list ordering clause.
		$orderCol = $this->state->get('list.ordering', $db->quoteName('a.title'));
		$orderDirn = $this->state->get('list.direction', 'asc');

		$query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

		return $query;
	}
}
