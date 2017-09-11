<?php
/**
 * Model for EAD3 records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2017.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAD3 records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEac extends SolrAuth
{
    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $titles = [];
        $path = 'cpfDescription/identity/nameEntryParallel/nameEntry';
        foreach ($this->getSimpleXML()->xpath($path) as $name) {
            $titles[] = $name->part[0];
        }
        return $titles;
    }

    /**
     * Return description
     *
     * @return string|null
     */
    public function getDescription()
    {
        $record = $this->getSimpleXML();
        if (isset($record->cpfDescription->description->biogHist->p)) {
            return (string)$record->cpfDescription->description->biogHist->p;
        }
        return null;
    }

    /**
     * Get authority title
     *
     * @return string|null
     */
    public function getTitle()
    {
        $record = $this->getSimpleXML();
        if (isset($record->cpfDescription->identity->nameEntry->part[0])) {
            return (string)$record->cpfDescription->identity->nameEntry->part[0];
        }
        return null;
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    public function getSimpleXML()
    {
        if ($this->simpleXML === null) {
            $this->simpleXML = simplexml_load_string($this->fields['fullrecord']);
        }
        return $this->simpleXML;
    }
}
