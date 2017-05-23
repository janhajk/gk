<?php

/**
 * Callback function to find all the inactive spenders
 *
 */
function gk_mark_inactive(){
   $sql = "SELECT nid FROM {node} WHERE type='%s'";
   $result = db_query($sql,'profile');
   $acitve = 0;
   $inactive = 0;
   while($row = db_fetch_object($result)){
      $node = node_load($row->nid);

      if(count($node->taxonomy) < 1){//this use belongs to no group -> inactive
         $dbObject = array(
            'field_profil_inaktiv_value' => 'Inaktiv',
            'nid'	=>	$node->nid,
         );
         drupal_write_record('content_type_profile',$dbObject,'nid');
         $inactive++;
      }
      else{
         $dbObject = array(
            'field_profil_inaktiv_value' => 'Aktiv',
            'nid'	=>	$node->nid,
         );
         drupal_write_record('content_type_profile',$dbObject,'nid');
         $acitve++;
      }
   }
   drupal_set_message($acitve . ' set to active. ' . $inactive . ' set to inactive');
   drupal_goto('gk/import');
}


function gk_menu() {
   $items['gk/print_serienbrief/%'] = array (
      'page callback' => 'gk_serienbrief_callback',
      'page arguments' => array (2),
      'type' => MENU_CALLBACK,
      'access arguments' => array ('send Serienbrief')
   );
   $items['gk/import'] = array(
      'page callback'	=>	'gk_import',
      'type'	=>	MENU_CALLBACK,
      'access arguments' =>	array(
         'send Serienbrief',
      ),
   );

   $items['gk/delete_all'] = array(
      'page callback'	=>	'gk_delete_all',
      'type' =>	MENU_CALLBACK,
      'access arguments' =>	array(
         'send Serienbrief',
      ),
      'page arguments' => array(2),
   );
   $items['gk/mark_inactive'] = array(
      'page callback'	=>	'gk_mark_inactive',
      'type'	=>	MENU_CALLBACK,
      'access arguments'	=>	array('send Serienbrief'),
   );
}
