<?php
/**
 * Record Controller
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use VuFindSearch\ParamBag;

/**
 * Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends \VuFind\Controller\RecordController
{
    use FinnaRecordControllerTrait;

    /**
     * Create record feedback form and send feedback to correct recipient.
     *
     * @return \Laminas\View\Model\ViewModel
     * @throws \Exception
     */
    public function feedbackAction()
    {
        $driver = $this->loadRecord();
        $recordPlugin = $this->getViewRenderer()->plugin('record');

        $data = [
           'record' => $driver->getBreadcrumb(),
           'record_info' => $recordPlugin($driver)->getEmail()
        ];

        return $this->redirect()->toRoute(
            'feedback-form',
            ['id' => 'FeedbackRecord'],
            ['query' => [
                'data' => $data,
                'layout' => $this->getRequest()->getQuery('layout', false),
                'record_id'
                => $driver->getSourceIdentifier() . '|' . $driver->getUniqueID()
            ]]
        );
    }

    /**
     * Load a normalized record from RecordManager for preview
     *
     * @param string $data   Record Metadata
     * @param string $format Metadata format
     * @param string $source Data source
     *
     * @return AbstractRecordDriver
     * @throw  \Exception
     */
    protected function loadPreviewRecord($data, $format, $source)
    {
        $config = $this->getConfig();
        if (empty($config->NormalizationPreview->url)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient(
            $config->NormalizationPreview->url,
            \Laminas\Http\Request::METHOD_POST
        );
        $client->setOptions(['useragent' => 'FinnaRecordPreview VuFind']);
        $client->setParameterPost(
            ['data' => $data, 'format' => $format, 'source' => $source]
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            if ($response->getStatusCode() === 400) {
                $this->flashMessenger()->addErrorMessage('Failed to load preview');
                $result = json_decode($response->getBody(), true);
                foreach (explode("\n", $result['error_message']) as $msg) {
                    if ($msg) {
                        $this->flashMessenger()->addErrorMessage($msg);
                    }
                }
                $metadata = [
                    'id' => '1',
                    'record_format' => $format,
                    'title' => 'Failed to load preview',
                    'title_short' => 'Failed to load preview',
                    'title_full' => 'Failed to load preview',
                    // This works for MARC and other XML loaders too
                    'fullrecord'
                        => '<collection><record><leader/></record></collection>'
                ];
            } else {
                throw new \Exception(
                    'Failed to load preview: ' . $response->getStatusCode() . ' '
                    . $response->getReasonPhrase()
                );
            }
        } else {
            $body = $response->getBody();
            $metadata = json_decode($body, true);
        }
        $recordFactory = $this->serviceLocator
            ->get(\VuFind\RecordDriver\PluginManager::class);
        $this->driver = $recordFactory->getSolrRecord($metadata);
        return $this->driver;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @param ParamBag $params Search backend parameters
     * @param bool     $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(ParamBag $params = null, bool $force = false)
    {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        // 0 = preview record
        if ($id != '0') {
            return parent::loadRecord($params, $force);
        }
        $data = $this->params()->fromPost(
            'data', $this->params()->fromQuery('data', '')
        );
        $format = $this->params()->fromPost(
            'format', $this->params()->fromQuery('format', '')
        );
        $source = $this->params()->fromPost(
            'source', $this->params()->fromQuery('source', '')
        );
        if (!$data) {
            // Support marc parameter for backwards-compatibility
            $format = 'marc';
            if (!$source) {
                $source = '_marc_preview';
            }
            $data = $this->params()->fromPost(
                'marc', $this->params()->fromQuery('marc')
            );
            $marc = new \File_MARC($data, \File_MARC::SOURCE_STRING);
            $record = $marc->next();
            if (false === $record) {
                throw new \Exception('Missing record data');
            }
            $data = $record->toXML();
            $data = preg_replace('/[\x00-\x09,\x11,\x12,\x14-\x1f]/', '', $data);
            $data = iconv('UTF-8', 'UTF-8//IGNORE', $data);
        }
        if (!$data || !$format || !$source) {
            throw new \Exception('Missing parameters');
        }

        return $this->loadPreviewRecord($data, $format, $source);
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Special case -- handle lightbox login request if login has already been
        // done
        if ($this->inLightbox()
            && $this->params()->fromQuery('catalogLogin', 'false') == 'true'
            && is_array($this->catalogLogin())
        ) {
            $response = $this->getResponse();
            $response->setStatusCode(205);
            return $response;
        }

        $view = parent::showTab($tab, $ajax);
        //$view->scrollData = $this->resultScroller()->getScrollData($driver);

        $this->getSearchMemory()->rememberScrollData($view->scrollData);
        return $view;
    }

    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction(
            'Holds',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkHolds) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Call checkFunction once more now that we have the gathered details since
        // details may affect the fields to display:
        if ($gatheredDetails) {
            $checkHolds = $catalog->checkFunction(
                'Holds',
                [
                    'id' => $driver->getUniqueID(),
                    'patron' => $patron,
                    'details' => $gatheredDetails,
                ]
            );
            if (!$checkHolds) {
                return $this->redirectToRecord();
            }
        }

        // Block invalid requests:
        try {
            $validRequest = $catalog->checkRequestIsValid(
                $driver->getUniqueID(),
                $gatheredDetails,
                $patron
            );
        } catch (\VuFind\Exception\ILS $e) {
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            return $this->redirectToRecord('#top');
        }
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'hold_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $requestGroups = $catalog->checkCapability(
            'getRequestGroups', [$driver->getUniqueID(), $patron, $gatheredDetails]
        ) ? $catalog->getRequestGroups(
            $driver->getUniqueID(), $patron, $gatheredDetails
        ) : [];
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : [];

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || ($gatheredDetails['level'] != 'copy'
                    || count($requestGroups) > 1));

        $pickupDetails = $gatheredDetails;
        if (!$requestGroupNeeded && !empty($requestGroups)
            && count($requestGroups) == 1
        ) {
            // Request group selection is not required, but we have a single request
            // group, so make sure pickup locations match with the group
            $pickupDetails['requestGroupId'] = $requestGroups[0]['id'];
        }
        try {
            $pickup = $catalog->getPickUpLocations($patron, $pickupDetails);
        } catch (\VuFind\Exception\ILS $e) {
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            return $this->redirectToRecord('#top');
        }

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeHold')) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $validGroup = $this->holds()->validateRequestGroupInput(
                $gatheredDetails, $extraHoldFields, $requestGroups
            );
            $validPickup = $validGroup && $this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? '', $extraHoldFields, $pickup
            );
            if (!$validGroup) {
                $this->flashMessenger()
                    ->addMessage('hold_invalid_request_group', 'error');
            } elseif (!$validPickup) {
                $this->flashMessenger()->addMessage('hold_invalid_pickup', 'error');
            } elseif (in_array('acceptTerms', $extraHoldFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                try {
                    $function = (string)$checkHolds['function'];
                    $results = $catalog->$function($holdDetails);
                } catch (\VuFind\Exception\ILS $e) {
                    $this->flashMessenger()
                        ->addErrorMessage('ils_connection_failed');
                }

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()->fromRoute('holds-list')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()
                            ->addMessage($results['status'], 'error');
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->holds()->getDefaultRequiredDate(
            $checkHolds, $catalog, $patron, $gatheredDetails
        );
        $defaultRequired = $this->serviceLocator->get(\VuFind\Date\Converter::class)
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
                ? false
                : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => $defaultRequired,
                'requestGroups' => $requestGroups,
                'defaultRequestGroup' => $defaultRequestGroup,
                'requestGroupNeeded' => $requestGroupNeeded,
                'helpText' => $checkHolds['helpText'] ?? null,
                'acceptTermsText' => $checkHolds['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/hold');
        return $view;
    }

    /**
     * Action for dealing with storage retrieval requests.
     *
     * @return mixed
     */
    public function storageRetrievalRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'StorageRetrievalRequests',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->storageRetrievalRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkStorageRetrievalRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status']
                    : 'storage_retrieval_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $extraFields = isset($checkRequests['extraFields'])
            ? explode(":", $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeStorageRetrievalRequest')) {
            if (in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                // Success: Go to Display Storage Retrieval Requests
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'storage_retrieval_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-storageretrievalrequests')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->addMessage(
                            $results['status'], 'error'
                        );
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->storageRetrievalRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get(\VuFind\Date\Converter::class)
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/storageretrievalrequest');
        return $view;
    }

    /**
     * Action for dealing with ILL requests.
     *
     * @return mixed
     */
    public function illRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'ILLRequests',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->ILLRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkILLRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'ill_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:

        $extraFields = isset($checkRequests['extraFields'])
            ? explode(":", $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeILLRequest')) {
            if (in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                // Success: Go to Display ILL Requests
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'ill_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-illrequests')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()
                            ->addMessage($results['status'], 'error');
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->ILLRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get(\VuFind\Date\Converter::class)
            ->convertToDisplayDate("U", $defaultRequired);

        // Get pickup libraries
        $pickupLibraries = $catalog->getILLPickUpLibraries(
            $driver->getUniqueID(), $patron, $gatheredDetails
        );

        // Get pickup locations. Note that these are independent of pickup library,
        // and library specific locations must be retrieved when a library is
        // selected.
        $pickupLocations = $catalog->getPickUpLocations($patron, $gatheredDetails);

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickupLibraries' => $pickupLibraries,
                'pickupLocations' => $pickupLocations,
                'homeLibrary' => $this->getUser()->home_library,
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/illrequest');
        return $view;
    }

    /**
     * Action for record preview form.
     *
     * @return mixed
     */
    public function previewFormAction()
    {
        $config = $this->getConfig();
        if (empty($config->NormalizationPreview->url)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient(
            $config->NormalizationPreview->url,
            \Laminas\Http\Request::METHOD_POST
        );
        $client->setOptions(['useragent' => 'FinnaRecordPreview VuFind']);
        $client->setParameterPost(
            ['func' => 'get_sources']
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            throw new \Exception(
                'Failed to load source list: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase()
            );
        }
        $body = $response->getBody();
        $sources = json_decode($body, true);
        array_walk(
            $sources,
            function (&$a) {
                if ($a['institution'] === '_preview') {
                    $a['institutionName'] = $this->translate('Generic Preview');
                } else {
                    $a['institutionName'] = $this->translate(
                        '0/' . $a['institution'] . '/', [], $a['institution']
                    );
                }
            }
        );
        usort(
            $sources,
            function ($a, $b) {
                $res = strcmp($a['institutionName'], $b['institutionName']);
                if ($res === 0) {
                    $res = strcasecmp($a['id'], $b['id']);
                }
                return $res;
            }
        );
        $view = new \Laminas\View\Model\ViewModel(
            [
                'sources' => $sources
            ]
        );
        return $view;
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processSave()
    {
        $result = parent::processSave();
        if ($this->inLightbox()) {
            if ($this->flashMessenger()->hasErrorMessages()) {
                return $result;
            }
            // Return HTTP 204 so that the modal gets closed.
            // Clear success flashmessages so that they dont get shown in
            // the following modal.
            $this->flashMessenger()->clearMessagesFromNamespace('success');
            $this->flashMessenger()->clearCurrentMessages('success');
            $response = $this->getResponse();
            $response->setStatusCode(204);
            return $response;
        }
        return $result;
    }

    /**
     * Download 3D model
     *
     * @return \Laminas\Http\Response
     */
    public function downloadModelAction()
    {
        $params = $this->params();
        $index = $params->fromQuery('index');
        $format = $params->fromQuery('format');
        $response = $this->getResponse();
        if ($format && $index) {
            $driver = $this->loadRecord();
            $id = $driver->getUniqueID();
            $models = $driver->tryMethod('getModels');
            $url = $models[$index][$format]['preview'] ?? false;
            if (!empty($url)) {
                $fileName = urlencode($id) . '-' . $index . '.' . $format;
                $fileLoader = $this->serviceLocator->get(\Finna\File\Loader::class);
                $file = $fileLoader->getFile(
                    $url, $fileName, 'Models', 'public'
                );
                if (empty($file['result'])) {
                    $response->setStatusCode(500);
                } else {
                    $contentType = '';
                    switch ($format) {
                    case 'gltf':
                        $contentType = 'model/gltf+json';
                        break;
                    case 'glb':
                        $contentType = 'model/gltf+binary';
                        break;
                    default:
                        $contentType = 'application/octet-stream';
                        break;
                    }
                    // Set headers for downloadable file
                    header("Content-Type: $contentType");
                    header(
                        "Content-disposition: attachment; filename=\"{$fileName}\""
                    );
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file['path']));
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($file['path']);
                }
            } else {
                $response->setStatusCode(404);
            }
        } else {
            $response->setStatusCode(400);
        }

        return $response;
    }
}
