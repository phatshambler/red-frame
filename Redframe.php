<?php

/*
 *
 * Red Frame
 *
 * 
 * Simple redis model abstraction class - in dev. Do NOT use for production. Learning redis project.
 * Requires a Predis\Client instance (Redis Client for PHP) in the constructor. Thank you nrk!
 * See: https://github.com/nrk/predis
 *
 * @author Nicolas Roy <nr@commun.ca>
 * @version 0.1
 *
 *
 */

abstract class RedFrame {
	
	/*
	 *
	 * Override these two - see samples folder for sample on how to configure your model
	 *
	 */

	protected $superStructure = array(
			'name' => 'names',
			'separator' => ':',
			'type' => 'set', //must be set for now
			'id' => 'numeric' //for v0.2
	);

	protected $infraStructure = array(

		'title' => array(
			'type' => 'string'
		),
		/*...*/
	);
	
	
	/*
	 *
	 * Basic stuff
	 *
	 */
	
	//The Redis Client Object
	protected $redis;

	//Name of the master key
	protected $name;

	//Item separator
	protected $separator;

	protected $_id;
	protected $_fetched;
	protected $_items;
	
	public function __construct($redis = null, $id = null) {
		
		if(isset($redis)) {
			$this->redis = $redis;
		}else{
			return false;
		}

		if(isset($id) && !empty($id)){
			$this->_id = $id;
		}

		$this->superStructure['next'] = $this->infraStructure;

		$this->name = $this->superStructure['name'];
		$this->separator = $this->superStructure['separator'];

	}

	/*
	 *
	 * Main getters
	 *
	 */

	public function getRedis(){
		return $this->redis;
	}

	public function getPath($path = '', $id = null, $safe = true){
		if(isset($this->_id) && $id == null){
			$string = $this->name.$this->separator.$this->_id;
		}
		else if(isset($id) && !empty($id)){
			$string = $this->name.$this->separator.$id;
		}

		if(!empty($string)){
			if(!empty($path)){
				if($safe){
					//to impl.
					$string .= $this->separator.$path;
				}else{
					$string .= $this->separator.$path;
				}
				
			}
			return $string;
		}else{
			return false;
		}
	}

	public function getTypeFromDb($path){
		return $this->redis->type($path);
	}

	public function getKeyValue($path){
		//to impl;
	}

	public function getItem($id = null){

		try{

			if($id == null && !empty($this->_id)){
				$id = $this->_id;
			}

			if(!isset($id) || empty($id)){
				return false;
			}
			
			$items = array();

			if($this->redis->exists($this->name.$this->separator.$id)){
				$path = $this->name.$this->separator.$id;
				
				foreach($this->infraStructure as $k => $i){
					$items[$k] = $this->recursiveGet($path.$this->separator.$k, $i);
				}
			}

			if(count($items) > 0){

				$this->_items = $items;
				return $items;
			}
			else{
				return false;
			}

		}catch(Exception $e){
			return false;
		}

	}

	public function getAllItems(){

		try{
			$items = array();

			if($this->redis->exists($this->name) && $this->redis->type($this->name) == 'set'){
				$set = $this->redis->smembers($this->name);

				foreach($set as $s){
					$items[] = $this->redis->getItem($s);
				}
			}

			if(count($items) > 0){
				return $items;
			}else{
				return false;
			}

		}catch (Exception $e){
			return false;
		}
	}

	protected function recursiveGet($path, $value){

		if(isset($value['next']) && is_array($value['next']) && !empty($value['next'])){
			$items = array();
			foreach($value['next'] as $k => $i){
				$items[$k] = $this->recursiveGet($path.$this->separator.$k, $i);
			}
			return $items;
			
		}else{
			return $this->getItemByType($path, $value['type']);
		}

	}


	public function getItemByType($path, $type){

		try{

			if($type == 'set'){
				return $this->redis->smembers($path);
			}
			else if($type == 'sortedset'){
				return $this->redis->zrange($path, 0, -1);
			}
			else if($type == 'string'){
				return $this->redis->get($path);
			}
			else if($type == 'hash'){
				return $this->redis->hgetall($path);
			}
			else if($type == 'list'){
				return $this->redis->lrange($path, 0, -1);
			}
			else{
				return false;
			}

		}catch(Exception $e){
			return false;
		}

	}


	/*
	 *
	 * Main setters
	 *
	 */

	public function setItem($value = array(), $id = null){

		try{

			if($id == null && !empty($this->_id)){
				$id = $this->_id;
			}

			if(!isset($id) || empty($id)){
				return false;
			}

			//superStructure type must be 'set' for this to work with getAllItems()
			$this->setValueByType($this->name, $this->superStructure['type'], $id);

			if(is_array($value)){
				$this->recursiveSet($this->infraStructure, $value, $this->name.$this->separator.$id);
				//So the main key for the item exists, i.e. keys:01
				$this->redis->set($this->name.$this->separator.$id, 1);
			}

			return true;

		}catch(Exception $e){
			return false;
		}
	}

	protected function recursiveSet($modelArray, $array, $path){

		foreach($array as $k => $v){

			if(array_key_exists($k, $modelArray)){

				if(isset($modelArray[$k]['next']) && is_array($modelArray[$k]['next']) && is_array($v) && !empty($v)){
					$this->recursiveSet($modelArray[$k]['next'], $v, $path.$this->separator.$k);
				}
				else{
					if(isset($modelArray[$k]['params']) && is_array($modelArray[$k]['params'])){
						$this->setValueByType($path.$this->separator.$k, $modelArray[$k]['type'], $v, $modelArray[$k]['params']);
					}else{
						$this->setValueByType($path.$this->separator.$k, $modelArray[$k]['type'], $v, null);
					}
				}
			}
		}

	}

	public function setValueByType($key, $type, $value, $params = null){

		try{
			//clear the key if present
			if(isset($params)){
				if(isset($params['autodel']) && $params['autodel']){
					$this->redis->del($key);
				}
			}

			if($type == 'set'){
				//can be array or not
				$this->redis->sadd($key, $value);
			}
			else if($type == 'sortedset'){
				//if multiple values for sorted set, scores = scores array, $value = value array
				if(is_array($value)){
					
					foreach($value['scores'] as $k => $v){
						$this->redis->zadd($key, $v, $value['values'][$k]);
					}
					
				}else{
					//default, score = 1
					$this->redis->zadd($key, 1, $value);
				}
			}
			else if($type == 'string'){
				$this->redis->set($key, $value);
			}
			else if($type == 'hash'){
				//filters the hash members with the provided list
				//value _must_ be associative array
				if(isset($params)){
					if(isset($params['members']) && is_array($params['members'])){
						foreach($value as $k => $v){
							if(!in_array($k, $params['members'])) unset($value[$k]);
						}
					}
				}
				$this->redis->hmset($key, $value);
			}
			else if($type == 'list'){
				//value car be array or item
				$this->redis->rpush($key, $value);
			}

			return true;

		}catch(Exception $e){
			return false;
		}

	}
}