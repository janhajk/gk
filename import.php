<?php

/**
 callback function for import
 */
function gk_import(){
	$out = drupal_get_form('gk_import_form');
	$out .= l('Inaktive Spender markieren','gk/mark_inactive') . ' Alle Spender, welche keiner Gruppe angeh&ouml;ren auf inaktiv setzen<br/><br/>';
	$out .= l('Delete spenden!!!','gk/delete_all/spende') . ' Be aware!!!! no confirmation needed. <br/><br/>';
	$out .= l('Delete ALL profiles!!!','gk/delete_all/profile') . ' Be aware!!!! no confirmation needed. <br/><br/>';
	$out .= l('Delete ALL nodes!!!','gk/delete_all') . ' Be aware!!!! no confirmation needed. <br/><br/>';
	return $out;
}

/**
 form definition for the import form
 */
function gk_import_form(){
	$form['help'] = array(
'#value' => 'Import erfolgt in der folgenden Reihenfolge: Zuerst die Mitglieder und die Spenden importieren. Im zweiten Schritt kommt dann die Gruppenzuordnung noch dazu. Zum Schluss m&uuml;ssen noch die inaktiven Mitglieder markiert werden. That\'s it.',
	);

	$form['file'] = array(
		'#type'	=>	'file',
		'#title'	=>	t('XML File'),
	);

	$form['type'] = array(
		'#type'	=>	'select',
		'#title'	=>	t('Type'),
		'#options'	=>	array(0=>'Mitglieder',1=>'Gruppenzuordnung',2=>'Mitglieder ohne Spende'),
	);

	$form['submit'] = array(
		'#type'	=>	'submit',
		'#value' =>	t('Import'),
	);
	$form['#attributes'] = array('enctype' => "multipart/form-data");
	return $form;
}

/**
 Import function - Callback when the form was submitted
 */
function gk_import_form_submit($form,&$form_state){
	ini_set("memory_limit","64M");
	switch ($form_state['values']['type']){
		case 0:
			_gk_import_mitglieder($form,$form_state);
			break;
		case 1:
			_gk_import_gruppenzuordnung($form,$form_state);
			break;
		case 2:
			_gk_import_member_only($form,$form_state);
	}

}

