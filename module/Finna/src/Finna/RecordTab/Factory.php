<?php
/**
 * Record Tab Factory Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Finna\RecordTab;

use Zend\ServiceManager\ServiceManager;

/**
 * Record Tab Factory Class
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for Map tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Map
     */
    public static function getMap(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $enabled = isset($config->Content->recordMap);

        // get Map Tab config options
        $mapTabConfig = $sm->get('VuFind\GeoFeatures\MapTabConfig');
        $mapTabOptions = $mapTabConfig->getMapTabOptions();
        $mapTabDisplay = $mapTabOptions['recordMap'];

        // add basemap options
        $basemapConfig = $sm->get('VuFind\GeoFeatures\BasemapConfig');
        $basemapOptions = $basemapConfig->getBasemap('MapTab');

        return new Map($mapTabDisplay, $basemapOptions, $mapTabOptions);
    }

    /**
     * Factory for UserComments tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserComments
     */
    public static function getUserComments(ServiceManager $sm)
    {
        $capabilities = $sm->get('VuFind\AccountCapabilities');
        $config = $sm->get('VuFind\Config')->get('config');
        $recaptcha = \Finna\Controller\Plugin\Factory::getRecaptcha($sm);
        $useRecaptcha = $recaptcha->active('userComments');
        return new UserComments(
            'enabled' === $capabilities->getCommentSetting(),
            $useRecaptcha
        );
    }

    /**
     * Factory for PressReview tab plugin.
     *
     * @return PressReviews
     */
    public static function getPressReviews()
    {
        return new PressReviews(true);
    }

    /**
     * Factory for Music tab plugin.
     *
     * @return Music
     */
    public static function getMusic()
    {
        return new Music(true);
    }

    /**
     * Factory for Distribution tab plugin.
     *
     * @return Distribution
     */
    public static function getDistribution()
    {
        return new Distribution(true);
    }

    /**
     * Factory for Inspection Details tab plugin.
     *
     * @return InspectionDetails
     */
    public static function getInspectionDetails()
    {
        return new InspectionDetails(true);
    }

    /**
     * Factory for Description tab plugin.
     *
     * @return DescriptionFWD
     */
    public static function getDescriptionFWD()
    {
        return new DescriptionFWD(true);
    }

    /**
     * Factory for Item Description tab plugin.
     *
     * @return Description
     */
    public static function getItemDescription()
    {
        return new ItemDescription(true);
    }
}
