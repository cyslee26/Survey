<?php
//-----------------------------------------------------------------------------
//	Libs & Constants
//-----------------------------------------------------------------------------
require_once('/mnt/about/tools/editorial/lib/Util.php');
require_once(LOUNGE_ROOT.'user.php');
require_once(LIB_EDITORS_ROOT . 'Survey.php');	
require_once(EDITORS_ROOT . 'design_functions.php');

define(HTML, EDITORS_ROOT.'tools/survey/html/main.htm');
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
//	User Checking ...
//-----------------------------------------------------------------------------
if($permlevel != 2)
{
	design_top("Permission ERROR:");
	echo '<P>You do not have permission to view the survey index page !! ';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	create survey object
//-----------------------------------------------------------------------------
$survey = new Survey($db, SERVER_ENV);

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

$row_statment = DisplayResultsInTable($survey_data, $survey);

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

//-----------------------------------------------------------------------------
// Function section ....
//-----------------------------------------------------------------------------

function DisplayResultsInTable($survey_data, $survey)
{
	$str = "";
	for ($i = 0; $i < count($survey_data) ; $i++)
	{
		if($i % 2 == 0)
		{
			$row_color = 'dark_gray_table';
		}
		else
		{
			$row_color = 'light_gray_table';
		}
		
				
		$total_questions = $survey->GetTotalNumQuestion($survey_data[$i]['id']);
		
		$str .= "
				<tr class='$row_color'>
					<td>".$survey_data[$i]['title']."</td>					
					<td>".$survey_data[$i]['wikis']."</td>	
					<td>".$total_questions ."</td>	
					<td>
					<a href=\"surveyedit.php?survey_id=".$survey_data[$i]['id']."\"> Edit </a> /
					<a href=\"surveycopy.php?survey_id=".$survey_data[$i]['id']."\"> Copy </a> /
					<a href=\"questionrank.php?survey_id=".$survey_data[$i]['id']."\"> Question </a> /
					<a href=\"javascript:surveydel('".$survey_data[$i]['id']."', '".$survey_data[$i]['type']."') \"> Delete </a> /
					<a href=\"surveytake.php?survey_id=".$survey_data[$i]['id']."\" TARGET=\"_BLANK\"> Take It </a> / 
					<a href=\"resultview.php?survey_id=".$survey_data[$i]['id']."\"> View Results </a> /
					<a href=\"resultdownload.php?survey_id=".$survey_data[$i]['id']."\"> Download All Results </a> /
					</td>
				</tr>";
	}

	return $str;
}




?>
