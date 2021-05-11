<?php
/**
 * Abstract base custom element
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
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace Finna\View\CustomElement;

use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Options;

/**
 * Abstract base custom element
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class AbstractBase implements CustomElementInterface
{
    /**
     * Regex for matching valid element names
     *
     * @var string
     */
    protected $validNameRegex = '/[A-Za-z][A-Za-z0-9]*-[A-Za-z0-9-]+/';

    /**
     * Element name
     *
     * @var string
     */
    protected $name;

    /**
     * Options
     *
     * The base class supports the following options:
     * - attributes
     *     Array of attributes for the custom element. These will overwrite
     *     attributes parsed from outerHTML.
     * - outerHTML
     *     Outer HTML of the element. This will be parsed to a DOM object and
     *     attributes.
     *
     * @var array
     */
    protected $options;

    /**
     * Attributes
     *
     * @var array
     */
    protected $attributes;

    /**
     * DOM object for the custom element if outerHTML is provided in options
     *
     * @var Dom
     */
    protected $dom = null;

    /**
     * View model for server-side rendering
     *
     * @var ModelInterface
     */
    protected $viewModel;

    /**
     * AbstractBase constructor.
     *
     * @param string $name          Element name
     * @param array  $options       Options
     * @param bool   $convertToBool Convert string true/false values to booleans
     */
    public function __construct(string $name, array $options = [],
        bool $convertToBool = false
    ) {
        if (!preg_match($this->validNameRegex, $name)) {
            throw new \Exception('Element name is not valid');
        }
        $this->name = $name;

        $attributes = $options['attributes'] ?? [];

        // If outer HTML is set, set up the DOM object and process attributes.
        if (isset($options['outerHTML'])) {
            $dom = (new Dom())->loadStr(
                $options['outerHTML'],
                (new Options())->setCleanupInput(false)
            );
            if ($dom->countChildren() === 1
                && $dom->firstChild()->getTag()->name() === $this->getName()
            ) {
                $this->dom = $dom;

                // Attributes set in options overwrite attributes set in HTML.
                $attributes = array_merge(
                    $dom->firstChild()->getAttributes(), $attributes
                );
            }
        }

        if ($convertToBool) {
            $options = $this->convertToBool($options);
            $attributes = $this->convertToBool($attributes);
        }

        // Get default variable values.
        $variables = $this->getDefaultVariables();

        // Try to set variable values from attributes, if defined by subclass.
        foreach ($this->getAttributeToVariableMap()
            as $attributeName => $variableName
        ) {
            if (array_key_exists($attributeName, $attributes)) {
                $variables[$variableName] = $attributes[$attributeName];
            }
        }

        // Try to set variable values from options, if defined by subclass.
        // Option values overwrite attribute values when setting variables.
        foreach ($this->getOptionToVariableMap() as $optionName => $variableName) {
            if (array_key_exists($optionName, $options)) {
                $variables[$variableName] = $options[$optionName];
            }
        }

        $this->options = $options;
        $this->attributes = $attributes;
        $this->viewModel = new ViewModel($variables);
    }

    /**
     * Get the name of the element.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the view model for server-side rendering the element.
     *
     * @return ModelInterface
     */
    public function getViewModel(): ModelInterface
    {
        return $this->viewModel;
    }

    /**
     * Get default values for view model variables.
     *
     * @return array
     */
    protected function getDefaultVariables(): array
    {
        return [];
    }

    /**
     * Get names of attributes to set as view model variables.
     *
     * @return array Keyed array with attribute names as keys and variable names as
     *               values
     */
    protected function getAttributeToVariableMap(): array
    {
        return [];
    }

    /**
     * Get names of options to set as view model variables.
     *
     * @return array Keyed array with option names as keys and variable names as
     *               values
     */
    protected function getOptionToVariableMap(): array
    {
        return $this->getAttributeToVariableMap();
    }

    /**
     * Convert string true/false values to boolean values.
     *
     * @param array $values Array of options, attributes or variables.
     *
     * @return array
     */
    protected function convertToBool(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($value, 'true') === 0) {
                    $values[$key] = true;
                } elseif (strcasecmp($value, 'false') === 0) {
                    $values[$key] = false;
                }
            }
        }
        return $values;
    }
}
