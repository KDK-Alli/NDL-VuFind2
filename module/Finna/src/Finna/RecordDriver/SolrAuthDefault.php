<?php
/**
 * Default model for Solr authority records -- used when a more specific
 * model based on the recordtype field cannot be found.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Default model for Solr authority records -- used when a more specific
 * model based on the recordtype field cannot be found.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthDefault extends \VuFind\RecordDriver\SolrAuthDefault
{
    use SolrCommonFinnaTrait;

    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'SolrAuth';

    /**
     * Return the unique identifier of this record within the index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        if (!isset($this->fields['id'])) {
            throw new \Exception('ID not set!');
        }
        return $this->fields['id'];
    }

    /**
     * Is this an authority index record?
     *
     * @return bool
     */
    public function isAuthorityRecord()
    {
        return true;
    }

    /**
     * Return birth date and place.
     *
     * @return string
     */
    public function getBirthDate()
    {
        return null;
    }

    /**
     * Return death date and place.
     *
     * @return string
     */
    public function getDeathDate()
    {
        return null;
    }

    /**
     * Return awards.
     *
     * @return string[]
     */
    public function getAwards()
    {
        return [];
    }

    /**
     * Return summary
     *
     * @return array|null
     */
    public function getSummary()
    {
        return null;
    }

    /**
     * Return authority data sources
     *
     * @return array|null
     */
    public function getSources()
    {
        return null;
    }

    /**
     * Return fields of activity.
     *
     * @return array
     */
    public function getFieldsOfActivity()
    {
        return $this->fields['field_of_activity'] ?? [];
    }

    /**
     * Return occupations.
     *
     * @return array
     */
    public function getOccupations()
    {
        return $this->fields['occupation'] ?? [];
    }

    /**
     * Return place of residence.
     *
     * @return string
     */
    public function getPlaceOfResidence()
    {
        return '';
    }

    /**
     * Return relations to other authority records.
     *
     * @return array
     */
    public function getRelations()
    {
        return [];
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return [$this->fields['record_type']];
    }

    /**
     * Get data source id
     *
     * @return string
     */
    public function getDataSource()
    {
        return isset($this->fields['datasource_str_mv'])
            ? ((array)$this->fields['datasource_str_mv'])[0]
            : '';
    }

    /**
     * Get the institutions holding the record.
     *
     * @return array
     */
    public function getInstitutions()
    {
        return $this->fields['institution'] ?? [];
    }

    /**
     * Is this a Person authority record?
     *
     * @return boolean
     */
    protected function isPerson()
    {
        return $this->fields['record_type'] === 'Personal Name';
    }
}
