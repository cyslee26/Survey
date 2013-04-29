<?php
require_once('/mnt/about/tools/editorial/lib/Util.php');

//-----------------------------------------------------------------------------
//	Form Queries ...
//-----------------------------------------------------------------------------
$survey_id = $_POST['survey_id'];
if($survey_id == "")
{
	$survey_id = 0;
}

$index = $_POST['q_index'];
if($index == "")
{
	$index = 0;
}

$qid = $_POST['q_id'];
if($qid == "")
{
	$qid = 0;
}

$next_qid = $_POST['q_next_id'];
if($next_qid == "")
{
	$next_qid = 0;
}

$isSurveyFinished = $_POST['survey_status'];
if($isSurveyFinished == "")
{
	$isSurveyFinished = 0;
}

$wiki_name = substr($_SERVER['SERVER_NAME'], 0, strpos($_SERVER['SERVER_NAME'], "."));

//--------------------------------------
//	Get User Answers
//--------------------------------------
$fields = array_keys($_POST);

$answer = "";
for ($i = 0; $i < count($fields); $i++)
{
	if(strncmp($fields[$i], "answer_", strlen("answer_")) == 0)
	{
		if($fields[$i] === "answer_checkbox")
		{	
			$answer = join("|", $_POST[$fields[$i]]);
		}
		else
		{
			$answer = $_POST[$fields[$i]];		
		}

		if(preg_match("/\[\[\[(.*)\]\]\]/i", $answer, $matches))
		{
			$open_answer = $_POST['answer_open'];
			$answer .= $open_answer;
		}
		
		break;
	}
}


//-----------------------------------------------------------------------------
//	Libraries ...
//-----------------------------------------------------------------------------
require_once(EDITORS_ROOT. 'tools/lib/Survey.php');
require_once(LOUNGE_ROOT. 'user.php');

if($wiki_name === "lounge")
{
	require_once(LOUNGE_ROOT.'design_functions.php');
}
else //haven and editors' wiki
{
	require_once(EDITORS_ROOT.'admin/'.$wiki_name. '/design_functions.php');
}

//--------------------------------------
//	create survey object
//--------------------------------------
$survey = new Survey($db, SERVER_ENV);
$ret_code = $survey->GetSurvey($survey_id, $survey_data);
if($ret_code < 0)
{	
	design_top("ERROR: Survey");
	echo '<P>Can not get Current Survey Information !! [ret_code: '.$ret_code.']';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Get User Information 
//--------------------------------------
$ret_code = $survey->GetUserInfo($user);
if($ret_code < 0)
{	
	design_top("ERROR: Survey");
	echo '<P>Can not get user information for save the survey results. !! [ret_code: '.$ret_code.']';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	Record the user inputs from form for logging
//-----------------------------------------------------------------------------
$log_file_name = LOG_EIDTORS_ROOT."survey/survey_".$survey_id."_".$user['uname'];
WriteLog("", $log_file_name, __LINE__, time(), "INFO", "Survey ID    : ".$survey_id);
WriteLog("", $log_file_name, __LINE__, time(), "INFO", "Question ID  : ".$qid);
WriteLog("", $log_file_name, __LINE__, time(), "INFO", "Username     : ".$user['uname']);
WriteLog("", $log_file_name, __LINE__, time(), "INFO", "Answer       : ".$answer);

if($isSurveyFinished){
	$survey_status_log = "Finished";
}
else{
	$survey_status_log = "Open";
}

WriteLog("", $log_file_name, __LINE__, time(), "INFO", "Survey Status: ".$survey_status_log);
//--------------------------------------
//	Set Up Results
//--------------------------------------
$result_data['survey_id']   = $survey_id;
$result_data['question_id'] = $qid;
$result_data['uid']         = $user['uid'];
$result_data['username']    = $user['uname'];
$result_data['channel']     = $user['channel'];
$result_data['answer']      = $answer;
$result_data['flag']        = 0;

//--------------------------------------
//	Validate Results
//--------------------------------------
if($result_data['survey_id'] < 1)
{
	design_top("ERROR: Survey");
	echo '<P>Invalid Survey ID value. Can not store the survey results [survey_id: '.$result_data['survey_id'].']';
	design_bottom();
	exit(0);
}

if($result_data['question_id'] < 1)
{
	design_top("ERROR: Survey");
	echo '<P>Invalid Question ID value. Can not store the survey results [survey_id: '.$result_data['question_id'].']';
	design_bottom();
	exit(0);
}

if((strlen($result_data['username']) < 1) || (strlen($result_data['channel']) < 1))
{
	design_top("ERROR: Survey");
	echo '<P>Invalid User Info value. Can not store the survey results [uname: '.$result_data['username'].'] [channel: '.$result_data['channel'].']';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Store the Survey Result
//--------------------------------------
$ret_code = $survey->SetResult($result_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Locked the Survey Result
//--------------------------------------
if($isSurveyFinished)
{
	$ret_code = $survey->LockSurveyResult($result_data['survey_id'], $result_data['username']);

	unset($survey);

	if($ret_code < 0)
	{
		design_top("ERROR: Survey");
		echo '<P>There is problem to finialized the Survey Results  [ret_code: '.$ret_code.']';
		design_bottom();
		exit(0);
	}
	else
	{
		$redirect_url = "http://".$_SERVER['SERVER_NAME'];
		design_top("Survey: ".$survey_data['title']);
		echo '
			<center>
			<P>Thanks for participating the survey.</p>
			<form name="result_saved">
			<p><input type="button" value="Close Window" onClick="window.close();"></p>
			</form>
			</center>
		  ';
		design_bottom();
		exit(0);
	}	
}


//--------------------------------------
//	Check for the last question saved
//--------------------------------------
$total_questions = $survey->GetTotalNumQuestion($result_data['survey_id']);

unset($survey);


if($total_questions == ($index+1))
{
	$redirect_url = "http://".$_SERVER['SERVER_NAME'];
	design_top("Survey: ".$survey_data['title']);
	echo '
			<center>
			<P>Your survey is saved now.</p>
			<form name="result_saved">
			<p><input type="button" value="Close Window" onClick="window.close();"></p>
			</form>
			</center>
		  ';
	design_bottom();
	exit(0);
}
else
{
	$next_index = $index+1;
	$url ="surveytake.php?survey_id=".$survey_id."&index=".$next_index."&qid=".$next_qid;
	echo '<meta http-equiv="refresh" content="0.5;url='.$url.'" />';
	exit(0);
}

?>