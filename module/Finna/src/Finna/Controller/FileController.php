<?php
/**
 * File Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Finna\File\Loader as FileLoader;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Session\Settings as SessionSettings;

/**
 * File Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FileController extends \Laminas\Mvc\Controller\AbstractActionController
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * File loader
     *
     * @var FileLoader
     */
    protected $fileLoader;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Session settings
     *
     * @var SessionSettings
     */
    protected $sessionSettings;

    /**
     * Constructor
     *
     * @param RecordLoader    $recordLoader Record loader
     * @param FileLoader      $fileLoader   File loader
     * @param CacheManager    $cm           Cache manager
     * @param SessionSettings $ss           Session settings
     */
    public function __construct(
        RecordLoader $recordLoader, FileLoader $fileLoader,
        CacheManager $cm, SessionSettings $ss
    ) {
        $this->recordLoader = $recordLoader;
        $this->fileLoader = $fileLoader;
        $this->cacheManager = $cm;
        $this->sessionSettings = $ss;
    }

    /**
     * Download 3D model
     *
     * @return \Laminas\Http\Response
     */
    public function downloadModelAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug
        $params = $this->params();
        $id = $params->fromQuery('id');
        $index = $params->fromQuery('index');
        $format = $params->fromQuery('format', '');
        $response = $this->getResponse();

        if ($id && $index) {
            $driver = $this->recordLoader->load(
                $id, $params->fromQuery('source') ?? DEFAULT_SEARCH_BACKEND
            );
            $filename = urlencode($id) . '-' . $index . '.' . $format;
            $models = $driver->tryMethod('getModels');
            $url = $models[$index][$format]['preview'] ?? false;
            if (!empty($url)) {
                $fileName = urlencode($id) . '-' . $index . '.' . $format;
                $file = $this->fileLoader->getFile(
                    $url, $fileName, 'Models', 'public'
                );
                if (!empty($file['result'])) {
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
                        "Content-disposition: attachment; filename=\"{$filename}\""
                    );
                    //No cache
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file['path']));
                    ob_clean();
                    flush();
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