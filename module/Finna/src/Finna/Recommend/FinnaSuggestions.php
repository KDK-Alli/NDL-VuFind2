<?php
/**
 * FinnaSuggestionsDeferred Recommendations Module
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Recommendations
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Recommend;

use VuFind\View\Helper\Root\Url;
use Zend\Http\Client;

/**
 * FinnaSuggestionsDeferred Recommendations Module
 *
 * This class provides recommendations via VuFind REST API (deferred).
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FinnaSuggestions implements \VuFind\Recommend\RecommendInterface,
    \VuFindHttp\HttpServiceAwareInterface, \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * API url
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Search URL
     *
     * @var string
     */
    protected $searchUrl;

    /**
     * Settings from searches.ini
     *
     * @var string
     */
    protected $settings;

    /**
     * Search term
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Search handler
     *
     * @var string
     */
    protected $searchHandler;

    /**
     * Search type
     *
     * @var string
     */
    protected $searchType;

    /**
     * Result count
     *
     * @var int
     */
    protected $resultCount;

    /**
     * HTTP client.
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * Current locale.
     *
     * @var string
     */
    protected $locale;

    /**
     * URL helper.
     *
     * @var \VuFind\View\Helper\Root\Url
     */
    protected $urlHelper;

    /**
     * FinnaSuggestions constructor.
     *
     * @param Client $client    HTTP client
     * @param string $locale    Current locale
     * @param Url    $urlHelper URL helper
     */
    public function __construct(
        Client $client, string $locale, Url $urlHelper
    ) {
        $this->client = $client;
        $this->locale = $locale;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        $lookfor = $request->get('lookfor');
        $searchHandler
            = $params->getSearchHandler() ?: $request->get('searchHandler');
        $searchType
            = $params->getSearchType() ?: $request->get('searchType');

        // Output suggestions only for basic search with
        // AllFields handler and no filters.
        if (!empty($lookfor)
            && !$params->getFilters()
            && $searchHandler === 'AllFields'
            && $searchType === 'basic'
        ) {
            $this->lookfor = $lookfor;
            $this->searchHandler = $searchHandler;
            $this->searchType = $searchType;
        }
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        return [
            'lookfor' => $this->lookfor,
            'resultCount' => $this->resultCount,
            'searchLink' => $this->getSearchLink()
        ];
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $this->settings = $settings;
        $settings = explode(':', $settings);
        $this->apiUrl = ('https://' . $settings[0]) ?? null;
        $this->searchUrl = ('https://' . $settings[1]) ?? null;
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        if (!$this->lookfor) {
            return;
        }

        $url = $this->apiUrl;

        $client = $this->client->setUri($url);
        $client->setOptions(
            [
                'timeout' => 30,
                'useragent' => 'VuFind'
            ]
        );
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );
        $client->setParameterGet(['lookfor' => $this->lookfor, 'limit' => 0]);
        $response = $client->setMethod('GET')->send();

        if (!$response->isSuccess()) {
            return;
        }

        $result = $response->getBody();
        $result = json_decode($result, true);

        if ($result['status'] === 'OK') {
            if ($resultCount = $result['resultCount'] ?? null) {
                $this->resultCount = $resultCount;
            }
        }
    }

    /**
     * Get search link to Finna
     *
     * @return string
     */
    protected function getSearchLink()
    {
        $base = $this->urlHelper->__invoke('home');
        $search = $this->urlHelper->__invoke('search-results');
        $search = substr($search, strlen($base));

        $query = http_build_query(
            ['lookfor' => $this->lookfor, 'lng' => $this->locale]
        );

        $base = $this->searchUrl;
        if (substr($base, -1) !== '/') {
            $base .= '/';
        }
        return "$base$search?$query";
    }
}
