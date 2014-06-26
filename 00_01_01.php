<?php

/*

	define owner user role and add lookup to the space template
	extend the user template

*/


if ($upgrader->isVersion( 0, 1, 0)) {

	// misc. user fields
	$upgrader->addField( 'pretty_name', 'text', 'Friendly Name' );

	// define owner role
	$role = $upgrader->addRole( 'space-owner' );

	$upgrader->addField( 'space_owner', 'page', 'Owner', array(
			'findPagesSelector' => "template=user, roles={$role->id}, check_access=0",
			"labelFieldName" => "pretty_name",
			"derefAsPage" => 1,
			"inputfield" => "InputfieldSelect",
			'required' => 0,
		));

	// extend space to have an owner
	$upgrader->addFieldsTo( 'space', array(
			'space_owner',
		));

	// extend user profile template
	$upgrader->addFieldsTo( 'user',
		array( 
			'pretty_name', 
			'phone', 
			'is_owner', 
			'rating'));


	$upgrader->setVersion( 0, 1, 1);
}
