<?php
/**
 * Authority Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019-20.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Authority Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorityController extends \Finna\Controller\SearchController
{
    use FinnaSearchControllerTrait;

    protected $searchClassId = 'SolrAuth';

    /**
     * Record action -- display a record
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function recordAction()
    {
        return $this->redirect()->toRoute(
            'authorityrecord',
            ['id' => $this->params()->fromQuery('id')]
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }

    /**
     * Handle onDispatch event
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $authorityHelper = $this->getViewRenderer()->plugin('authority');
        if (!$authorityHelper->isAvailable()) {
            throw new \Exception('Authority search is disabled');
        }

        return parent::onDispatch($e);
    }
}