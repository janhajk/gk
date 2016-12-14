<?php
/***************************************************************************************
* Software: gkxls.class                                                        		   *
* Version:  0.1                                                                		   *			
* Date:     2008-12-05                                                         		   *
* Author:   Jan Schär 		                                                   		   *
* License:  Freeware                                                           		   *
*                                                                              		   *
* You may use and modify this software as you wish.                            		   *
***************************************************************************************/
if (!class_exists('Cxls')) {
	define('Cxls_VERSION', '0.1');

	class Cxls {
		//Private properties
		private $data; // die Daten für das Excel-Sheet
		private $filename = 'export';
		private $headerRows;

		/*******************************************************************************
		*                                                                              *
		*                               Public methods                                 *
		*                                                                              *
		*******************************************************************************/
		public function __construct($filename, $arrParam) {
			$this->filename = $filename;
			$this->data = $arrParam;
		}
		public function setHeaderRow($arrParam) {
				$this->headerRows = $arrParam;
		}
		
		/*
		 * Gibt das XLS aus
		 * "\t" -> für neue Zelle
		 * "\n" -> für neue Zeile
		 */
		public function output(){
			$data = '';
			$total = '';
			foreach($this->data as $row) {	// Zeile für Zeile
				$line = '';
				foreach($this->headerRows as $key=>$col) {
					$value = $row[$key];
					$arr_value = '';
					if ((!isset($value)) OR ($value == "")) {$value = "\t";}	// leere Zelle
					elseif(is_array($value)) {									// Zelle ist Array
						$value = $this->split_spenderArray($value);
						foreach($value as $par) {
							$arr_value .= '"'.$par.'"'."\t";
						}
						$value = $arr_value;
					}		// für das gruppen-array
					// Als Zelle formatieren
					else {														// Zelle hat normalen Inhalt
						$value = $value;	// wegen den Umlauten
						$value = str_replace('"', '""', $value);
						$value = '"'.$value.'"'."\t";
					}
					$line .= $value;	// Zelle zu Row hinzufügen
				}	// nächste Zelle	 
				$data .= trim($line)."\n";	// neue Zeile
			}	// nächste Zeile
			$data = str_replace("\r","",$data);
			$data = $this->makeHeaderRow().utf8_decode($data);
			$this->xlsHeader(strlen($data));
			print $data;
			exit;	// gibt die ExcelDatei aus, keine weiteren aktionen möglich
		}
		
		/*
		 * Schreibt den XLS Header der Datei
		 */
		private function xlsHeader($filelength) {
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Length: $filelength");
			header("Content-type: application/csv; charset=utf-8");
			header("Content-Disposition: attachment;filename=".$this->filename.".xls");			
			header("Expires: 0");
		}
		
		/*
		 * Erstellt die Kopfzeile der Tabelle
		 */
		private function makeHeaderRow() {
			$line = '';
			foreach($this->headerRows as $key=>$value) {                                                      
				if ((!isset($value)) OR ($value == "")) {$value = "\t";} 
				elseif($value=='Gruppen') {$value='"'.'Spender'.'"'."\t".'"'.'Mitglied'.'"'."\t".'"'.'Team'.'"'."\t".'"'.'allgemeine Adressen'.'"'."\t".'"'.'Sonstiges'.'"'."\t";}
				else {
					$value = ($value);	// wegen den Umlauten
					$value = str_replace('"', '""', $value);
					$value = '"' . $value . '"' . "\t";
				}
				$line .= $value;
			}
			return(trim($line)."\n");
		}
		
		/*
		 * Nimmt das Array mit den Gruppenzugehörigkeiten
		 * und Splittet es nach Spender-Art
		 */
		private function split_spenderArray($gruppenarray) {
			$sg = array('Spender'=>'','Mitglied'=>'','Team'=>'','allgemeine Adresse'=>'','Sonstiges'=>'');
			foreach($gruppenarray as $gruppe) {
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
					elseif($gruppe=='keine') {
						$sg['Sonstiges'] = $gruppe;
					}
			}
		return $sg;
		}

	} //End of Class
} // End if Class not exists