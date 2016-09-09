<?php
namespace FinnaConsole\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'util' => 'FinnaConsole\Controller\UtilController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\HMAC' => 'VuFind\Service\Factory::getHMAC',
            'Finna\AccountExpirationReminders' => 'FinnaConsole\Service\Factory::getAccountExpirationReminders',
            'Finna\ClearMetalibSearch' => 'FinnaConsole\Service\Factory::getClearMetaLibSearch',
            'Finna\DueDateReminders' => 'FinnaConsole\Service\Factory::getDueDateReminders',
            'Finna\EncryptCatalogPasswords' => 'FinnaConsole\Service\Factory::getEncryptCatalogPasswords',
            'Finna\ExpireUsers' => 'FinnaConsole\Service\Factory::getExpireUsers',
            'Finna\OnlinePaymentMonitor' => 'FinnaConsole\Service\Factory::getOnlinePaymentMonitor',
            'Finna\ScheduledAlerts' => 'FinnaConsole\Service\Factory::getScheduledAlerts',
            'Finna\UpdateSearchHashes' => 'FinnaConsole\Service\Factory::getUpdateSearchHashes',
            'Finna\VerifyRecordLinks' => 'FinnaConsole\Service\Factory::getVerifyRecordLinks'
        ]
    ]
];

return $config;
