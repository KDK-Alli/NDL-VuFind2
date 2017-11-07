<?php
/**
 * KohaRest ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * VuFind Driver for Koha, using REST API
 *
 * Minimum Koha Version: work in progress as of 23 Jan 2017
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    /**
     * Mappings from Koha messaging preferences
     *
     * @var array
     */
    protected $messagingPrefTypeMap = [
        'Advance_Notice' => 'dueDateAlert',
        'Hold_Filled' => 'pickUpNotice',
        'Item_Check_in' => 'checkinNotice',
        'Item_Checkout' => 'checkoutNotice',
        'Item_Due' => 'dueDateNotice'
    ];

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null)
    {
        $data = parent::getHolding($id, $patron);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);

            // Remove request counts before adding the summary if necessary
            if (isset($this->config['Holdings']['display_item_hold_counts'])
                && !$this->config['Holdings']['display_item_hold_counts']
            ) {
                foreach ($data as &$item) {
                    unset($item['requests_placed']);
                }
            }

            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $data = parent::getStatus($id);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fines = parent::getMyFines($patron);
        foreach ($fines as &$fine) {
            $fine['payableOnline'] = true;
        }
        return $fines;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id']], false, 'GET', $patron
        );

        $expirationDate = !empty($result['dateexpiry'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['dateexpiry']
            ) : '';

        $guarantor = [];
        $guarantees = [];
        if (!empty($result['guarantorid'])) {
            $guarantorRecord = $this->makeRequest(
                ['v1', 'patrons', $result['guarantorid']], false, 'GET', $patron
            );
            if ($guarantorRecord) {
                $guarantor['firstname'] = $guarantorRecord['firstname'];
                $guarantor['lastname'] = $guarantorRecord['surname'];
            }
        } else {
            // Assume patron can have guarantees only if there is no guarantor
            $guaranteeRecords = $this->makeRequest(
                ['v1', 'patrons'], ['guarantorid' => $patron['id']], 'GET',
                $patron
            );
            foreach ($guaranteeRecords as $guarantee) {
                $guarantees[] = [
                    'firstname' => $guarantee['firstname'],
                    'lastname' => $guarantee['surname']
                ];
            }
        }

        $messagingPrefs = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );

        $messagingSettings = [];
        foreach ($messagingPrefs as $type => $prefs) {
            $typeName = isset($this->messagingPrefTypeMap[$type])
                ? $this->messagingPrefTypeMap[$type] : $type;
            $settings = [
                'type' => $typeName
            ];
            if (isset($prefs['transport_types'])) {
                $settings['settings']['transport_types'] = [
                    'type' => 'multiselect'
                ];
                foreach ($prefs['transport_types'] as $key => $active) {
                    $settings['settings']['transport_types']['options'][$key] = [
                        'active' => $active
                    ];
                }
            }
            if (isset($prefs['digest'])) {
                $settings['settings']['digest'] = [
                    'type' => 'boolean',
                    'name' => '',
                    'active' => $prefs['digest']['value'],
                    'readonly' => !$prefs['digest']['configurable']
                ];
            }
            if (isset($prefs['days_in_advance'])
                && ($prefs['days_in_advance']['configurable']
                || null !== $prefs['days_in_advance']['value'])
            ) {
                $options = [];
                for ($i = 0; $i <= 30; $i++) {
                    $options[$i] = [
                        'name' => $this->translate(
                            1 === $i ? 'messaging_settings_num_of_days'
                            : 'messaging_settings_num_of_days_plural',
                            ['%%days%%' => $i]
                        ),
                        'active' => $i == $prefs['days_in_advance']['value']
                    ];
                }
                $settings['settings']['days_in_advance'] = [
                    'type' => 'select',
                    'value' => $prefs['days_in_advance']['value'],
                    'options' => $options,
                    'readonly' => !$prefs['days_in_advance']['configurable']
                ];
            }
            $messagingSettings[$type] = $settings;
        }

        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $result['mobile'],
            'smsnumber' => $result['smsalertnumber'],
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['zipcode'],
            'city' => $result['city'],
            'country' => $result['country'],
            'expiration_date' => $expirationDate,
            'hold_identifier' => $result['othernames'],
            'guarantor' => $guarantor,
            'guarantees' => $guarantees,
            'loan_history' => $result['privacy'],
            'messagingServices' => $messagingSettings,
            'full_data' => $result
        ];
    }

    /**
     * Purge Patron Transaction History
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return array Associative array of the results
     */
    public function purgeTransactionHistory($patron)
    {
        list($code, $result) = $this->makeRequest(
            ['v1', 'checkouts', 'history'],
            ['borrowernumber' => $patron['id']],
            'DELETE',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Purging the loan history failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => 'loan_history_purged',
            'sys_message' => ''
        ];
    }

    /**
     * Update Patron Transaction History State
     *
     * Enable or disable patron's transaction history
     *
     * @param array $patron The patron array from patronLogin
     * @param mixed $state  Any of the configured values
     *
     * @return array Associative array of the results
     */
    public function updateTransactionHistoryState($patron, $state)
    {
        $request = [
            'privacy' => (int)$state
        ];

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the checkout history state failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron's phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updatePhone($patron, $phone)
    {
        $request = [
            'mobile' => $phone
        ];
        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron's SMS alert number
     *
     * @param array  $patron Patron array
     * @param string $number SMS alert number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateSmsNumber($patron, $number)
    {
        $request = [
            'smsalertnumber' => $number
        ];
        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron's email address
     *
     * @param array  $patron Patron array
     * @param String $email  Email address
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateEmail($patron, $email)
    {
        $request = [
            'email' => $email
        ];
        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron contact information
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of patron contact information
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateAddress($patron, $details)
    {
        $addressFields = isset($this->config['updateAddress']['fields'])
            ? $this->config['updateAddress']['fields'] : [];
        $addressFields = array_map(
            function ($item) {
                $parts = explode(':', $item, 2);
                return isset($parts[1]) ? $parts[1] : '';
            },
            $addressFields
        );
        $addressFields = array_flip($addressFields);

        // Pick the configured fields from the request
        $request = [];
        foreach ($details as $key => $value) {
            if (isset($addressFields[$key])) {
                $request[$key] = $value;
            }
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the contact information failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron messaging settings
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of messaging settings
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateMessagingSettings($patron, $details)
    {
        $messagingPrefs = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );

        $messagingSettings = [];
        foreach ($details as $prefId => $pref) {
            $result = [];
            foreach ($pref['settings'] as $settingId => $setting) {
                if (!empty($setting['readonly'])) {
                    continue;
                }
                if ('boolean' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => $setting['active']
                    ];
                } elseif ('select' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => ctype_digit($setting['value'])
                            ? (int)$setting['value'] : $setting['value']
                    ];
                } else {
                    foreach ($setting['options'] as $optionId => $option) {
                        $result[$settingId][$optionId] = $option['active'];
                    }
                }
            }
            $messagingSettings[$prefId] = $result;
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            [
                'borrowernumber' => $patron['id'],
                '##body##' => json_encode($messagingSettings)
            ],
            'PUT',
            $patron,
            true
        );
        if ($code >= 300) {
            return  [
                'success' => false,
                'status' => 'Changing the preferences failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $pickUpLocation = $holdDetails['pickupLocationId'];

        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'branchcode' => $pickUpLocation
        ];

        list($code, $result) = $this->makeRequest(
            ['v1', 'holds', $requestId],
            json_encode($request),
            'PUT',
            $patron,
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Change request status
     *
     * This is responsible for changing the status of a hold request
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details (at the moment only 'frozen'
     * is supported)
     *
     * @return array Associative array of the results
     */
    public function changeRequestStatus($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $frozen = !empty($holdDetails['frozen']);

        $request = [
            'suspend' => $frozen
        ];

        list($code, $result) = $this->makeRequest(
            ['v1', 'holds', $requestId],
            json_encode($request),
            'PUT',
            $patron,
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron)
    {
        $fines = $this->getMyFines($patron);
        if (!empty($fines)) {
            $amount = 0;
            foreach ($fines as $fine) {
                $amount += $fine['balance'];
            }
            $config = $this->getConfig('onlinePayment');
            $nonPayableReason = false;
            if (isset($config['minimumFee']) && $amount < $config['minimumFee']) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reason'] = $nonPayableReason;
            }
            return $res;
        }
        return [
            'payable' => false,
            'amount' => 0,
            'reason' => 'online_payment_minimum_fee'
        ];
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron        Patron.
     * @param int    $amount        Amount to be registered as paid
     * @param string $transactionId Transaction ID
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId)
    {
        $request = [
            'amount' => $amount / 100,
            'note' => "Online transaction $transactionId"
        ];
        $operator = $patron;
        if (!empty($this->config['onlinePayment']['userId'])
            && !empty($this->config['onlinePayment']['userPassword'])
        ) {
            $operator = [
                'cat_username' => $this->config['onlinePayment']['userId'],
                'cat_password' => $this->config['onlinePayment']['userPassword']
            ];
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'payment'],
            json_encode($request),
            'POST',
            $operator,
            true
        );
        if ($code != 204) {
            $error = "Failed to mark payment of $amount paid for patron"
                . " {$patron['id']}: $code: $result";

            $this->error($error);
            throw new ILSException($error);
        }
        // Clear patron's block cache
        $cacheId = 'blocks|' . $patron['id'];
        $this->removeCachedData($cacheId);
        return true;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $requests = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            $locations[$item['location']] = true;
            if ($item['requests_placed'] > $requests) {
                $requests = $item['requests_placed'];
            }
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        $result = [
           'available' => $availableTotal,
           'total' => count($holdings),
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => null
        ];
        if (!empty($this->config['Holdings']['display_total_hold_count'])) {
            $result['reservations'] = $requests;
        }
        return $result;
    }

    /**
     * Return a call number for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemCallNumber($item)
    {
        $result = [];
        if (!empty($item['ccode'])) {
            $result[] = $this->translateCollection(
                $item['ccode'],
                isset($item['ccode_description']) ? $item['ccode_description'] : null
            );
        }
        $result[] = $this->translateLocation($item['location']);
        return implode(', ', $result);
    }

    /**
     * Translate location name
     *
     * @param string $location Location code
     *
     * @return string
     */
    protected function translateLocation($location)
    {
        $prefix = 'location_';
        if (!empty($this->config['Catalog']['id'])) {
            $prefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate(
            "$prefix$location",
            null,
            $location
        );
    }

    /**
     * Translate collection name
     *
     * @param string $code        Collection code
     * @param string $description Collection description
     *
     * @return string
     */
    protected function translateCollection($code, $description)
    {
        $prefix = 'collection_';
        if (!empty($this->config['Catalog']['id'])) {
            $prefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate(
            "$prefix$code",
            null,
            $description
        );
    }
}
