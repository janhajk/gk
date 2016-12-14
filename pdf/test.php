<?
include_once('gkpdf.class.php');
$testpdf = new GKPDF();
$testarr = array();
$testpdf->printSerienbrief($testarr);
?>