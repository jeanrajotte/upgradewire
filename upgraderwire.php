<?php

/*
*
* Simple version management system for processwire sites
*
* @version 0.1
*	initial checkin
*
* The MIT License (MIT)
* 
* Copyright (c) 2014 Jean Rajotte
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*/

// place this file in /site/versions/  or whatever name you choose.

// bootstrap pw
include('../../index.php');

class Upgrader {

	// doesn't do much so far
	public function __construct() {
		// $this->languageSupport = wire('modules')->get('LanguageSupport');
		// $default_field = $languageSupport->defaultLanguagePageID; 
	}

	//////////// utils
	public $debug = false;

	// echo msg if debug or if $always is provided
	public function log($msg, $always=false) {
		if ($this->debug || $always) {
			echo "$msg\n"; 
		}
	}

	// clean up a string to be a valid name
	protected function name( $name ) {
		return wire('sanitizer')->name( trim( $name )); 
	}

	///////////// version

	// returns the current version, by default as an array
	// where 0=major, 1=minor, 2=revision
	// or as a string.  The version is stored in the / page as a n.n.n string
	// the first time this is invoked and there's no version field in the root page,
	// the field is created and seeded with 0.0.0.
	public function getVersion( $asString = false ) {
		$home = wire('pages')->get('/');
		if ( !$home->version ) {
			// 1st time
			$this->addField( 'version', 'Text', 'Site Version');
			$this->addFieldsTo( $home, array( 'version'));
			$this->setVersion( 0, 0, 0);		// starting out
		}
		return $asString ? $home->version : explode( '.', $home->version );
	}

	// update the stored version
	// returns the new version as a n.n.n string
	public function setVersion( $major, $minor, $revision ) {
		$home = wire('pages')->get('/');
		$v = "{$major}.{$minor}.{$revision}";
		$home->version = $v;
		$home->save( 'version' );
		$this->log( "Set version to $v", true);
		return $v;
	}

	// returns a boolean after testing whether the provided version is the stored one.
	public function isVersion( $major, $minor, $revision ) {
		list( $maj, $min, $rev) = $this->getVersion();
		return ($maj==$major && $min==$minor && $rev==$revision);
	}

	///////// fields

	// find field if it exists.
	// if context present, it means it MUST exist, so throw up if it's not there
	// returns the field instance
	public function getField( $x, $context=false ) {
		if (is_object($x) && $x instanceof Field) {
			return $x;		
		} else {
			$this->log("getField: {$x}");
			$res = wire('fields')->get($x);
			if ($context && !$res) {
				throw new WireException("$context cannot locate field: $x"); 
			}
			return $res;
		}
	}

	// create field if it doesn't exist
	// modify it if it does
	// returns the field instance
	// the name, type, and label are mandatory
	// the other properties are optional, provided as an associative array
	public function addField( $name, $type, $label, $attrs = array()) {
		$name_clean = $this->name( $name );
		if (!$name_clean) { 
			throw new WireException( "addField needs a name: {$name}" );
		}
		$this->log( "addField: $name", true );
		// even if it's found, update its values
		$fld = $this->getField($name_clean);
		if ($fld) { 
			$this->log( "  exists as {$fld->type}" );
		} else {
			$fld = new Field();
			$fld->name = $name_clean;
		}
		$fld->type = $type;
		$fld->label = $label;
		$fld->save();
		// additional attributes
		foreach($attrs as $key => $val) {
			$fld->$key = $val;
			$this->log("  $key = $val");
		}
		$fld->save();
		$this->log("  saved");
		return $fld;		
	}


	// add repeater field and return the new instance
	// this creates the field and its template and its page (a funny network)
	// name and label required
	// list of field names to include 
	// optionally, additional properties to the repeater field
	public function addRepeaterField( $name, $label, $fields, $attrs=array()) {
		$name = $this->name( $name );
		$f_name = "repeater_$name";
		$this->log( "addRepeaterField: $name" , true);
		$fields = array_map( function( $x ) {
				return upgrader()->getField( $x, 'addRepeaterField');
			}, $fields);
		$t = $this->addFieldsTo(  $this->addTemplate( $f_name, array(
				"noChildren" => 1,
				"noParents" => 1,
				"slashUrls" => 1,
				"noGlobal" => 1)
			),
			array_map( function($f){ return $f->name; }, $fields));
		$f = $this->addField( $name, 'Repeater', $label, array_merge(
			array(
				"template_id" => $t->id,
				"repeaterFields" => array_map( function($f){ return $f->id; }, $fields),
			), $attrs)
		);
		$t_admin = wire('templates')->get('name=admin');
		$p_repeaters = wire('pages')->get('name=repeaters');
		$p = $this->addPage($p_repeaters->path, "for-field-{$f->id}", $t_admin->name );
		return $this->addField( $name, 'Repeater', $label, array(
			"parent_id" => $p->id,
			)
		);
	}

