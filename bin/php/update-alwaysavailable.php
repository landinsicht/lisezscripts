<?php
/**
 *  Updates all choosen objects ( by Class) to always_available true|false
 *  feel free to make changes as you need.
 */
set_time_limit (0);
ini_set('memory_limit', '1000M');
require 'autoload.php';
/*
 * enter siteaccess name
 */
$siteAccess = "";
/*
 * enter classes to fetch
 */
$classFilter = array();
/*
 *  set the new status
 */
$alwaysAvailable = false;

/************************************/

$script = eZScript::instance( array( 'description' => ( "updateing always_available status" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'debug-output' => true,
                                     'debug-message' => true,
                                     'site-access' => $siteAccess ) );
                                     
$script->startup();
$script->initialize();


$cli = eZCLI::instance();
$db = eZDB::instance();

$cli->output( 'Start ----------------------------------->' );

$nodes = eZContentObjectTreeNode::subTreeByNodeID(
						    array(
						        'ClassFilterType' => 'include',
						        'ClassFilterArray' => $classFilter,
						        'SortBy' => array('name', true)
						    ), 2);

$cli->output( 'Count: ' . count($nodes) );

foreach ($nodes as $c => $node) {
        $objectID = $node->attribute("contentobject_id");
        $cli->output("updating ". $node->attribute('node_id')." |  $objectID ". $node->Name);
		$status = updateAlwaysAvailable($objectID, $alwaysAvailable);

}
								    
	


$cli->output( 'Stop <-----------------------------------' );

$script->shutdown();

function updateAlwaysAvailable( $objectID, $newAlwaysAvailable )
{
	$object = eZContentObject::fetch( $objectID );
	$change = false;

	if ( $object->isAlwaysAvailable() & $newAlwaysAvailable == false )
	{
		$object->setAlwaysAvailableLanguageID( false );
		$change = true;
	}
	else if ( !$object->isAlwaysAvailable() & $newAlwaysAvailable == true )
	{
		$object->setAlwaysAvailableLanguageID( $object->attribute( 'initial_language_id' ) );
		$change = true;
	}
	if ( $change )
	{
		eZContentCacheManager::clearContentCacheIfNeeded( $objectID );
		if ( !eZSearch::getEngine() instanceof eZSearchEngine )
		{
			eZContentOperationCollection::registerSearchObject( $objectID );
		}
	}

	return array( 'status' => true );
}

?>
