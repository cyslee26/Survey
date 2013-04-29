<?php
//------------------------------------------------------------------------------
// Program Name    :	
// Aurthor Name    :	
// Create      Date:	
// Last Modify Date:
// Description     :	
//------------------------------------------------------------------------------

require_once('../lib/User.php');
require_once('../lib/Survey.php');

//--------------------------------------
//	Contant Variables ...
//--------------------------------------
define(SURVEY_DEBUG, 0);
define(HTML, '../html/download_result.htm');

//--------------------------------------
//	Form Queries ...
//--------------------------------------
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

//--------------------------------------
//	Validating the survey id
//--------------------------------------
if(($survey_id == "") || ($survey_id < 1)) 
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Create survey objects 
//--------------------------------------
$survey  = new Survey($db);

$ret_code = $survey->GetSurvey($survey_id, $survey_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Get Results 
//--------------------------------------
/* set up sort option for data */
$sort['field'] = "ts";
$sort['direction'] = "desc";

if($question_id > 0)
{
	/* Get Question Data */
	$ret_code = $survey->GetQuestion($question_id, $question_data);
	if($ret_code < 0)
	{
		return -2;
	}
		
	$total_results = $survey->GetResultsByQuestion($survey_id, $question_id, $result_data, "", $sort);
	if($total_results < 0)
	{
		design_top("ERROR: Survey");
		echo '<p>Can not get survey results [survey_id: $survey_id] [question_id: $question_id] [ret_code: $ret_code]</p><br>';
		design_bottom();
		exit(0);
	}
	
	$csv_fname = "../download/survey_".$survey_id."_".$question_id.".csv";
	
	$fp = fopen($csv_fname, "w+");
	
	//csv header
	$csv_header[0] = "Respondent";
	$csv_header[1] = "Answer (".$question_data['question'].") ";
	
	fwrite($fp, $survey_data['title']." Results\n");
	fputcsv($fp, $csv_header);
	
	for($i = 0; $i < count($result_data); $i++)
	{	
		$csv_data[0] = $result_data[$i]['username'];
		$csv_data[1] = $result_data[$i]['answer'];
		fputcsv($fp, $csv_data);
	}
	
	fclose($fp);
}
else // All the results 
{
	$ret_code = $survey->GetAllQuestions($survey_id, &$question_data);
	if($ret_code < 0)
	{
		design_top("ERROR: Survey");
		echo '<p>Can not get list of question in order to get the results [survey_id: $survey_id] [ret_code: $ret_code]</p><br>';
		design_bottom();
		exit(0);
	}
	
	
	$csv_header[0] = "Respondent";

	for($i = 0 ; $i < count($question_data); $i++)
	{
		$qid = $question_data[$i]['id'];
		$qid_list[$i] = $qid;
		
		$q_index = $i+1;

		$csv_header[$q_index] = "Q".$q_index.". ".substr($question_data[$i]['question'], 0, 35);
		if(strlen($question_data[$i]['question']) > 35)
		{
			$csv_header[$i+1] .= "...";
		}
		
		$result_data = array();
		$total_results = $survey->GetResultsByQuestion($survey_id, $qid, $result_data, "", $sort);
		if($total_results < 0)
		{
			design_top("ERROR: Survey");
			echo '<p>Can not get survey results [survey_id: $survey_id] [question_id: $question_id] [ret_code: $ret_code]</p><br>';
			design_bottom();
			exit(0);
		}
		
		for ($k = 0; $k < $total_results; $k++)
		{
			$username = $result_data[$k]['username'];	
			$answer   = $result_data[$k]['answer'];
			if(preg_match("/\[\[\[(.*)\]\]\](.*)/i", $answer, $matches))
			{
				$all_result[$username][$qid]['answer'] = $matches[2];
			}
			else
			{
				$all_result[$username][$qid]['answer'] = $answer;
			}
			
			if(SURVEY_DEBUG){
				if($username=== "golondon")
				{
					error_log("[$k] [user:$username] [qid:$qid] answer: ". $all_result[$username][$qid]['answer']);
				}
			}
		}

		unset($result_data);
	}
	
	ksort($all_result);
	$respondents = array_keys($all_result);
	$num_questions = count($qid_list);
	
	/* csv section */
	$csv_fname = "../download/survey_".$survey_id."_".date('Y-m-d').".csv";
	
	$fp = fopen($csv_fname, "w+");
	
	//csv header
	fwrite($fp, $survey_data['title']." Results\n");
	fputcsv($fp, $csv_header);
	
	
	for($i = 0; $i < count($respondents); $i++)
	{
		$csv_data = array();
		array_push($csv_data, $respondents[$i]);

		for ($k = 0; $k < $num_questions; $k++)
		{
			array_push($csv_data,  $all_result[$respondents[$i]][$qid_list[$k]]['answer']);
		}
		
		fputcsv($fp, $csv_data);
		unset($csv_data);			
	}

	fclose($fp);	
}


// get the file contents for csv 
$fp = fopen($csv_fname, "r");
$csv =  fread($fp, filesize($csv_fname));
fclose($fp);

header('Content-type: application/octet-stream');
header("Content-disposition: attachment; filename=output.csv"); 
header("Expires: 0"); 
header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 

echo $csv;


?>