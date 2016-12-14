<?php

/**
 * Spendenliste erstellen
 *
 * @param array $form
 * @param array $form_state
 */
function _gk_generate_spendenliste($form,&$form_state){

	gk_insert_pdf_header();

	$ar_tids = array ();
	for($i=1;$i<6;$i++){
				foreach ($form_state['values']['gk_vocab'.$i] as $tid => $val) {
					if ($val != 0 && $val != 999) {
						$ar_tids[] = $val;
					}
				}
	}
	//filter by taxonomy
	$sql = "SELECT * FROM {term_node} AS tn, " .
	"{content_type_profile} AS ctp, " .
	"{content_type_spende} AS cts " .
	"WHERE " . 
	"ctp.nid = tn.nid " . 
	"AND cts.field_spende_spender_nid = ctp.nid ";
	if (count($ar_tids) > 0) {
		$where = ' AND (';
		$flag = FALSE;
		foreach ($ar_tids as $value) {
			if ($flag) {
				$where .= ' OR tn.tid = %d';
			} else {
				$where .= 'tn.tid = %d';
				$flag = TRUE;
			}
		}
		$where .= ')';
	}

	// Jahresbericht
	if ($form_state['values']['im_jahresbericht'] != 99) {
		$where .= "AND ctp.field_profil_imjahresbericht_value = '%s' ";
		if ($form_state['values']['im_jahresbericht'] == 0) {
			$ar_tids[] = 'nein';
		} else {
			$ar_tids[] = 'ja';
		}

	}
	
	

	// Verdankungsart
	if ($form_state['values']['verdankungsart'] != 99) {
		$where .= "AND ctp.field_profil_verdankung_value = '%s' ";
		if ($form_state['values']['verdankungsart'] == 1) {
			$ar_tids[] = 'keine';
		}
		elseif ($form_state['values']['verdankungsart'] == 2) {
			$ar_tids[] = 'Monatsverdankung';
		} else {
			$ar_tids[] = 'Jahresverdankung';
		}
	}

	//jetzt werden diese ganzen leute noch nach Datum aussortiert
	$ar_where = array();
	if($form_state['values']['date_von'] != ''){
		$from_date = _gk_transform_date($form_state['values']['date_von']);
		if(is_array($from_date)){
			$from_date = $from_date[0];
		}
		$where_sp .= " AND cts.field_spende_datum_value = '%s'";
		$ar_where = array($from_date);
	}
	if($form_state['values']['date_bis'] != ''){
		$ar_where = array();
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
		$ar_where = array($from_date,$to_date);
		
	}

	$array_where = array_merge($ar_tids,$ar_where);
	
		// Beitragsart
		// Beitragsart
		if($form_state['values']['beitragsart'] != 99){
			
			if ($form_state['values']['beitragsart'] == '1') {
				$where_sp .= " AND field_spende_beitragsart_value = '%s' ";
				$ar_where[] = 'Spender';
			}
			elseif ($form_state['values']['beitragsart'] == '2') {
				$where_sp .= " AND field_spende_beitragsart_value = '%s' ";
				$ar_where[] = 'Mitglied';
			} 
			elseif($form_state['values']['beitragsart'] == '3'){
				$where_sp .= " AND (field_spende_beitragsart_value = '%s' OR field_spende_beitragsart_value = '%s') ";
				$ar_where[] = 'Mitglied';
				$ar_where[] = 'Spender';
			}
		}
	
	
	//  $result = taxonomy_select_nodes($ar_tids);
	$result = db_query($sql . $where . $where_sp, $array_where);
 
	$rows = array();
	while($row = db_fetch_array($result)){
		$row['terms'] = gk_get_terms_for_node($row['field_spende_spender_nid']);
		if($form_state['values']['kumulieren'] == '1'){
			if(!empty($rows[$row['field_spende_spender_nid']])){
				$rows[$row['field_spende_spender_nid']]['field_spende_betrag_value'] += $row['field_spende_betrag_value'];
			}else{
				$rows[$row['field_spende_spender_nid']] = $row;
			}
		}else{
			$rows[] = $row;
		}
	}
	$i = 0;

	$pdf = new Gkpdf();
	
	$pdf->setRows($rows);
	$pdf->setParams($form_state['values']);
	$pdf->getFile();
}