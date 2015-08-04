<?php
namespace DebugKit\Traits;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

if (Configure::read('debug')) {
    trait DebugKitTrait
    {
        public $viewVarsBT = [];
        public $_fallbackedBT = [];

        public function set($name, $value = null)
        {
            $bt = debug_backtrace();

            $this->viewVarsBT[$name][] = [
                'file' => $bt[0]['file'],
                'line' => $bt[0]['line'],
                'function' => $bt[1]['function'],
            ];

            return parent::set($name, $value);
        }

        public function loadModel($modelClass = null, $type = 'Table')
        {
            $return = parent::loadModel($modelClass, $type);

            $generics = TableRegistry::genericInstances();

            if (isset($generics[$modelClass])) {
                $bt = debug_backtrace();

                $this->_fallbackedBT[$return->alias()][] = [
                    'file' => $bt[0]['file'],
                    'line' => $bt[0]['line'],
                    'function' => $bt[1]['function'],
                ];
            }

            return $return;
        }
    }
} else {
    trait DebugKitTrait
    {

    }
}