	// add a textarea wired with TinyMCE, just like so
	// returns the new instance
	// optinal properties
	public function addRichTextField( $name, $type, $label, $attrs=array()) {
		return $this->addField($name, $type, $label, array_merge(
			array(
				"inputfieldClass" => "InputfieldTinyMCE",
				"contentType" => 1,	// html
				"pageLinkAbstractor" => 2,
				"rows" => 5,
			), $attrs)
		);
	}

	// bulk deletion of fields
	// returns nothing
	public function deleteFields( $names ) {
		foreach( $names as $name) {
			$fld = wire('fields')->get( $this->name( $name ));
			if ($fld && $fld->numFieldgroups()===0) {
				$this->log( "deleting field: {$fld}", true);
				wire('fields')->delete( $fld );
			}
		}
	}


	////////// templates

	// find template by name and return the instance
	// return false if not there
	// throw up if context supplied 
	public function getTemplate( $x, $context = false) {
		if (is_object($x) && $x instanceof Template) {
			return $x;		
		} else {
			$this->log("getTemplate: {$x}");
			$res = wire('templates')->get($x);
			if ($context && !$res) {
				throw new WireException("$context cannot locate template: $x"); 
			}
			return $res;
		}
	}

	// add fields to template and return the template instance.
	// $obj is the target, given as a page instance, a template instance, or a template name
	// $fields is an array of fields NAMES. Every field must exist.
	// for more complex situation, if a field is given as a closure (a function) 
	// instead of a field name, then the function is run with the template as a single argument
	public function addFieldsTo( $obj, $fields) {
		if (is_object($obj) && $obj instanceof Page) {
			$template = $obj->template;
		} elseif (is_object($obj) && $obj instanceof Template) {
			$template = $obj;
		} else {
			$template = wire('templates')->get( $this->name( $obj));
		}
		if (!$template) {
			throw new WireException("addFieldsTo cannot locate template: $obj");
		}
		$this->log( "addFieldsTo: {$template->name}", true);
		foreach( $fields as $f) {
			if (is_object($f) && $f instanceof Closure ) {
				$f( $template );
				continue;
			}
			$name = $this->name( $f );
			$fld = $this->getField( $name, 'upgrader::addFieldsTo');
			if (! $template->hasField( $fld )) {
				$template->fields->add($fld);
			}
		}
		// are both these necessaray?
		$template->fields->save();
		$template->save();
		return $template;
	}

	// create an empty template and its unique fieldgroup
	// optionally with additional properties
	// return the new instance
	public function addTemplate( $name, $attrs=array()) {
		$t_name = $this->name( $name );
		$o = wire('templates')->get($t_name);
		if (!$o) {
			$this->log( "Create template: {$name}", true);
			$fieldgroup = new Fieldgroup();
			$fieldgroup->name = $t_name;
			$fieldgroup->save();
			$o = new Template();
			$o->name = $t_name; // must be same name as the fieldgroup
			$o->fieldgroup = $fieldgroup;
			$o->save(); 
		} 
		// there might be attributes to add to the template
		if (count($attrs)) {
			$this->log( "Update template Attributes: {$name}");
			foreach($attrs as $key => $val) {
				$o->set( $key, $val);
			}
			$o->save(); 
		}
		return $o; 
	}

	// modify the templates in a chain to define who can be parent of
	// who and child of who
	// arg: array of template names
	// this function only deals with a single child or parent.
	// use addTemplateParents or addTemplateChildren to do more 
	public function setParentage( $lineage ) {
		$this->log("set parentage: " . implode(',', $lineage), true);
		$parent = $this->getTemplate( array_shift( $lineage ), 'setParentage');
		foreach($lineage as $name) {
			$this->log("  for {$name}");
			$current = $this->getTemplate( $name, 'setParentage');
			$this->addChildTemplates( $parent, array( $current));
			$this->addParentTemplates( $current, array( $parent));
			wire('templates')->save( $parent );
			$parent = $current;
		}
		$current->noChildren = 1;
		$current->childTemplates = array();
		wire('templates')->save( $current );
		return $this;
	}

	// add a list of child templates to a parent template
	public function addChildTemplates( $target, $items ) {
		$o = $this->getTemplate( $target, 'addChildTemplates');
		$o->noChildren = 0;
		$arr = $o->childTemplates;
		foreach( $items as $item) {
			$x = $this->getTemplate( $item, 'addChildTemplates');
			$id = $x->id;
			if (in_array($id, $arr) === false) { $arr[] = $id; }
		}
		$o->childTemplates = $arr;
		$o->save();
		return $o;
	}

