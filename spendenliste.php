<?php

/**
 * Spendenliste erstellen
 *
 * @param array $form
 * @param array $form_state
 */
function _gk_generate_spendenliste($form, &$form_state){

   // FPDF Header generieren
	gk_insert_pdf_header();

	// Lädt die Auswahl der Spendertypen
   $ar_tids = array ();
   for($i=1;$i<=5;$i++){
      foreach ($form_state['values']['gk_vocab'.$i] as $tid => $val) {
         // Alle gesetzten Häckchen speichern;
         // die anderen (mit Value 0 oder 999) werden nicht weitergetragen
         if ($val != 0 && $val != 999) {
            $ar_tids[] = $val;
         }
      }
   }

   // Erstellen der Query;
   // Die Tabelle term_node enthält alle Term-Node Verknüpfungen
   // Es werden also alle Terms geladen, die selektiert wurden

   // drupal_query übergibt die Values separat,
   // deshalb werden diese in einem Array gespeichert
   $values = [];

   $where = [];
   $filter = [];
   foreach ($ar_tids as $tid) {
      $filter[] = 'tn.tid = %d';
      $values[] = $tid;
   }
   $filter[] = 'tn.tid IS NULL';
   $where[] = '('.implode(" OR ", $filter).')';

   $join = [
      "LEFT JOIN content_type_profile as ctp ON (cts.field_spende_spender_nid = ctp.nid)",
      "LEFT JOIN term_node as tn ON (cts.field_spende_spender_nid = tn.nid)"
   ];
   $sql = "SELECT DISTINCT cts.nid, cts.*, ctp.* FROM content_type_spende as cts ".implode(" ",$join);

	// Jahresbericht
	if ($form_state['values']['im_jahresbericht'] != 99) {
		$where[] = "ctp.field_profil_imjahresbericht_value = '%s'";
		if ($form_state['values']['im_jahresbericht'] == 0) {
			$values[] = 'nein';
		} else {
			$values[] = 'ja';
		}

	}

	// Verdankungsart
	if ($form_state['values']['verdankungsart'] != 99) {
		$where[] = "ctp.field_profil_verdankung_value = '%s'";
		if ($form_state['values']['verdankungsart'] == 1) {
			$values[] = 'keine';
		}
		elseif ($form_state['values']['verdankungsart'] == 2) {
			$values[] = 'Monatsverdankung';
		} else {
			$values[] = 'Jahresverdankung';
		}
	}

   // "Datum von:" und "Datum bis:"
   if($form_state['values']['date_von'] != ''){
      $from_date = _gk_transform_date($form_state['values']['date_von']);
      if(is_array($from_date)){
         $from_date = $from_date[0];
      }
      $where_sp .= " AND cts.field_spende_datum_value = '%s'";
      $values[] = $from_date;
   }
   if($form_state['values']['date_bis'] != ''){
      $to_date = _gk_transform_date($form_state['values']['date_bis']);
      if(is_array($from_date)){
         $to_date = $to_date[0];
      }
      $from_date = _gk_transform_date($form_state['values']['date_von']);
      if(is_array($from_date)){
         $from_date = $from_date[0];
      }
      $where_sp = " AND cts.field_spende_datum_value > '%s'";
      $where_sp .= " AND cts.field_spende_datum_value < '%s'";
      $values[] = $to_date;
   }

   // Beitragsart (Select Option)
   if($form_state['values']['beitragsart'] != 99){
      if ($form_state['values']['beitragsart'] == '1') {
         $where_sp .= " AND field_spende_beitragsart_value = '%s' ";
         $values[] = 'Spender';
      }
      elseif ($form_state['values']['beitragsart'] == '2') {
         $where_sp .= " AND field_spende_beitragsart_value = '%s' ";
         $values[] = 'Mitglied';
      }
      elseif($form_state['values']['beitragsart'] == '3'){
         $where_sp .= " AND (field_spende_beitragsart_value = '%s' OR field_spende_beitragsart_value = '%s') ";
         $values[] = 'Mitglied';
         $values[] = 'Spender';
      }
   }
	//  $result = taxonomy_select_nodes($ar_tids);
	$sql = $sql.' WHERE '.implode(" AND ", $where).$where_sp;
   error_log($sql);
   error_log(print_r($values,1));
	$result = db_query($sql, $values);
   $rows = array();
	while($row = db_fetch_array($result)){
		$row['terms'] = gk_get_terms_for_node($row['field_spende_spender_nid']);
		if($form_state['values']['kumulieren'] == '1'){
			if(!empty($rows[$row['field_spende_spender_nid']])){
				$rows[$row['field_spende_spender_nid']]['field_spende_betrag_value'] += $row['field_spende_betrag_value'];
			}else{
				$rows[$row['field_spende_spender_nid']] = $row;
			}
		}
      else {
			$rows[] = $row;
		}
	}
	$i = 0;

	$pdf = new Gkpdf();

	$pdf->setRows($rows);
	$pdf->setParams($form_state['values']);
	$pdf->getFile();
}