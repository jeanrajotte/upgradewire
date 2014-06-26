upgradewire
===========

A simple PHP program to manage "continuous integration" for ProcessWire sites.

0. [Caveat Emptor](#caveat)
1. [Overview](#overview)
 - [Yeah But](#yeah-but)
2. [How It Works](#how-it-works)
 - [An Example](#eg)
3. [API](#api)


## <a name='caveat'></a>Caveat Emptor

This is definitely a work in progress, only as complete as the functionality I have needed so far.  I intend to continue to grow it as I need. Of course, you're all welcome to race ahead. It hasn't been exercised quite much yet. 

_Note that the NN\_NN\_NN.php files in the github repo are not intrinsic to this app, they are just examples to help orient you._

There are forum threads on the issue of continuous integration at processwire. [This thread](https://processwire.com/talk/topic/2117-continuous-integration-of-field-and-template-changes/) demonstrates a lot of deep thinking about the importance of continuous integration. I hope the module(s) come to see the light of day.  [This thread](https://processwire.com/talk/topic/530-profile-export-module-also-upgrade-pw-20-to-21/) is another, more "all-at-once" approach to moving site configurations between installations. So far though, either the tools aren't ready or the conversation isn't going toward something that makes sense to me (see [Yeah But](#yeah-but)).

I first built **SchlepWire** (see [github](https://github.com/jeanrajotte/schlepwire)) which takes a whole site and packages it, files and data, into a single **zip** file and unpacks it on top of a new location.  This works for the 1st time or if you deploy w/o fear of completely overwriting the destination again and again. 

I use schlepwire to create a new site from an old one. And now I use **UpgradeWire** to reset the site to the nub I want and grow it from there, one documented step after another, in a "classic" source code management approach.

Also, as at this writing, I'm a newbie at PW. My understanding of the API and the intricasies of its metamodel is limited.  So my implementation of the configuration logic to create fields of diverse types, templates w/ bells and whistles, etc, is crude.  IOW, don't make fun of my source code, please :) 


## <a name='overview'></a>Overview 

ProcessWire (PW) is great! It's a lightweight CMS with a simple but powerful API and a Zen-ly clear metamodel. It's supported by a great community. And it looks like it's growing.

Like most CMS's, it keeps its structures and its contents in a database and provides an administrative backend to create and maintain both.  This is sweet and simple for a single installation of a site, being maintained by a single developer.  However, in a more complex application and development environment, possibly with several developers and/or with a development/staging/production deployment scheme, keeping the structure in the same medium as the content can be problematic.

My simple solution is to treat the structure as source code and be specific about versioning changes.  This enables one to version control the upgrade path, so more than one developer can work together usnig the usual source management tools. This helps not clobber your customer's data when upgrading the live site, where as a developer you're concerned with structure, not content. And it documents the data model of the application in text files, instead of having it buried in the PW administrative UI, requiring multiple point and click steps across the menu system and config pages.

The last point is an important one to me.  The administrative backend of any CMS makes complete sense to me for content creation, holding the hand of the site's editors/writers. PW has a very sweet tree metaphor that makes this task intuitively clear for the users.  However, mixing in there the development of the structure, at first blush, is useful, but to me not industrial-solid-like. It turns the backend into a kitchen sink, making all editing of the model a series of jumps between menu items and tab pages. Old school _moi_, I'd rather work from text files for the maintenance of the model, if the configuration language is neat enough. What I have here is far from neatness Nirvana -- it's more functional than configuration-style, but it reads well enough and it is easy to document.

### <a name="yeah-but"></a>Yeah But

Furthermore, I've a bit of a problem making every piece that works w/ PW a module. A module makes sense to enhance the front-end functionality, maybe even the backend functionality, but not for bootstrapping the site -- it seems too risky that you can't get started. Also, so far, the under-the-hood complexity of PW is somewhat above my pay grade. It makes more sense to me to have the bootstrapping stuff outside, simple, transparent, automatable w/ build tools, etc.

The **main pitch** is: This project is an early attempt at managing the PW structure (model) as source files.

## <a name='how-it-works'></a>How It Works 

Create a child folder of /site/ named as you please. I call mine "versions". In /site/versions, place the one **upgraderwire.php**, and add, over time, files named to sort alphabetically as the versions progress -- e.g. 00\_01\_00.php, 00\_01\_01.php, etc... The program includes, in sequence, all the files matching the regex pattern  **/^\d+\_\d+\_\d+\.php/**.  Each file is responsible to implement something like this:

	if ($upgrader->isVersion( 0, 1, 0)) {
		
		... do stuff to upgrade the templates and fields and pages if you please

		$upgrader->setVersion( 0, 1, 1);
	}

Using *curl* or a browser, invoke http://example.com/site/versions/upgradewire.php.

The first time it runs, it adds a "version" field to the "/" page template and initializes it to 0.0.0.  Thereafter, if one of the included files meets the <code>isVersion()</code> condition, its statements will modify the model. If <code>isVersion()</code> returns false, nothing happens and the program goes to the next file in the sequence.

The API is simple; it merely attempts to encapsulate repetitive configuration in a legible way.

### <a name="eg"></a>An Example

Here's an example of a configuration (version) file, named 00\_01\_00.php.  Its logic kicks in when the current version is 0.0.0.  It concludes by bumping the version up to 0.1.0:

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

Hopefully, the above reads like a configuration file that can use functionality when needed, instead of merely use a "language" to paint the structure (e.g. JSON, XML, or SQL).  I'm aiming for a sweet spot in the middle.

## <a name='api'></a>API 

Look at the [source](https://github.com/jeanrajotte/upgradewire/blob/master/upgradewire.php) to learn. I can just see this section getting stale quickly as I'll lag documenting the moving target... 


Enjoy!
