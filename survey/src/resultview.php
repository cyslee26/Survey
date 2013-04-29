<?php
//------------------------------------------------------------------------------
// Program Name    :	
// Aurthor Name    :	
// Create      Date:	
// Last Modify Date:
// Description     :	
//------------------------------------------------------------------------------

//-----------------------------------------------------------------------------
//	Libraries ...
//-----------------------------------------------------------------------------
require_once(LIB_ROOT . '/Util.php');
require_once(LIB_ROOT . 'User.php');
require_once(LIB_ROOT . 'HTMLForm.php');
require_once('../lib/Survey.php');

//-----------------------------------------------------------------------------
//	Contant Variables ...
//-----------------------------------------------------------------------------
define(SURVEY_DEBUG, 0);
define(HTML, '../html/survey_result.htm');

//-----------------------------------------------------------------------------
//	Form Queries ...
//-----------------------------------------------------------------------------
$survey_id = $_GET['survey_id'];
if($survey_id == "")
{
	$survey_id = 0;
}

$question_id = $_GET['question_id'];
if($question_id == "")
{
	$question_id = 0;
}

if(SURVEY_DEBUG) { WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "[survey_id:$survey_id] [qid:$qid]"); }

//-----------------------------------------------------------------------------
//	Validating the survey id
//-----------------------------------------------------------------------------
if(empty($survey_id))
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	Create survey objects 
//-----------------------------------------------------------------------------
$survey = new Survey($db, SERVER_ENV);
$htmlform = new HTMLForm();

//-----------------------------------------------------------------------------
//	Get Survey 
//-----------------------------------------------------------------------------
$ret_code = $survey->GetSurvey($survey_id, $survey_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	Get All the Questions
//-----------------------------------------------------------------------------
$ret_code = $survey->GetAllQuestions($survey_id, $question_data);
if($ret_code < 0)
{
	design_top("ERROR: Quiz / Survey");
	echo "<P>Can not get questions for the current survey !! [ret_code: $ret_code]";
	design_bottom();
	exit(0);
}

/* if it is the first page, than shows the first question results */
if($question_id == 0)
{
	$question_id = $question_data[0]['id'];
}
//-----------------------------------------------------------------------------
//	Build Questions List
//-----------------------------------------------------------------------------
for($i = 0; $i < count($question_data); $i++)
{
	$q_key = $question_data[$i]['id'];
	
	$str_break_index = strpos($question_data[$i]['question'], "[");
	
	if($str_break_index == 0){
		$str_break_index = strlen($question_data[$i]['question']);
	}

	$questions[$q_key]['title'] = substr($question_data[$i]['question'], 0, $str_break_index);
	$questions[$q_key]['display'] = 1;
}

$extra = "onChange=\"DisplaySurveyResult('".$survey_id."', 'question')\" STYLE=\"font-size: 8pt\"";
$str_question_dropdown = $htmlform->CreateDropDownWithArray("question", $questions, $question_id, $extra);

//-----------------------------------------------------------------------------
//	Build Total Results in table 
//-----------------------------------------------------------------------------
if(($question_id > 0) && ($survey_id > 0))
{
	$ret_code = SurveyViewUtil::BuildResultsInArray($survey, $survey_id, $question_id, $summary_results, $detail_results);
	if($ret_code < 0)
	{
		design_top("ERROR: Quiz / Survey");
		echo "<P>Can't build the results in table !! [ret_code: $ret_code]";
		design_bottom();
		exit(0);
	}
}

$summary_fields = array("Answer", "Count", "Percentage");
$showTotal      = 0;
$str_summary_table = SurveyViewUtil::BuildResultsInTable($summary_results, $summary_fields, $showTotal);

$detail_fields = array("username", "answer");
$showTotal     = 1;
$str_detail_table = SurveyViewUtil::BuildResultsInTable($detail_results, $detail_fields, $showTotal);

//-----------------------------------------------------------------------------
//	Clean up the object
//-----------------------------------------------------------------------------
unset($survey);
unset($htmlform);

//-----------------------------------------------------------------------------
// Get HTML Template
//-----------------------------------------------------------------------------
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
fclose($fp);

// Graph URL Section 
if(count($summary_results) >  0)
{
	$str_image    = '<img src="survey_graph.php?survey_id='.$survey_id.'&question_id='.$question_id.'" >';
	$str_download = '<a href="resultdownload.php?survey_id='.$survey_id.'&question_id='.$question_id.'">Download Current Results </a>';
}
else
{
	$str_image = "";
	$str_download = "";
}

error_log("[".basename(__FILE__).":".__LINE__."] image str: $str_image");
//-----------------------------------------------------------------------------
// Get HTML contents 
//-----------------------------------------------------------------------------
$html = str_replace("<!--QUESTION_LIST-->", $str_question_dropdown, $html);
$html = str_replace("<!--SUMMARY_RESULTS-->", $str_summary_table, $html);
$html = str_replace("<!--DETAIL_RESULTS-->", $str_detail_table, $html);
$html = str_replace("<!--RESULT_GRAPH-->", $str_image, $html);
$html = str_replace("<!--DOWNLOAD_LINK-->", $str_download, $html);

//-----------------------------------------------------------------------------
//	Display the Results
//-----------------------------------------------------------------------------
$title = "Results for Survey:". $survey_data['title'];
design_top($title);

echo $html;

design_bottom();

exit();



?>