function _gk_import_member_only($form,&$form_state){
	global $user;
	$filename = $_FILES['files']['tmp_name']['file'];
	$xml = simplexml_load_file($filename);
	$anz_profiles=0;
	foreach($xml AS $item){
	if($anz_profiles > 2){
	//	return $anz_profiles;
	}
			//[field_profil_firma-raw]-[field_profil_nachname-raw]-[field_profil_vorname-raw]
			$node_p = null;
			$node_p->title = $item->Firma.' '.$item->Nachname.' '.$item->Vorname;
			$node_p->type = 'profile';
			$node_p->language = 'de';
			$node_p->uid = $user->uid;

			node_save($node_p);
			$nid_profile = db_last_insert_id('node','nid');

			$dbObjectProfile['field_profil_vorname_value'] = trim($item->Vorname);
			$dbObjectProfile['field_profil_nachname_value'] = trim($item->Nachname);

			$dbObjectProfile['field_profil_strasse_value'] = trim($item->Strasse);
			$dbObjectProfile['field_profil_zusatz_value'] = trim($item->Adresse2);
			$dbObjectProfile['field_profil_ort_value'] = trim($item->Ort);
			$dbObjectProfile['field_profil_plz_value'] = trim($item->PLZ);
			$dbObjectProfile['field_profil_anredespezial_value'] = trim($item->Anrede);
			$briefanrede = trim($item->Briefanrede);
			
			switch($briefanrede){
				case 'Sehr geehrter Herr':
					$briefanrede = 'Sehr geehrter Herr %nachname%';
					break;
				case 'Sehr geehrte Frau':
					$briefanrede = 'Sehr geehrte Frau %nachname%';
					break;
				case 'Lieber':
					$briefanrede = 'Lieber %vorname%';
					break;
				case 'Liebe':
					$briefanrede = 'Liebe %vorname%';
					break;
				case 'Sehr geehrte Frau, sehr geehrter Herr':
					$briefanrede = 'Sehr geehrte Frau %nachname%, sehr geehrter Herr %nachname%';
					break;
				case 'Sehr geehrte Familie':
					$briefanrede = 'Sehr geehrte Familie %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
			}
			
			$dbObjectProfile['field_profil_briefanredespez_value'] = $briefanrede;
			$dbObjectProfile['field_profil_telp_value'] = trim($item->Tel_P);
			$dbObjectProfile['field_profil_telg_value'] = trim($item->Tel_G);
			$verdanken = 'Monatsverdankung';
			if($item->Verdanken ==1){
				$verdanken = 'Monatsverdankung';
			}elseif($item->Verdanken == 2){
				$verdanken = 'Jahresverdankung';
			}elseif($item->Verdanken == 3){
				$verdanken = 'keine';
			}
			$dbObjectProfile['field_profil_verdankung_value'] = $verdanken;
			$dbObjectProfile['field_profil_handy_value'] = trim($item->Natel);
			$dbObjectProfile['field_profil_faxg_value'] = trim($item->Fax);
			//$dbObjectProfile['field_profil_geburtstag_value'] = $item->Geburtstag;
			$dbObjectProfile['field_profil_eintritt_value'] = trim($item->M_Eintritt);
			$dbObjectProfile['field_profil_imjahresbericht_value'] = ''; 
			$dbObjectProfile['field_profil_memo_value'] = trim($item->Adresse.Memo);
			$dbObjectProfile['field_profil_firma_value'] = trim($item->Firma);
			$dbObjectProfile['nid'] = $nid_profile; //key
			$dbObjectProfile['vid'] = $nid_profile;
			$dbObjectProfile['field_profile_adresse_id_value'] = $item->Adresse_ID;

				//print_r($dbObjectProfile);
				drupal_write_record('content_type_profile',$dbObjectProfile,'vid');
				$gruppe = $item->M_Bezeichnung;
				_gk_import_write_gruppen($gruppe,$nid_profile,$nid_profile);
				$anz_profiles++;
	}
	drupal_set_message($anz_profiles. " Profil ohne Spenden importiert");
}

function _gk_import_write_gruppen($gruppe,$nid,$vid){
	
	$new_gruppe = '';
			switch ($gruppe) {
				case 'Sonstige':
					$new_gruppe = 'sonstiges';
					break;
				case 'Sozial':
					$new_gruppe = 'sozial';
					break;
				case 'Mitglied':
					$new_gruppe = 'normal';
					break;
				case 'christkatholisch':
					$new_gruppe = 'katholisch';
					break;
				case 'Spender':
					$new_gruppe = 'privat';
					break;
				case 'Freiwillige aktiv':
					$new_gruppe = 'freiwillig aktiv';
					break;
				case 'Freiwillige passiv':
					$new_gruppe = 'freiwillig passiv';
					break;
				case 'Teammitglieder':
					$new_gruppe = 'fest';
					break;
				case 'Vorstandsmitglieder':
					$new_gruppe = 'Vorstand';
					break;
				case 'Anonym/Barspenden':
					$new_gruppe = 'anonym/bar';
					break;
				case 'Praktikanten':
					$new_gruppe = 'Praktikant';
					break;
				default:
					$new_gruppe = $gruppe;
					break;
			}
			
			if($gruppe == ''){
				$new_gruppe = 'keine';
			}

			$gruppe = '';
			

			$term_row = db_fetch_object(db_query("SELECT tid FROM {term_data} WHERE name = '%s' LIMIT 0,1",$new_gruppe));

			$dbObject = array(
				'nid'	=>	$nid,
				'vid'	=>	$vid,
				'tid'	=>	$term_row->tid,
			);
			drupal_write_record('term_node',$dbObject);
}

