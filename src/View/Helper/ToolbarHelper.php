<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         DebugKit 0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace DebugKit\View\Helper;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\View\Helper;
use DebugKit\DebugKitDebugger;

/**
 * Provides Base methods for content specific debug toolbar helpers.
 * Acts as a facade for other toolbars helpers as well.
 *
 * @since         DebugKit 0.1
 */
class ToolbarHelper extends Helper
{

    /**
     * helpers property
     *
     * @var array
     */
    public $helpers = ['Html', 'Form', 'Url'];

    /**
     * Whether or not the top level keys should be sorted.
     *
     * @var bool
     */
    protected $sort = false;

    /**
     * Passed values backtraces
     *
     * @var array
     */
    protected $valuesBT = null;

    /**
     * set sorting of values
     *
     * @param bool $sort Whether or not sort values by key
     */
    public function setBackTraces($valuesBT)
    {
        $this->valuesBT = $valuesBT;
    }

    /**
     * set sorting of values
     *
     * @param bool $sort Whether or not sort values by key
     */
    public function setSort($sort)
    {
        $this->sort = $sort;
    }

    /**
     * Recursively goes through an array and makes neat HTML out of it.
     *
     * @param mixed $values Array to make pretty.
     * @param string $ancestorName name of ancestor property
     * @param int $openDepth Depth to add open class
     * @param int $currentDepth current depth.
     * @param bool $doubleEncode Whether or not to double encode.
     * @param \SplObjectStorage $currentAncestors Object references found down
     * the path.
     * @return string
     */
    public function makeNeatArray($values, $ancestorName = '', $openDepth = 0, $currentDepth = 0, $doubleEncode = false, \SplObjectStorage $currentAncestors = null)
    {
        if ($currentAncestors === null) {
            $ancestors = new \SplObjectStorage();
        } elseif (is_object($values)) {
            $ancestors = new \SplObjectStorage();
            $ancestors->addAll($currentAncestors);
            $ancestors->attach($values);
        } else {
            $ancestors = $currentAncestors;
        }
        $className = "neat-array depth-$currentDepth";
        if ($openDepth > $currentDepth) {
            $className .= ' expanded';
        }

        $nextDepth = $currentDepth + 1;
        $out = "<ul class=\"$className\" title=\"$ancestorName\">";

        if (!is_array($values)) {
            if (is_bool($values)) {
                $values = [$values];
            }
            if ($values === null) {
                $values = [null];
            }

            if (is_object($values)) {
                if (method_exists($values, 'toArray') && !($values instanceof Entity)) {
                    try {
                        $values = $values->toArray();
                    } catch (\Cake\Database\Exception $e) {
                        //Likely issue is unbuffered query; fall back to __debugInfo
                        $values = $values->__debugInfo();
                    }
                } elseif (method_exists($values, '__debugInfo')) {
                    // Convert objects into using __debugInfo.
                    $values = $values->__debugInfo();
                }
            }
        }

        if (empty($values)) {
            $values[] = '(empty)';
        }
        if ($this->sort && is_array($values)) {
            ksort($values, SORT_NATURAL | SORT_FLAG_CASE);
        }
        $last_letter = null;
        foreach ($values as $key => $value) {
            $name = h($key, $doubleEncode);
            $current_first_letter = strtoupper(substr($name, 0, 1));
            $bookmark = '';

            if ($last_letter !== $current_first_letter && $this->sort) {
                $last_letter = $current_first_letter;
                $bookmark = '<span>' . $current_first_letter . '</span>';
            }

            $title = '';
            $last_occurence = '';
            if ($currentDepth === 0 && $this->valuesBT && isset($this->valuesBT[$name]) && $ancestorName !== false) {
                $title = ' title="';
                foreach($this->valuesBT[$name] as $index => $bt) {
                    $title .= $index !== 0 ? '&#013;' : '';
                    $last_occurence = 'Line: ' . $bt['line'] . ' File: ' . str_replace(APP, '', $bt['file']);
                    $last_occurence .= ' Function: ' . $bt['function'];
                    $title .= $last_occurence;

                }
                $title .= '"';
            }

            if ($ancestorName !== false) {
                $current_path = ($ancestorName !== '' ) ? $ancestorName . '.' . $name : $name;
            }
            $out .= '<li' . $title . '>' . $bookmark . '<strong>' . $name . '</strong>';
            if (is_array($value) && count($value) > 0) {
                $btn = '<a href="#openModal" class="btn-primary get-as-json" data-name="' . $current_path . '">';
                $btn .= __d('debug_kit', 'to JSON') . '</a>';
                $out .= '(array)' . ($ancestorName !== false ? $btn : '');
                if ($currentDepth === 0 && $this->valuesBT && isset($this->valuesBT[$name])) {
                    $out .= '<div>' . $last_occurence . '</div>';
                }
            } elseif (is_object($value)) {
                $btn = '<a href="#openModal" class="btn-primary get-as-json" data-name="' . $current_path . '">';
                $btn .= __d('debug_kit', 'to JSON') . '</a>';
                $out .= '(object - ' . get_class($value) . ')' . ($ancestorName !== false ? $btn : '');
                if ($currentDepth === 0 && $this->valuesBT && isset($this->valuesBT[$name])) {
                    $out .= '<div>' . $last_occurence . '</div>';
                }
            }


            if ($value === null) {
                $value = '(null)';
            }
            if ($value === false) {
                $value = '(false)';
            }
            if ($value === true) {
                $value = '(true)';
            }
            if (empty($value) && $value != 0) {
                $value = '(empty)';
            }
            if ($value instanceof Closure) {
                $value = 'function';
            }

            $isObject = is_object($value);
            if ($isObject && $ancestors->contains($value)) {
                $isObject = false;
                $value = ' - recursion';
            }

            if ((
                $value instanceof ArrayAccess ||
                $value instanceof Iterator ||
                is_array($value) ||
                $isObject
                ) && !empty($value)
            ) {
                $out .= $this->makeNeatArray($value, $current_path, $openDepth, $nextDepth, $doubleEncode, $ancestors);
            } else {
                $out .= h($value, $doubleEncode);
                if ($currentDepth === 0 && $this->valuesBT && isset($this->valuesBT[$name])) {
                    $out .= '<div>' . $last_occurence . '</div>';
                }
            }

            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }
}
