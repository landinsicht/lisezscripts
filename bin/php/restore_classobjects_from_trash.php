<?php
/**
 * script to restore all objects from trash with a defined contetnclass.  
 * restores all node assignement. ( original parent nodes must exist ) 
 * @copyright land in sicht ag 2012
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version 0.1
 * @eZversion > 4.x
 */


require_once 'autoload.php';

// Variables
$userName = "[insert username here]";  // admin
$classID = '[insert contentClassID here]'; // 2
$limit = 100; 

//ez scripts
$cli = eZCLI::instance();

$cli->setUseStyles( true );
$script = eZScript::instance( array( 'description' => ( "restoring objects out of the glorious trash" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'debug-output' => false,
                                     'debug-message' => true,                               
) );
$script->startup();
$options = $script->getOptions( '[test][limit]','', array( 'test' => 'only test, no db manipulation', "limit" => "default limit is $limit Objects" ) );
$script->initialize();

if (empty($options['siteaccess'])) {
    $cli->error( 'No valid Siteaccess -> use -s siteaccessname' );
    $script->shutdown( 1, '-> Script shutdown' );
}

$isDebug = ($options['test'] != null ? true : false );
if ($isDebug == true )
{
    $cli->warning("Doing testdrive ... no DB manipulation");
}

$cli->warning ("current limit: $limit");

$db = eZDB::instance();
$user = eZUser::fetchByName($userName);
$userID = $user->id();

$user = eZUser::fetch($userID);
eZUser::setCurrentlyLoggedInUser($user, $userID);
$userObject = eZUser::currentUser();


$cli->output("fetching Objects for classID: $classID ");
// alle objekte
$conds = array(
    'contentclass_id' => $classID,
    'status' => eZContentObject::STATUS_ARCHIVED
    );

$objects = eZContentObject::fetchList(true,$conds);
$cli->output(count($objects)." Objekte");

$i = 0;
if (count($objects))
{
    foreach ($objects as $obj)
    {
        $i++;
        if ($i > $limit)
            continue;
        
        $cli->output("checking Object: " . $obj->ID."|". $obj->Name );
        $version = $obj->attribute( 'current' );
        $nodeAssignments =  $version->attribute( 'node_assignments' );
        $status = $obj->attribute( 'status' );
        $cli->output("status: $status");
        
        if (!count($nodeAssignments ))
        {
            $cli->output("no assignments. leaving object alone.");
            $i--;
        } 
        else
        {
            $cli->notice("nodes vorhanden");
            $cli->output("class:".$obj->attribute('contentclass_id'));
            if ($obj->attribute('contentclass_id') == $classID && count($nodeAssignments) >= 1 && $status == eZContentObject::STATUS_ARCHIVED)
            {
                foreach ($nodeAssignments as $assignment)
                {
                    $parentNodeID =  $assignment->attribute('parent_node');
                    $cli->output("parentNodeID: ". $parentNodeID);
                    if ($isDebug !== true )
                    {
                        $version->assignToNode( $parentNodeID, $assignment->attribute('is_main'));
                        $assignment->purge();
                    }
                }   
                  
                $obj->setAttribute( 'status', eZContentObject::STATUS_DRAFT );
                if ($isDebug !== true )
                {
                    $obj->store();
                }
                $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
                $version->setAttribute( 'user_id', $userID );
                $version->setAttribute( 'creator_id', $userID );
                if ($isDebug !== true )
                {
                    $version->store();
                }
                
                $obj->restoreObjectAttributes();
                if ($isDebug !== true )
                {
                    // rpeublishing object 
                    $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $obj->ID,
                                                                    'version' => $version->attribute( 'version' ) ), null, false );
                    //removing object from trash
                    eZContentObjectTrashNode::purgeForObject( $obj->ID  );
                }
            }
        }
            
    }
}
$cli->output(count($objects)." objects");
$cli->warning("thank you for using our script.");
$cli->error("Have a nice Day!");

$script->shutdown();