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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace DebugKit\Controller;

use Cake\Cache\Cache;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\Entity;
use Cake\Utility\Hash;

/**
 * Provides utility features need by the toolbar.
 */
class ToolbarController extends Controller
{

    /**
     * components
     *
     * @var array
     */
    public $components = ['RequestHandler'];

    /**
     * View class
     *
     * @var string
     */
    public $viewClass = 'Cake\View\JsonView';

    /**
     * Before filter handler.
     *
     * @param \Cake\Event\Event $event The event.
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException
     */
    public function beforeFilter(Event $event)
    {
        // TODO add config override.
        if (!Configure::read('debug')) {
            throw new NotFoundException();
        }
    }

    /**
     * Clear a named cache.
     *
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException
     */
    public function clearCache()
    {
        $this->request->allowMethod('post');
        if (!$this->request->data('name')) {
            throw new NotFoundException('Invalid cache engine name.');
        }
        $result = Cache::clear(false, $this->request->data('name'));
        $this->set([
            '_serialize' => ['success'],
            'success' => $result,
        ]);
    }

    /**
     * Get variable as json
     *
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException
     */
    public function getVariableAsJson($path, $id)
    {
        $this->request->allowMethod('get');

        $this->loadModel('DebugKit.Panels');
        $panel = $this->Panels->get($id);
        $data = unserialize($panel->content);

        switch($panel->panel) {
            case 'Session' :
                $data = $data['content'];
                break;
            case 'Variables' :
                $data = $data['content'];
                break;
        }

        $walker = function (&$item) use (&$walker) {
            if (method_exists($item, 'toArray') && !($item instanceof Entity)) {
                try {
                    $item = $item->toArray();
                } catch (\Cake\Database\Exception $e) {
                    //Likely issue is unbuffered query; fall back to __debugInfo
                    $item = array_map($walker, $item->__debugInfo());
                }
            } elseif ($item instanceof Closure ||
                $item instanceof PDO ||
                $item instanceof SimpleXmlElement
            ) {
                $item = 'Unserializable object - ' . get_class($item);
            } elseif ($item instanceof Exception) {
                $item = sprintf(
                    'Unserializable object - %s. Error: %s in %s, line %s',
                    get_class($item),
                    $item->getMessage(),
                    $item->getFile(),
                    $item->getLine()
                );
            } elseif (is_object($item) && method_exists($item, '__debugInfo')) {
                // Convert objects into using __debugInfo.
                // $item = array_map($walker, $item->__debugInfo());
            }
            return $item;
        };

        array_walk_recursive($data, $walker);

        $variable = Hash::get($data, $path);

        $result = false;

        if ($variable) {
            array_walk_recursive($variable, function (&$item) {
                if(is_string($item)) {
                    $item = utf8_encode($item);
                }
            });

            $result = json_encode($variable, JSON_PRETTY_PRINT);
        }

        $this->set([
            '_serialize' => ['success', 'content'],
            'success' => $result !== false,
            'content' => $result
        ]);
    }
}