function _gk_import_gruppenzuordnung($form,&$form_state){
	$filename = $_FILES['files']['tmp_name']['file'];
	$xml = simplexml_load_file($filename);

	$i = 0;
	foreach($xml AS $item){
		$dbObject = array();
		$adress_id = $item->adresse_id;
		$row = db_fetch_object(db_query("SELECT nid,vid FROM {content_type_profile} " .
										"WHERE field_profile_adresse_id_value=%d LIMIT 0,1",$adress_id));
		if(!empty($row)){
			$gruppe = $item->M_Bezeichnung;
			_gk_import_write_gruppen($gruppe,$row->nid,$row->vid);
			$i++;
		}
	}
	drupal_set_message("$i Gruppen importiert.");
}

/**
 * Importing the xml for members with there spenden
 *
 * @param array $form
 * @param array $form_state
 */
function _gk_import_mitglieder($form,&$form_state){
	global $user;
	$filename = $_FILES['files']['tmp_name']['file'];

	/*if (!($fp = fopen($filename, "r"))) {
	 die("cannot open ".$filename);
	 }*/

	$xml = simplexml_load_file($filename);

	$ar_already_in_db = array();
	$ar_profile_nid = array();

	//print '<pre>';
	$anz_profiles = 0;
	$anz_spenden = 0;
	foreach($xml AS $item){
		$dbObject = array();

		// make a node for the spende
		$node = null;
		//print_r($item->Nachname);
		$title = $item->Firma .'-'.trim($item->Nachname) . '-' . $item->Betrag;
		$node->title = $title;
		//		print $node->title.'<br/>';
		$node->type = 'spende';
		$node->language = 'de';
		$node->uid = $user->uid;
		node_save($node);
		$nid_spende = db_last_insert_id('node','nid');

		if(!in_array($item->Adresse_ID,$ar_already_in_db)){
			//[field_profil_firma-raw]-[field_profil_nachname-raw]-[field_profil_vorname-raw]
			$node_p = null;
			$node_p->title = $item->Firma.' '.$item->Nachname.' '.$item->Vorname;
			$node_p->type = 'profile';
			$node_p->language = 'de';
			$node_p->uid = $user->uid;

			node_save($node_p);
			$nid_profile = db_last_insert_id('node','nid');

			$dbObjectProfile['field_profil_vorname_value'] = trim($item->Vorname);
			$dbObjectProfile['field_profil_nachname_value'] = trim($item->Nachname);

			$dbObjectProfile['field_profil_strasse_value'] = trim($item->Strasse);
			$dbObjectProfile['field_profil_zusatz_value'] = trim($item->Adresse2);
			$dbObjectProfile['field_profil_ort_value'] = trim($item->Ort);
			$dbObjectProfile['field_profil_plz_value'] = trim($item->PLZ);
			$dbObjectProfile['field_profil_anredespezial_value'] = trim($item->Anrede);
			$briefanrede = trim($item->Briefanrede);
			
			switch($briefanrede){
				case 'Sehr geehrter Herr':
					$briefanrede = 'Sehr geehrter Herr %nachname%';
					break;
				case 'Sehr geehrte Frau':
					$briefanrede = 'Sehr geehrte Frau %nachname%';
					break;
				case 'Lieber':
					$briefanrede = 'Lieber %vorname%';
					break;
				case 'Liebe':
					$briefanrede = 'Liebe %vorname%';
					break;
				case 'Sehr geehrte Frau, sehr geehrter Herr':
					$briefanrede = 'Sehr geehrte Frau %nachname%, sehr geehrter Herr %nachname%';
					break;
				case 'Sehr geehrte Familie':
					$briefanrede = 'Sehr geehrte Familie %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
				case 'Sehr geehrte Frau Dr.':
					$briefanrede = 'Sehr geehrte Frau Dr. %nachname%';
					break;
			}
			
			$dbObjectProfile['field_profil_briefanredespez_value'] = $briefanrede;
			$dbObjectProfile['field_profil_telp_value'] = trim($item->Tel_P);
			$dbObjectProfile['field_profil_telg_value'] = trim($item->Tel_G);
			$verdanken = 'Monatsverdankung';
			if($item->Verdanken ==1){
				$verdanken = 'Monatsverdankung';
			}elseif($item->Verdanken == 2){
				$verdanken = 'Jahresverdankung';
			}elseif($item->Verdanken == 3){
				$verdanken = 'keine';
			}
			$dbObjectProfile['field_profil_verdankung_value'] = $verdanken;
			$dbObjectProfile['field_profil_handy_value'] = trim($item->Natel);
			$dbObjectProfile['field_profil_faxg_value'] = trim($item->Fax);
			$dbObjectProfile['field_profil_geburtstag_value'] = trim($item->Geburtstag);
			$dbObjectProfile['field_profil_eintritt_value'] = trim($item->M_Eintritt);
			$dbObjectProfile['field_profil_imjahresbericht_value'] = '';
			$dbObjectProfile['field_profil_memo_value'] = trim($item->Adresse.Memo);
			$dbObjectProfile['field_profil_firma_value'] = trim($item->Firma);
			$dbObjectProfile['nid'] = $nid_profile; //key
			$dbObjectProfile['vid'] = $nid_profile;
			$dbObjectProfile['field_profile_adresse_id_value'] = $item->Adresse_ID;

			$ar_already_in_db[] = trim($item->Adresse_ID);
			$ar_profile_nid[trim($item->Adresse_ID)] = $nid_profile;
			//print_r($dbObjectProfile);
			drupal_write_record('content_type_profile',$dbObjectProfile,'vid');
			$anz_profiles++;
		}
		else{// already saved the profile
			$nid_profile = $ar_profile_nid[trim($item->Adresse_ID)];
		}


		$beitrag = 'Spender';
		if($item->Beitrag_Typ == 1){
			$beitrag = 'Mitglied';
		}

		$date = trim($item->Datum);
		//2002-01-01T00:00:00
		$ar_date = explode("-",$date);
		$date = $ar_date[0] . "-" . $ar_date[1] . "-" . "00T00:00:00";
		$dbObjectSpende['field_spende_datum_value'] = $date;
		$dbObjectSpende['field_spende_beitragsart_value'] = trim($beitrag);
		$dbObjectSpende['field_spende_betrag_value'] = trim($item->Betrag);
		$dbObjectSpende['field_spende_memo_value'] = trim($item->Adresse.Memo);
		$dbObjectSpende['vid'] = $nid_spende;
		$dbObjectSpende['nid'] = $nid_spende;
		$dbObjectSpende['field_spende_spender_nid'] = $nid_profile; //nid von spender
			
		//print_r($dbObjectSpende);
		drupal_write_record('content_type_spende',$dbObjectSpende,'vid');
		$anz_spenden++;
		//break;
	}
	drupal_set_message('Anzahl Spenden: ' . $anz_spenden . ' - Anzahl Profiles:' . $anz_profiles);
	//print '</pre>';
}


/**
 * Callback function to delete all nodes from the system
 *
 */
function gk_delete_all($nodetype=''){
   $sql = "SELECT nid FROM {node}";
   $result = db_query($sql);
   $i = 0;
   while($row = db_fetch_object($result)){
      if($nodetype!=''){
         //$node = node_load(array('nid'=>$row->nid));
         $tmp = db_fetch_object(db_query("SELECT type FROM {node} WHERE nid=%d",$row->nid));
         //print_r($tmp);
         if($tmp->type == $nodetype){
            node_delete(array('nid'=>$row->nid));
            //print $row->nid . ' '; 
            $i++;
         }

      }else{
         if($row->nid != 11882 && $row->nid != 3 && $row->nid != 4){
            node_delete($row->nid);
            $i++;
         }
      }
   }
   drupal_set_message("deleted all nodes $nodetype ($i)");
   drupal_goto('gk/import');
}