<?php
/**
 * Default Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchController extends \VuFind\Controller\SearchController
{
    use SearchControllerTrait;

    /**
     * Results action.
     *
     * @return mixed
     */
    public function resultsAction()
    {
        $view = parent::resultsAction();
        $this->initSavedTabs();
        return $view;
    }

    /**
     * Sends search history and alert schedules for saved searches to view.
     *
     * @return mixed
     */
    public function historyAction()
    {
        $view = parent::historyAction();
        $user = $this->getUser();

        // Retrieve search history
        $search = $this->getTable('Search');
        $searchHistory = $search->getSearches(
            $this->getServiceLocator()->get('VuFind\SessionManager')->getId(),
            is_object($user) ? $user->id : null
        );

        $schedule = [];

        // Loop through the history
        foreach ($searchHistory as $current) {
            // Saved searches
            if ($current->saved == 1) {
                $minSO = $current->getSearchObject();
                // Only Solr searches allowed
                if ($minSO->cl !== 'Solr') {
                    continue;
                }
                $minSO = $minSO->deminify($this->getResultsManager());
                $schedule[$minSO->getSearchId()] = $current->finna_schedule;
            }
        }

        $view->schedule = $schedule;
        return $view;
    }
}

