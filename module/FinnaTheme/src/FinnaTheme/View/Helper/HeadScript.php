<?php
/**
 * Head script view helper (extended for VuFind's theme system)
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2017.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace FinnaTheme\View\Helper;

use VuFindTheme\ThemeInfo;
use Zend\Http\Request;

/**
 * Head script view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadScript extends \VuFindTheme\View\Helper\HeadScript
{
    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param ThemeInfo   $themeInfo Theme information service
     * @param string|bool $plconfig  Whether or not to concatenate
     * @param Request     $request   Request
     */
    public function __construct(ThemeInfo $themeInfo, $plconfig, Request $request)
    {
        // Disable pipeline on old Android browsers (< 4.0) due to them having
        // trouble handling all the minified data.
        $ua = $request->getHeader('User-Agent');
        $agent = $ua !== false ? $ua->toString() : '';
        if (strstr($agent, 'Mozilla/5.0') !== false
            && strstr($agent, 'Android') !== false
            && preg_match('/WebKit\/(\d+)/', $agent, $matches)
            && $matches[1] <= 534
        ) {
            $plconfig = false;
        }

        parent::__construct($themeInfo, $plconfig);
        $this->request = $request;
    }

    /**
     * Create script HTML
     *
     * @param mixed  $item        Item to convert
     * @param string $indent      String to add before the item
     * @param string $escapeStart Starting sequence
     * @param string $escapeEnd   Ending sequence
     *
     * @return string
     */
    public function itemToString($item, $indent, $escapeStart, $escapeEnd)
    {
        // Remove default type for current html5 compatibility
        if (!empty($item->type) && 'text/javascript' === $item->type) {
            $item->type = '';
        }
        if (!empty($item->attributes['src'])) {
            $ua = $this->request->getHeader('User-Agent');
            $agent = $ua !== false ? $ua->toString() : '';
            if (strstr($agent, 'MSIE 8.0') || strstr($agent, 'MSIE 7.0')) {
                if ($item->attributes['src'] == 'vendor/jquery.min.js') {
                    $item->attributes['src'] = 'vendor/jquery-1.12.1.min.js';
                }
            }
        }
        return parent::itemToString($item, $indent, $escapeStart, $escapeEnd);
    }
}
