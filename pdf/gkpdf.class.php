<?php
/***************************************************************************************
* Software: GKPDF.CLASS                                                        		   *
* Version:  0.5                                                                		   *
* Date:     2008-11-04                                                         		   *
* Author:   Jan Schür 		                                                   		   *
* License:  Freeware                                                           		   *
*                                                                              		   *
* You may use and modify this software as you wish.                            		   *
***************************************************************************************/
include_once ('fpdf/fpdf.php');
include_once ('gkxls.class.php');
if (!class_exists('GKPDF')) {
	define('GKPDF_VERSION', '0.5');

	class Gkpdf {
		//Private properties
		private $pdf; // Das PDF
		private $tpl;
		private $params;	// Die Paramter der Eingabemaske
		private $adressData;// Die Adressdaten
		private $EtikettenCount;	// Die Anzahl Etiketten; wird gebraucht, um eine Etikette an den Richtigen Ort auf dem Blatt zu plazieren
		private $EtikettenRow;		// Aktuelle Zeile bei den Etiketten
		private $EtikettenCol;		// Aktuelle Spalte bei den Etiketten
		private $filename = '_exportPDF.pdf';
		private $page = 1;	// aktuelle Seite

		/*******************************************************************************
		*                                                                              *
		*                               Public methods                                 *
		*                                                                              *
		*******************************************************************************/
		public function __construct() {
			$this->pdf = new FPDF();
			$this->pdf->SetAutoPageBreak(false);
			$this->EtikettenCount=0;
			$this->EtikettenRow=0;
			$this->EtikettenCol=0;
		}

		// Gewünschte Datei ausgeben
		public function getFile() {
				$t = $this->params['type_what'];
            print '<pre>'.print_r($this->adressData,1).'</pre>';
				if($t[1]){ foreach($this->adressData as $Param){$this->printBriefPage     ($Param);}}
				if($t[3]){ foreach($this->adressData as $Param){$this->printEtikettenLabel($Param);}}
				if($t[4]){ $this->printSpendenPage($this->adressData);}
				if($t[1]){ $this->pdf->Output(date('Y-m-d',time()).$this->filename, 'D'); }
				if($t[3]){ $this->pdf->Output(date('Y-m-d',time()).$this->filename, 'D');$this->pdf->Output(date('Y-m-d',time()).$this->filename, 'D'); }	// Braucht es irgendwie zweimal, sonst gehts nicht...
				if($t[4]){ $this->pdf->Output(date('Y-m-d',time()).$this->filename, 'D'); }
				if($this->params['type_what'][2]){ $this->exportExcel($this->adressData); }

		}

		/**
		 * Parameter für die SQL Abfrage
		 */
		public function setParams($arrParams){
			//dsm($arrParams); //zum debuggen
			$this->params = $arrParams;
		}

		/**
		 * set the rows from the db, containing the profile information and
		 * the betrag 
		 */
		public function setRows($arrRows){
			// folgender Vorgang ist notwendig, um die Briefe nach dem Betrag zu sortieren
			$tmpParams = array();
			foreach($arrRows as $k=>$v) {
				$b = round($v['field_spende_betrag_value']);
				$b = substr('0000000'.$b,-7);
				$tmpParams[$b][$k] = $v;
			}
			ksort($tmpParams);
			foreach($tmpParams as $var) {
				foreach($var as $k=>$v) {
					$this->adressData[$k] = $v;
				}
			}
		}

		// Template für den Serienbrief einlesen
		public function setTemplate($arrParam) {
		//dsm($arrParam); //zum debuggen
			$this->tpl = array (
				'betreff' 	=> '',
				'brief' 	=> '',
				'gruss' 	=> '',
				'autor' 	=> '',
				'footer' 	=> '', 
				'autor2'	=> '', 
				'bed_aut2' => ''
			);
			if (isset ($arrParam['field_serienbrief_betreff_value'])) {
				$this->tpl['betreff'] = utf8_decode($arrParam['field_serienbrief_betreff_value']);
			}
			if (isset ($arrParam['field_serienbrief_brief_value'])) {
				$this->tpl['brief'] = utf8_decode($arrParam['field_serienbrief_brief_value']);
			}
			if (isset ($arrParam['field_serienbrief_gruss_value'])) {
				$this->tpl['gruss'] = utf8_decode($arrParam['field_serienbrief_gruss_value']);
			}
			if (isset ($arrParam['field_serienbrief_autor_value'])) {
				$this->tpl['autor'] = utf8_decode($arrParam['field_serienbrief_autor_value']);
			}
			if (isset ($arrParam['field_serienbrief_fusszeile_value'])) {
				$this->tpl['footer'] = utf8_decode($arrParam['field_serienbrief_fusszeile_value']);
			}
			if (isset ($arrParam['field_serienbrief_autor2_value'])) {
				$this->tpl['autor2'] = utf8_decode($arrParam['field_serienbrief_autor2_value']);
			}
			if (isset ($arrParam['field_serienbrief_bed_aut2_value'])) {
				$this->tpl['bed_aut2'] = utf8_decode($arrParam['field_serienbrief_bed_aut2_value']);
			}
		}
		/*******************************************************************************
		*                                                                              *
		*                              Protected methods                               *
		*                                                                              *
		*******************************************************************************/

		/**
		 * Schreibt eine Serienbrief-Seite
		 * @param array $arrParam Das Array mit den Adressdaten
		 */
		private function printBriefPage($arrParam) {
			$content = $this->parseAdress($arrParam);
				$tmpTpl = $this->tpl;
				$this->varReplace($tmpTpl, $content);
				// Die Null-Beträge rausfiltern, ausser das Option "Beitragsart" ist auf "egal"
				if(($this->params['beitragsart'] <= 3 && $content['betrag'] > 0) || (intval($this->params['beitragsart']) > 3)) {
					$this->pdf->AddPage();
					$this->printHeader();
					$this->printOrtDatum();
					$this->printAdresse(125,50,$content);
					$this->varReplace($content['briefanrede'],$content);
					$this->printAnrede($content['briefanrede']);
					$this->printBetreff($tmpTpl['betreff']);
					$this->printBrief($tmpTpl['brief']);
					$this->printGruss($tmpTpl['gruss']);
					$betrag = intval(substr($tmpTpl['bed_aut2'],10,strlen($tmpTpl['bed_aut2'])-1));
					if(substr($tmpTpl['bed_aut2'],0,9)=='betrag >=' && $content['betrag']>=$betrag) {
						$this->printAutor($tmpTpl['autor2']);
					}
					else {
						$this->printAutor($tmpTpl['autor']);
					}
					$this->printFooter($tmpTpl['footer']);
				}
		}

		/**
		 * Erstellt die Spendenliste
		 * @param array $arrParam Das Array mit den Adress- und Spendendaten von allen
		 */
		private function printSpendenPage($arrParam) {
			$this->pdf->SetAutoPageBreak(False);
			$this->pdf->AddPage('L');	// beginnt Spendenliste auf neuer Seite
			$this->printHeader(90);		// Header und Logo des Papiers
			$this->pdf->SetFont('Arial', 'B', 14);$this->pdf->Text(10,20,'Spendenliste '.$this->params['date_von']);	// Titel des Papiers
			$this->pdf->SetY(45);
			$this->pdf->SetFont('arial', '', 9);
			$fill = 0;
			//print_r($arrParam);
			$groupedArrParam = array();

			// Datensütze nach Spenden-Gruppen aufteilen
			foreach($arrParam as $k=>$p){
				if($p['field_spende_beitragsart_value'] == 'Mitglied') {
					$groupedArrParam['Mitgliederbeitrag'][$k] = $p;
					$groupedArrParam['Mitgliederbeitrag'][$k]['gruppen'] = $this->getGruppe($p['terms']);
				}
				else {
					$gruppen = $this->getGruppe($p['terms']);
					$gruppe = ($gruppen['Spender']=='')?(($gruppen['Mitglied']=='')?$gruppen['Team']:$gruppen['Mitglied']):$gruppen['Spender'];
					$groupedArrParam[$gruppe][$k] = $p;
					$groupedArrParam[$gruppe][$k]['gruppen'] = $gruppen;
				}
			}
			//print_r($groupedArrParam);
			//print "<br><br><br><br><br>";
			//print_r($arrParam);
			$tTotal = 0;
			$i = 0; // Anzahl Spenden
			foreach($groupedArrParam as $term=>$arrParam) {
            //if ($term === '') $term = 'keine Gruppenzuordnung';
				$this->pdf->SetFillColor(150,210,150);$this->pdf->Cell(282,10,$term,0,0,'',1);$this->pdf->ln();
				$total = 0;
				foreach($arrParam as $Param){	// Einzelne Adresssütze durchgehen
					$fill = ($fill)?0:1;
					$fill?$this->pdf->SetFillColor(200):'';
					$adresse = $this->parseAdress($Param);
					if($adresse['firma']=='') { $this->pdf->Cell(100,4,$adresse['nachname'].' '.$adresse['vorname'],0,0,'',$fill); }
					else { $this->pdf->Cell(100,4,$adresse['firma'].' '.$adresse['zusatz'],0,0,'',$fill); }						 
					$this->pdf->Cell(50,4,$adresse['strasse'],0,0,'',$fill);
					$this->pdf->Cell(17,4, $adresse['plz'],0,0,'L',$fill);
					$this->pdf->Cell(32,4,$adresse['ort'],0,0,'',$fill);
					$this->pdf->Cell(13,4,number_format($adresse['betrag'],2,".","`"),0,0,'R',$fill);
					$this->pdf->Cell(13,4,(($Param['field_profil_verdankung_value']=='keine')?'nein':'ja'),0,0,'L',$fill);
					$this->pdf->Cell(70-13,4,utf8_decode(substr($Param['field_spende_memo_value'],0,41)).(strlen($Param['field_spende_memo_value'])>42?' [..]':''),0,0,'',$fill);
					$this->pdf->ln();
					if ($this->pdf->GetY() >= 180) {
						$this->spenden_footer($this->params['date_von']);
						$this->pdf->AddPage('L');	// beginnt Spendenliste auf neuer Seite
						$this->page++;
					}
					$total += $adresse['betrag'];
					$i++;
				}
				$this->pdf->SetFillColor(160,150,210);$this->pdf->Cell(282,4,'Total: '.number_format($total,2,".","`"),0,0,'R',1);$this->pdf->Ln();$this->pdf->Ln();
				$tTotal += $total;
			}
         if ($this->pdf->GetY() >= 180) {
            $this->spenden_footer($this->params['date_von']);
            $this->pdf->AddPage('L');	// beginnt Spendenliste auf neuer Seite
            $this->page++;
         }
			$this->pdf->Ln();$this->pdf->Ln(20);$this->pdf->Ln();$this->pdf->SetFillColor(160,150,210);$this->pdf->Cell(282,4,'Anzahl: '.$i.'; Total alles : '.number_format($tTotal,2,".","`"),0,0,'R',1);
			$this->spenden_footer($this->params['date_von']);
		}



		/**
		 * Druckt eine Adresse auf das Etikettenblatt; setzt automatisch die richtige Position auf der Seite
		 * @param array $adresse Das Array mit den Adressen
		 */
		private function printEtikettenLabel($adresse) {
			$adresse = $this->parseAdress($adresse);
			$this->EtikettenCount++;
			if($this->EtikettenCount==1 || $this->EtikettenCount==25) { $this->pdf->AddPage(); }
			if($this->EtikettenCount==25) { $this->EtikettenCount=1;}
			$y = (ceil($this->EtikettenCount / 3)-1)*37+5;
			$x = ($this->EtikettenCount-(ceil($this->EtikettenCount / 3)-1)*3-1)*71+5;
			$this->printAdresse($x,$y,$adresse);
		}

		/**
		 * Exportiert die Datensütze nach Excel
		 * @arrParam array die Datensütze
		 */
		private function exportExcel($arrParam){
			$xls = new Cxls(date('Y-m-d',time()).'_GassenkuecheDatenExport',$arrParam);
			$xls->setHeaderRow(Array(
					"vid" 									   => 'node-id',
					/* "nid" 									=> 'nid', */
					"field_profil_firma_value" 			=> 'Firma',
					"field_profil_zusatz_value" 			=> 'Zusatz',
					"field_profil_vorname_value" 			=> 'Vorname',
					"field_profil_nachname_value" 		=> 'Nachname',
					"field_profil_strasse_value" 			=> 'Strasse',
					"field_profil_plz_value" 				=> 'PLZ',
					"field_profil_ort_value" 				=> 'Ort',
					"field_spende_betrag_value" 			=> 'Betrag',
					"field_profil_telp_value" 				=> 'Telefon privat',
					"field_profil_telg_value" 				=> 'Telefon Geschäft',
					"field_profil_handy_value" 			=> 'Handy',
					"field_profil_faxg_value" 				=> 'Fax Geschäft',
					"field_profil_mail_email" 				=> 'E-Mail',
					"field_profil_geburtstag_value" 		=> 'Geburtstag',
					"field_profil_eintritt_value" 		=> 'Eintritt',
					"field_profil_verdankung_value" 		=> 'Verdankung',
					"field_profil_imjahresbericht_value"=> 'Jahresbericht',
					"field_profil_memo_value" 				=> 'Memo',
					/* "field_profile_adresse_id_value" => 'Adresse ID', */
					"field_profil_inaktiv_value" 			=> 'Inaktiv',
					"terms"									   => 'Gruppen',
					"field_profil_anrede_value" 			=> 'Anrede',
					"field_profil_briefanrede_value"		=> 'Briefanrede',
					"field_profil_anredespezial_value" 	=> 'Anrede spezial',
					"field_profil_briefanredespez_value"=> 'Briefanrede spezial'
			));
			$xls->output();
		}

		/*
		 * Druckt den Header eines Papiers
		 * Beinhaltet das Gassenküchen-Logo auf der rechten Seite
		 * Ist das Papier in Landscape, muss noch eine Einzug dazu
		 * gerechnet werden: $landscape=90 ist ein guter Wert
		 */
		private function printHeader($landscape=0) {
			$img_path = drupal_get_path('module', 'gk') . '/pdf/logo_brief.png';
			$this->pdf->Image($img_path, 161+$landscape, 7, 35);
		}
		private function printOrtDatum() {
			$this->pdf->SetFont('arial', '', 10);
			$this->pdf->SetX(150);
			$this->pdf->SetY(90);
			$this->pdf->Write(5, "Basel, ".date("d.m.Y",time()));
		}
		private function printBetreff($par) {
			$this->pdf->SetFont('arial', 'B', 12);
			$this->pdf->SetX(20);
			$this->pdf->SetY(105);
			$this->pdf->Write(5, $par);
		}
		/**
		  * Druckt eine Adresse an beliebiger Stelle $x,$y
		  * @param integer $x x-Koordinate, wo Adressblock geschrieben wird
		  * @param integer $y y-Koordinate, wo Adressblock geschrieben wird
		  * @param array $content das Adress-Array
		  */
		private function printAdresse($x,$y, $content) {
			$leftMargin = $this->pdf->lMargin;
			$this->pdf->SetFont('arial', '', 11);
			$this->pdf->SetLeftMargin($x);
			$this->pdf->SetX(0);
			$this->pdf->SetY($y);
			$string .= ($content['firma']!='')?$content['firma']."\n":'';
			$string .= ($content['zusatz']!='')?$content['zusatz']."\n":'';
			$string .= ($content['anrede']!='' && $content['vorname'].$content['nachname']!='')?$content['anrede']."\n":'';
			$string .= ($content['vorname'].$content['nachname']!='')?$content['vorname'] . ' ' . $content['nachname']."\n":'';
			$string .= ($content['strasse']!='')?$content['strasse']."\n":'';
			$string .= ($content['plz'].$content['ort']!='')?$content['plz'] . ' ' . $content['ort']."\n":'';
			$string = wordwrap($string,30);
			$this->pdf->Write(5, $string);
			$this->pdf->SetLeftMargin($leftMargin);
		}
		private function printAnrede($par) {
			$this->pdf->SetFont('arial', '', 11);
			$this->pdf->SetX(20);
			$this->pdf->SetY(125);
			$this->pdf->Write(5, $par);
		}
		private function printBrief($par) {
			$this->pdf->SetFont('arial', '', 11);
			$this->pdf->SetX(20);
			$this->pdf->SetY(140);
			$this->pdf->Write(5, $par);
		}
		private function printGruss($par) {
			$this->pdf->SetFont('arial', '', 11);
			$this->pdf->SetX(20);
			$this->pdf->SetY($this->pdf->GetY() + 10);
			$this->pdf->Write(5, $par);
		}
		private function printAutor($par) {
			$this->pdf->SetFont('arial', '', 11);
			$this->pdf->SetX(20);
			$this->pdf->SetY($this->pdf->GetY() + 20);
			$this->pdf->Write(5, $par);
		}
		private function printFooter($par) {
			$leftMargin = $this->pdf->lMargin;
			$this->pdf->SetFont('arial', '', 8);
			$this->pdf->SetLeftMargin(160);
			$this->pdf->SetX(0);
			$this->pdf->SetY(297 - 30);
			$this->pdf->Write(3, $par);
			$this->pdf->SetLeftMargin($leftMargin);
		}

		/*
		 * Parst die Adresse anhand des Inputs $arrParam
		 * returns Array $content
		 */
		private function parseAdress($arrParam) {
			$content = array (
				'firma' => '',
				'vorname' => '',
				'nachname' => '',
				'anrede' => '',
				'zusatz' => '',
				'strasse' => '',
				'plz' => '',
				'ort' => '',
				'briefanrede' => '',
				'anredespezial' => '',
				'briefanredespezial' => '',
				'betrag' => '',
            'spendendatum' => '',
			);
			if (isset ($arrParam['field_profil_vorname_value'])) {
				$content['vorname'] = utf8_decode($arrParam['field_profil_vorname_value']);
			}
			if (isset ($arrParam['field_profil_nachname_value'])) {
				$content['nachname'] = utf8_decode($arrParam['field_profil_nachname_value']);
			}
			if (isset ($arrParam['field_profil_firma_value'])) {
				$content['firma'] = utf8_decode($arrParam['field_profil_firma_value']);
			}
			if (isset ($arrParam['field_profil_anrede_value'])) {
				$content['anrede'] = utf8_decode($arrParam['field_profil_anrede_value']);
			}
			if (!is_null($arrParam['field_profil_anredespezial_value'])) {
				$content['anrede'] = utf8_decode($arrParam['field_profil_anredespezial_value']);
			}
			if (isset ($arrParam['field_profil_zusatz_value'])) {
				$content['zusatz'] = utf8_decode($arrParam['field_profil_zusatz_value']);
			}
			if (isset ($arrParam['field_profil_strasse_value'])) {
				$content['strasse'] = utf8_decode($arrParam['field_profil_strasse_value']);
			}
			if (isset ($arrParam['field_profil_plz_value'])) {
				$content['plz'] = utf8_decode($arrParam['field_profil_plz_value']);
			}
			if (isset ($arrParam['field_profil_ort_value'])) {
				$content['ort'] = utf8_decode($arrParam['field_profil_ort_value']);
			}
			if (isset ($arrParam['field_profil_briefanrede_value'])) {
				$content['briefanrede'] = utf8_decode($arrParam['field_profil_briefanrede_value']);
			}
			if (!is_null ($arrParam['field_profil_briefanredespez_value'])) {
				$content['briefanrede'] = utf8_decode($arrParam['field_profil_briefanredespez_value']);
			}
			if (isset ($arrParam['field_spende_betrag_value'])) {
				$content['betrag'] = utf8_decode($arrParam['field_spende_betrag_value']);
			}
			return $content;
		}

		private function getGruppe($terms) {
			$sg = array('Spender'=>'','Mitglied'=>'','Team'=>'','allgemeine Adresse'=>'','Sonstiges'=>'');
			foreach($terms as $gruppe) {
				if( $gruppe=='anonym/bar' || $gruppe=='katholisch' || $gruppe=='Medien' || $gruppe=='methodistisch' || $gruppe=='privat' || $gruppe=='reformiert' || $gruppe=='sonstiges' || $gruppe=='sozial' || $gruppe=='Wirtschaft') {
						$sg['Spender'] = $gruppe;
					}
					elseif($gruppe=='normal' || $gruppe=='Vorstand') {
						$sg['Mitglied'] = $gruppe;
					}
					elseif($gruppe=='fest' || $gruppe=='freiwillig aktiv' || $gruppe=='freiwillig passiv' || $gruppe=='Praktikant' || $gruppe=='Seitenwechsler') {
						$sg['Team'] = $gruppe;
					}
					elseif($gruppe=='Lieferanten' || utf8_decode($gruppe)=='PüB') {
						$sg['allgemeine Adresse'] = $gruppe;
					}
               //else {
					elseif($gruppe=='keine') {
						$sg['Sonstiges'] = $gruppe;
					}
			}
			return $sg;
		}

		/**
		 * Ersetzt die String-Variablen
		 * @param array &$arrParam Das Array wo ersetzt werden soll
		 * @param array $content Das Array mit den Adressdaten
		 */
		private function varReplace(&$arrParam, $content) {
			$arrParam = str_ireplace('%betrag%',  $content['betrag'],  $arrParam);
			$arrParam = str_ireplace('%vorname%', $content['vorname'], $arrParam);
			$arrParam = str_ireplace('%nachname%',$content['nachname'],$arrParam);
			$arrParam = str_ireplace('%firma%',   $content['firma'],   $arrParam);
         $arrParam = str_ireplace('%spendendatum%',   $content['firma'],   $arrParam);
		}

		private function spenden_footer($month) {
			$this->pdf->SetFont('arial', '', 8);
			$this->pdf->Text(10,205,'Spendenliste vom: '.$month);
			$this->pdf->Text(292-10,205,'Seite '.$this->page);
		}
	} //End of Class
} // End if Class not exists

