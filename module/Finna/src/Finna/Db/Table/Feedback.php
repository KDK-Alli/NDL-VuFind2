<?php
/**
 * Table Definition for feedback form data.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Zend\Db\Adapter\Adapter;

/**
 * Table Definition for feedback form data.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Feedback extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'finna_feedback'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Save feedback to database.
     *
     * @param string $url         Site URL
     * @param string $formId      Form ID
     * @param int    $userId      User ID (when user is logged in)
     * @param string $message     Feedback form email message
     * @param string $messageJson Form data as JSON
     *
     * @return FeedbackRow
     */
    public function saveFeedback(
        $url, $formId, $userId = null, $message = null, $messageJson = null
    ) {
        $feedback = $this->createRow();
        $feedback->user_id = $userId;
        $feedback->ui_url = $url;
        $feedback->form = $formId;
        $feedback->message = $message;
        $feedback->message_json = $messageJson;
        $feedback->save();

        return $feedback;
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param int    $userId User ID (to limit results to a particular
     * user).
     * @param string $form   ID of form being checked.
     * @param string $url    UI URL
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getFeedbacksByUserAndFormAndUrl(
        int $userId,
        string $form,
        string $url
    ) : \Zend\Db\ResultSet\AbstractResultSet {
        $callback = function ($select) use ($userId, $form, $url) {
            $select->columns(['*']);
            $select->where->equalTo('ui_url', $url);
            $select->where->equalTo('form', $form);
            $select->where->equalTo('user_id', $userId);
        };
        return $this->select($callback);
    }
}
