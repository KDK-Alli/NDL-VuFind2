<?php
/**
 * Table Definition for user_resource
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for user_resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserResource extends \VuFind\Db\Table\UserResource
{
    /**
     * Create link if one does not exist; update notes if one does.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $user_id     ID of user creating link
     * @param string $list_id     ID of list to link up
     * @param string $notes       Notes to associate with link
     *
     * @return void
     */
    public function createOrUpdateLink($resource_id, $user_id, $list_id,
        $notes = ''
    ) {
        parent::createOrUpdateLink($resource_id, $user_id, $list_id, $notes);
        $this->updateListDate($list_id, $user_id);
    }

    /**
     * Unlink rows for the specified resource.  This will also automatically remove
     * any tags associated with the relationship.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink
     * (null for ALL matching lists, with the destruction of all tags associated
     * with the $resource_id value; true for ALL matching lists, but retaining
     * any tags associated with the $resource_id independently of lists)
     *
     * @return void
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null)
    {
        parent::destroyLinks($resource_id, $user_id, $list_id);
        if (null !== $list_id && true !== $list_id) {
            $this->updateListDate($list_id, $user_id);
        }
    }


    /**
     * Add custom favorite list order
     *
     * @param int    $user_id       User_id
     * @param int    $list_id       List_id
     * @param string $resource_list Ordered List of Resources
     *
     * @return boolean
     */
    public function saveCustomFavoriteOrder($user_id,$list_id,$resource_list) {
        $index_counter = 0;
        $resource_index = [];

        foreach (explode(',', $resource_list) as $resource) {
            $index_counter++;
            $resource_index["$resource"] = $index_counter;
        }

        $callback = function($select) use ($list_id, $user_id) {
            $select->join(
                ['r' => 'resource'],
                'r.id = user_resource.resource_id',
                ['record_id']
            );
            $select->where->equalTo('list_id', $list_id);
            $select->where->equalTo('user_id', $user_id);
        };

        try {
            foreach ($this->select($callback) as $row) {
                if ($row_to_update = $this->select(
                    ['user_id' => $user_id, 'list_id' => $list_id,
                     'resource_id' => $row->resource_id]
                )->current()) {
                    $row_to_update->finna_custom_order_index = $resource_index[$row->record_id];
                    $row_to_update->save();
                } else {
                    return false;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }


    /**
     * Get custom favorite list order
     *
     * @param int $list_id List_id
     * @param int $user_id User_id
     *
     * @return boolean|string
     */
    public function getCustomFavoriteOrder($list_id, $user_id = null)
    {
        $callback = function($select) use ($list_id, $user_id) {
            if ($user_id) {
                $select->where->equalTo('user_id', $user_id);
            }
            $select->where->equalTo('list_id', $list_id);
            $select->join(
                ['r' => 'resource'],
                'user_resource.resource_id = r.id',
                ['record_id']
            );
            $select->order('finna_custom_order_index');
        };

        $list = [];
        foreach ($this->select($callback) as $result) {
            if ($result->finna_custom_order_index) {
                $list[] = $result->record_id;
            }
        }
        if (empty($list)) {
            return false;
        } else {
            return $list;
        }
    }

    /**
     * Update the date of a list
     *
     * @param string $list_id ID of list to unlink
     * @param string $user_id ID of user removing links
     *
     * @return void
     */
    protected function updateListDate($list_id, $user_id)
    {
        $userTable = $this->getDbTable('User');
        $user = $userTable->select(['id' => $user_id])->current();
        if (empty($user)) {
            return;
        }
        $listTable = $this->getDbTable('UserList');
        $list = $listTable->getExisting($list_id);
        $list->save($user);
    }
}
