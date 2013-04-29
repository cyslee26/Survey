<?php
//-----------------------------------------------------------------------------
//	Libs & Constants
//-----------------------------------------------------------------------------
require_once('../lib/User.php');
require_once('../lib/Survey.php');
require_once('../lib/SurveyViewUtil.php');

define(HTML, '../html/main.htm');
define(SURVEY_DEBUG, 0);

//-----------------------------------------------------------------------------
//	Get Form Query Value ...
//-----------------------------------------------------------------------------
$sort = $_GET[sort];
if ($sort == "") {
	$sort = "title";
}

$order = $_GET[order];
if ($order == "") {
	$order = "ASC";
}

//-----------------------------------------------------------------------------
//	create survey object
//-----------------------------------------------------------------------------
$survey = new Survey($db);

$ret_code = $survey->GetAllSurveys($survey_data, $sort, $order);
if($ret_code < 0)
{
	$html = "ERROR: Can't get Survey / Quiz Data [ret_code: $ret_code]";
	design_top("Survey / Quiz Index: ERROR Message");
	echo $html;
	design_bottom();
	exit(0);
}

/* check the number of data */
if(count($survey_data) == 0)
{
	$html = "ERROR: Can't get Survey / Quiz Data [ret_code: $ret_code]";
	design_top("Survey / Quiz Index: ERROR Message");
	echo $html;
	design_bottom();
	exit(0);
}

$row_statment = SurveyViewUtil::DisplayResultsInTable($survey_data, $survey);

/* get html contents */
$fp = fopen(HTML, "r");
if(!$fp)
{
	$html = "ERROR: Can't Display the Survey Data.  Please contact the developer. ";
	design_top("Survey / Quiz Index: ERROR Message");
	echo $html;
	design_bottom();
	exit(0);
}

$html = fread($fp, filesize(HTML));

/* clean up */
fclose($fp);
unset($survey);

/* replace the contents */
$html = str_replace("<!--SURVEY_DATA_ROWS-->", $row_statment, $html);

design_top("Survey / Quiz Index");

echo $html;


design_bottom();

exit(0);







?>
