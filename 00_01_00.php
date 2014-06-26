<?php

// $upgrader->setVersion( 0,0,0 );

if ($upgrader->isVersion( 0, 0, 0)) {

	// make model and data barebone
	$upgrader->reset( array('js', 'menus', 'menu', 'repeater_menu_items', 'contact') );

	// defined fields we know and love
	$upgrader->addField( 'featured', 	'checkbox', 'Featured?');
	$upgrader->addField( 'directions', 	'textareaLanguage', 	'Directions' );
	$upgrader->addField( 'summary', 	'textareaLanguage', 	'Summary' );
	$upgrader->addField( 'body', 		'textareaLanguage', 	'Detailed Description');
	$upgrader->addField( 'images', 		'image', 				'Images' );
	$upgrader->addField( 'capacity', 	'integer', 				'Room Capacity');
	$upgrader->addField( 'phone',		'text',					'Telephone Number' );
	$upgrader->addField( 'rating', 		'integer', 				'Rating 1-5');
	$upgrader->addField( 'map',			'MapMarker',			'Address and Map', array(
			"defaultAddr" => "Toronto, Ontario",
			"defaultType" => "HYBRID",
			"defaultLat" => "43.653226",
			"defaultLng" => "-79.3831843",
		));

	$upgrader->addField( 'tags', 'FieldtypePage', 'Tags', array(
			'findPagesSelector' => 'template=tag',
			"labelFieldName" => "title",
			"inputfield" => "InputfieldAsmSelect",
		));

	// create templates with fields
	$upgrader->addFieldsTo( $upgrader->addTemplate( 'cities'),
		array( 'title', 'map'));

	$upgrader->addFieldsTo( $upgrader->addTemplate( 'city'),
		array( 'title', 'map'));

	$upgrader->addFieldsTo( $upgrader->addTemplate( 'spaces'),
		array( 'title'));

	$upgrader->addFieldsTo( $upgrader->addTemplate( 'space'),
		array( 'title', 
			'map', 
			'phone',		// a space has a phone distinct from the owner's
			'featured', 
			'tags', 
			'summary', 'body', 'directions', 'capacity', 'images',
			'rating'
			));

	$upgrader->addFieldsTo( $upgrader->addTemplate( 'tags'),
		array( 'title'));

	$upgrader->addFieldsTo( $upgrader->addTemplate( 'tag'),
		array( 'title' ));

	// link template family
	$upgrader->setParentage( array( 'home', 'cities', 'city', 'spaces', 'space'));
	$upgrader->setParentage( array( 'home', 'tags', 'tag'));

	// add root page for cities
	$upgrader->addPage( '/', 'cities', 'cities', array(
			'title' => 'Cities',
		));
	
	$upgrader->addPage( '/', 'tags', 'tags', array(
			'title' => 'Tags',
			'_' => function($p) { $p->sortfield = upgrader()->getField('title', 'sortfield-assignment'); },
		));

	// try out our initial city
	$upgrader->addPage( '/cities', 'toronto', 'city', array(
			'title' => 'Toronto',
			'_' => function( $p ) {
					$p->map->address = 'Toronto, Ontario';
					$p->map->geocode(); 
				},
		));

	// add root page for toronto spaces
	$upgrader->addPage( '/cities/toronto', 'spaces', 'spaces', array(
			'title' => 'Spaces in Toronto',
		));

	$upgrader->setVersion( 0, 1, 0);
}
