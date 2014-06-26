<?php

// uncomment this to reset the app and run through the whole upgrading cascade from scratch

// $upgrader->setVersion( 0,0,0 );

if ($upgrader->isVersion( 0, 0, 0)) {

	// make model and data barebone.
	// i.e. remove all pages, templates, and fields that do not use the templates
	// named here, in addition to the admin templates defined in the reset method.
	// this assumes you're starting from a copy of another site.
	
	$upgrader->reset( array('js', 'menus', 'menu', 'repeater_menu_items', 'contact') );

	// define fields we know and love.
	// read up on the signature of each method in the Upgrader class.
	// the idea is to keep this piece lean and clean.

	$upgrader->addField( 'featured', 	'checkbox', 	'Featured?');

	// TODO: init other language labels also where applicable.

	$upgrader->addField( 'directions', 	'textareaLanguage', 	'Directions');
	$upgrader->addField( 'summary', 	'textareaLanguage', 	'Summary' );
	$upgrader->addField( 'body', 		'textareaLanguage', 	'Detailed Description');
	$upgrader->addField( 'images', 		'image', 				'Images' );
	$upgrader->addField( 'capacity', 	'integer', 				'Room Capacity');
	$upgrader->addField( 'phone',		'text',					'Telephone Number' );
	$upgrader->addField( 'rating', 		'integer', 				'Rating 1-5');
	
	// note how this fancier fieldtype has extra properties in an assoc array.

	$upgrader->addField( 'map',			'MapMarker',			'Address and Map', array(
			"defaultAddr" => "Toronto, Ontario",
			"defaultType" => "HYBRID",
			"defaultLat" => "43.653226",
			"defaultLng" => "-79.3831843",
		));

	// again with the more explicit configuration.

	$upgrader->addField( 'tags', 'FieldtypePage', 'Tags', array(
			'findPagesSelector' => 'template=tag',
			"labelFieldName" => "title",
			"inputfield" => "InputfieldAsmSelect",
		));

	// create templates with fields
	
	// this creates the "cities" template and adds to it fields that were created above.

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

	// link template family.

	// this is neat: create the hierarchies of templates that control the 
	// shape of the page trees.

	$upgrader->setParentage( array( 'home', 'cities', 'city', 'spaces', 'space'));
	$upgrader->setParentage( array( 'home', 'tags', 'tag'));

	// alternatively, you could do this, useful when a parent allows for more than 
	// one child template type, or vice versa:

	$upgrader->setChildTemplates( 'home', array( 'cities' /* , and more when applicable */ ));
	$upgrader->setParentTemplates( 'cities', array( 'home' /* , and more when applicable */ ));

	// add root page for cities.

	$upgrader->addPage( '/', 'cities', 'cities', array(
			'title' => 'Cities',
		));
	
	// add root page for tags (note the use of a function to set the sortfield value).

	$upgrader->addPage( '/', 'tags', 'tags', array(
			'title' => 'Tags',
			'_' => function($p) { $p->sortfield = upgrader()->getField('title', 'sortfield-assignment'); },
		));

	// install our initial city.  Again with the function to initialize a field.

	$upgrader->addPage( '/cities', 'toronto', 'city', array(
			'title' => 'Toronto',
			'_' => function( $p ) {
					$p->map->address = 'Toronto, Ontario';
					$p->map->geocode(); 
				},
		));

	// add root page for toronto spaces.

	$upgrader->addPage( '/cities/toronto', 'spaces', 'spaces', array(
			'title' => 'Spaces in Toronto',
		));

	$upgrader->setVersion( 0, 1, 0);
}