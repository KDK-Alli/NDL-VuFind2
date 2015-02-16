<?php
/**
 * Header view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Header view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordImage extends \Zend\View\Helper\AbstractHelper
{

    /**
     * Image parameters
     *
     * @var array
     */
    protected $params;

    /**
     * Record view helper
     *
     * @var Zend\View\Helper\Record
     */
    protected $record;

    /**
     * Assign record image URLs to the view and return header view helper.
     *
     * @param \Finna\View\Helper\Root\Record $record Record helper.
     *
     * @return FInna\View\Helper\Root\Header
     */
    public function __invoke(\Finna\View\Helper\Root\Record $record)
    {
        $this->params['small'] = $this->params['medium'] = $this->params['large'] 
            = array('bg' => 'ffffff');
        $this->record = $record;

        return $this;
    }

    /**
     * Return URL to large record image.
     *
     * @param int $index Record image index.
     *
     * @return mixed string URL or false if no 
     * image with the given index was found.
     */  
    public function getLargeImage($index = 0)
    {
        $cnt = $this->record->getNumOfRecordImages('large');
        if ($cnt > $index) {
            $urlHelper = $this->getView()->plugin('url');
            $params = $this->record->getRecordImage('large');   
            unset($params['url']);
            
            $params['index'] = $index;

            return
                $urlHelper('cover-show') . '?' . 
                http_build_query(array_merge($params, $this->params['large']));
        }
        return false;
    }

    /**
     * Return rendered record image HTML.
     *
     * @param string $type   Page type (list, record).
     * @param array  $params Optional array of image parameters as 
     *                       an associative array of parameter => value pairs:
     *                         'w'    Width
     *                         'h'    Height
     *                         'maxh' Maximum height
     *                         'bg'   Background color, hex value
     *
     * @return string 
     */  
    public function render($type = 'list', $params = null)
    {
        if ($this->record->getSourceIdentifier() !== 'Solr') {
            return;
        }

        if ($params) {
            foreach ($params as $size => $sizeParams) {
                $this->params[$size] 
                    = array_merge($this->params[$size], $sizeParams);
            }
        }

        $view = $this->getView();
        $view->type = $type;

        $view = $this->getView();
        $urlHelper = $this->getView()->plugin('url');
        $numOfImages = $this->record->getNumOfRecordImages('large');

        $params = $this->record->getRecordImage('small');
        unset($params['url']);
        unset($params['size']);

        $view->smallImage = $urlHelper('cover-show') . '?' . 
            http_build_query(array_merge($params, $this->params['small']));

        $params = $this->record->getRecordImage('large');
        unset($params['url']);
        unset($params['size']);

        $view->mediumImage = $urlHelper('cover-show') . '?' . 
            http_build_query(array_merge($params, $this->params['medium']));
        
        $view->largeImage = $urlHelper('cover-show') . '?' . 
            http_build_query(array_merge($params, $this->params['large']));
        
        $images = array();
        if ($numOfImages > 1) {
            for ($i=0; $i<$numOfImages; $i++) {
                $params['index'] = $i;
                $images[] = array(
                    'small' => $urlHelper('cover-show') . '?' . 
                        http_build_query(
                            array_merge($params, $this->params['small'])
                        ),

                    'medium' => $urlHelper('cover-show') . '?' . 
                        http_build_query(
                            array_merge($params, $this->params['medium'])
                        ),
                    
                    'large' => $urlHelper('cover-show') . '?' . 
                        http_build_query(
                            array_merge($params, $this->params['large'])
                        )
                );
            }
        }
        $view->allImages = $images;

        return $view->render('RecordDriver/SolrDefault/record-image.phtml');    
    }
}
