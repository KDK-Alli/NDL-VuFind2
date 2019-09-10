<?php
/**
 * Blender backend.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\Blender;

use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * Blender backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend implements RetrieveBatchInterface
{
    /**
     * Primary backend
     *
     * @var AbstractBackend
     */
    protected $primaryBackend;

    /**
     * Secondary backend
     *
     * @var AbstractBackend
     */
    protected $secondaryBackend;

    /**
     * Limit for number of records to blend
     *
     * @var int
     */
    protected $blendLimit;

    /**
     * Block size for interleaved records
     *
     * @var int
     */
    protected $blockSize;

    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param AbstractBackend     $primary   Primary backend
     * @param AbstractBackend     $secondary Secondary backend
     * @param \Zend\Config\Config $config    Blender config
     *
     * @return void
     */
    public function __construct(AbstractBackend $primary, AbstractBackend $secondary,
        \Zend\Config\Config $config
    ) {
        $this->primaryBackend = $primary;
        $this->secondaryBackend = $secondary;
        $this->config = $config;
        $this->blendLimit = min(100, $this->config['Results']['blendLimit'] ?? 100);
        $this->blockSize = $this->config['Results']['blockSize'] ?? 10;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(AbstractQuery $query, $offset, $limit,
        ParamBag $params = null
    ) {
        $mergedCollection = new Response\Json\RecordCollection($this->config);

        $secondaryQuery = $this->translateQuery($query);
        $secondaryParams = $params->get('secondary_backend')[0];
        $params->remove('secondary_backend');
        // If offset is less than the limit, fetch from both backends
        // up to the limit first.
        if ($offset <= $this->blendLimit) {
            $primaryCollection = $this->primaryBackend->search(
                $query,
                0,
                $this->blendLimit,
                $params
            );

            $secondaryCollection = $this->secondaryBackend->search(
                $secondaryQuery,
                0,
                $this->blendLimit,
                $secondaryParams
            );

            $mergedCollection->initBlended(
                $primaryCollection,
                $secondaryCollection,
                $offset,
                $limit,
                $this->blockSize
            );
        } else {
            $primaryCollection = $this->primaryBackend->search(
                $query,
                0,
                0,
                $params
            );

            $secondaryCollection = $this->secondaryBackend->search(
                $secondaryQuery,
                0,
                0,
                $secondaryParams
            );

            $mergedCollection->initBlended(
                $primaryCollection,
                $secondaryCollection,
                $offset,
                $limit,
                $this->blockSize
            );
        }

        // Fill up to the required records in a round-robin fashion
        if ($offset + $limit > $this->blendLimit) {
            $primaryTotal = $primaryCollection->getTotal();
            $secondaryTotal = $secondaryCollection->getTotal();
            $primaryOffset = $mergedCollection->getPrimaryCount();
            $secondaryOffset = $mergedCollection->getSecondaryCount();

            for ($offset = $this->blendLimit; $offset < $limit; $offset++) {
                $primary = ($offset / $this->blockSize) % 2 === 0;
                if ($primary && $offset >= $primaryTotal) {
                    if ($offset >= $secondaryTotal) {
                        break;
                    }
                    $primary = false;
                }
                if ($primary) {
                    $record = $this->getRecord(
                        $this->primaryBackend,
                        $params,
                        $primaryCollection,
                        $query,
                        $primaryOffset
                    );
                    ++$primaryOffset;
                } else {
                    $record = $this->getRecord(
                        $this->secondaryBackend,
                        $secondaryParams,
                        $secondaryCollection,
                        $query,
                        $secondaryOffset
                    );
                    ++$secondaryOffset;
                }
                if (null !== $record) {
                    $mergedCollection->add($record);
                }
            }
        }

        $mergedCollection->setSourceIdentifier($this->identifier);

        return $mergedCollection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function retrieve($id, ParamBag $params = null)
    {
        $result = $this->primaryBackend->retrieve($id, $params);
        if ($result->count() === 0) {
            $result = $this->secondaryBackend->retrieve($id, $params);
        }
        return $result;
    }

    /**
     * Retrieve a batch of documents.
     *
     * @param array    $ids    Array of document identifiers
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $results = $this->primaryBackend->retrieveBatch($ids, $params);
        $found = [];
        foreach ($results->getRecords() as $record) {
            $found[] = $record->getUniqueID();
        }
        $missing = array_diff($ids, $found);
        if ($missing) {
            if (is_callable([$this->secondaryBackend, 'retrieveBatch'])) {
                $secondResults = $this->secondaryBackend->retrieveBatch(
                    $missing,
                    $params
                );
                foreach ($secondResults->getRecords() as $record) {
                    $results->add($record);
                }
            } else {
                foreach ($missing as $id) {
                    $secondResults = $this->secondaryBackend->retrieve($id, $params);
                    $records = $secondResults->getRecords();
                    if ($records) {
                        $results->add($records[0]);
                    }
                }
            }
        }

        $results->setSourceIdentifier($this->identifier);
        return $results;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory()
    {
        return null;
    }

    /**
     * Get a record from the given backend by offset
     *
     * @param AbstractBackend           $backend    Backend
     * @param RecordCollectionInterface $collection Record collection
     * @param AbstractQuery             $query      Query
     * @param int                       $offset     Record offset
     *
     * @return array
     */
    protected function getRecord(AbstractBackend $backend,
        ParamBag $params, RecordCollectionInterface $collection,
        AbstractQuery $query, $offset
    ) {
        $records = $collection->getRecords();
        if ($offset < count($records)) {
            return $records[offset];
        }
        $collection = $backend->search($query, $offset, $this->blockSize, $params);
        $records = $collection->getRecords();
        return $records[$offset] ?? null;
    }

    /**
     * Translate query from the primary backend format to secondary backend format
     *
     * @param AbstractQuery $query Query
     *
     * @return AbstractQuery
     */
    protected function translateQuery(AbstractQuery $query)
    {
        return $query;
    }
}
