<?php

//Sample class for RedFrame

class Article extends RedFrame {

	public $superStructure = array(
			'name' => 'articles', //name of the master key, ex. items, articles, animals...
			'separator' => ':', //item separator
			'type' => 'set', //To be able to retrieve the set of all the added items
			'id' => 'numeric'
	);

	public $infraStructure = array(

		'title' => array(
			'type' => 'string'
		),
		'meta' => array(
			'type' => 'set',
			'next' => array( //next acts like a hash but can contain any other type of data
				'author' => array(
					'type' => 'string'
				),
				'dateadded' => array(
					'type' => 'string'
				),
				'datemodified' => array(
					'type' => 'list'
				)
			)
		),
		'tags' => array(
			'type' => 'set'
		),
		'hashed_thing' => array(
			'type' => 'hash',
			'params' => array('members' => array('name', 'profession', 'friend')), //list of the allowed members of the hash
		),
		'sorted_thing' => array(
			'type' => 'sortedset' //two arrays _needed_, of the same length, the scores and their associated values
		),
		'list_thing' => array(
			'type' => 'list',
			'params' => array('autodel' => true) //key flushes itself first if autodel is true
		)

	);

}