	// add a list of parent templates to a child template
	public function addParentTemplates( $target, $items ) {
		$o = $this->getTemplate( $target, 'addParentTemplates');
		$o->noParents = 0;
		$arr = $o->parentTemplates;
		foreach( $items as $item) {
			$x = $this->getTemplate( $item, 'addParentTemplates');
			$id = $x->id;
			if (in_array($id, $arr) === false) { $arr[] = $id; }
		}
		$o->parentTemplates = $arr;
		$o->save();
		return $o;
	}


	// create a new page, using the template given
	// returns the page instance
	// $path is the parent's path
	// $name is a string
	// $template is an template instance
	// values is an associative array for optional values
	// some values might be functions (closures) to be executed in this context. 
	// the function expects the current page as a sole argument.
	public function addPage( $path, $name, $template, $values=array()) {
		$path = trim($path);
		$parent = wire('pages')->get( $path );
		if (!$parent->id) {
			throw new WireException("addPage cannot locate parent: $path");
		}
		$p = $parent->get('name=' . $this->name( $name) );
		if (!$p->id) {
			$p = new Page();
			$p->name = $name;
			$p->parent = $parent;
			$p->template = $template;
			$p->save();
		}
		$this->log( "Adding page: $path  $name", true);
		foreach( $values as $key => $val) {
			if (is_object($val) && $val instanceof Closure ) {
				$val( $p );
			} else {
				$p->{$key} = $val;
			}
		}
		$p->save();
		return $p;
	}

	//////////// Access

	// create new permission or find an existing one
	// update the title
	// return the permission instance
	public function addPermission( $name, $title) {
		$name = $this->name($name);
		$o = wire('permissions')->get($name);
		if (!$o) {
			$this->log( "Adding permission: $name" );
			$o = new Permission();
			$o->name = $name;
		} else {
			$this->log( "Updating permission: $name" );
		}
		$o->title = $title;
		$o->save();
		return $o;
	} 

	// create new role or find an existing one by name
	// return the role instance
	// optionally provide a list of permissions
	public function addRole( $name, $perms=array()) {
		$name = $this->name( $name); 
		$o = wire('roles')->get( $name );
		if (!$o->id) {
			$o = new Role();
			$o->name = $name;
			$this->log( "Adding role: $name" );
			$o->save();
		} else {
			$this->log( "Updating role: $name" );
		}
		foreach($perms as $perm) {
			$o->addPermission( $this->name( $perm ));
			$this->log( "  with permission: $perm" );
		}
		$o->save();
		return $o;
	} 

	/////////// start from scratch

	// brutally remove pages, templates, and fields that are not 
	// with the templates listed herein, and those additionally provided 
	// in the argument
	public function reset( $extraExcluded = array()) {
		// remove all pages but those that use the except templates
		// work the page tree from home
		// don't touch the admin branch
		$templatesExcluded = array_merge(
			array( 
				'admin', 
				'basic', 
				'basic-page', 
				'login', 
				'login-page', 
				'home', 
				'language', 
				'permission', 
				'role', 
				'user'),
			$extraExcluded );
		$excluded = implode('|', array_map( 'trim', $templatesExcluded));
		foreach(wire('pages')->get('/')->children("include=all,template.name!={$excluded}") as $p) {
			wire('pages')->delete( $p, true );
			$this->log( "deleted page: {$p->name} and descendants...", true);
		}
		
		// templates
		foreach(wire('templates')->find("name!={$excluded}") as $o) {
			$this->log( "deleting template {$o->name}", true);
			if ($o->flags & Template::flagSystem) {
				$this->log( "Skip SYSTEM: {$o->name}");
				continue;
			}
			$fg = $o->fieldgroup;
			wire('templates')->delete($o);
			$this->log( "deleting fieldgroup {$o->name}");
			wire('fieldgroups')->___delete( $fg );
		}

		// fields
		foreach(wire('fields') as $fld) {
			if ($fld->numFieldgroups()===0) {
				$this->log( "deleting field: {$fld}", true);
				wire('fields')->delete( $fld );
			}
		}
	}


}

// create the instance to be used
$upgrader = new Upgrader;

// make it available in all contexts
function upgrader() {
	global $upgrader;
	return $upgrader;
}

// $upgrader->debug=true;

// start logging
echo "<pre>\n";
$upgrader->log( "Current version: {$upgrader->getVersion( true )}.", true);

// include all the files that match the pattern in the current directory

$fnames = glob(dirname(__FILE__) . DIRECTORY_SEPARATOR . '*.php');
sort($fnames);
foreach($fnames as $fname) {
	if (preg_match( '/^\d+_\d+_\d+\.php/', basename( $fname ))) {
		$upgrader->log( "LOADING: {$fname}" );
		include( $fname );
	}
}

// done!

$upgrader->log( "Final version: {$upgrader->getVersion( true )}.", true);
echo "</pre>\n";


