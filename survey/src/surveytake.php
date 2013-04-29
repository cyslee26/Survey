<?php
//-----------------------------------------------------------------------------
//	Libraries & Contant Variables ...
//-----------------------------------------------------------------------------
require_once(LIB_ROOT . 'Util.php');

define(SURVEY_DEBUG, 0);
define(HTML, '../html/survey_take.htm');

//-----------------------------------------------------------------------------
//	Form Queries ...
//-----------------------------------------------------------------------------
$survey_id = $_GET[survey_id];
if($survey_id == "")
{
	$survey_id = 0;
}

$index = $_GET[index];
if($index == "")
{
	$index = 0;
}

$qid = $_GET[qid];
if($qid == "")
{
	$qid = 0;
}

$survey_status = $_GET[status];
if($survey_status == "")
{
	$survey_status = 0;
}

if(SURVEY_DEBUG) { 
	WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "[survey_id:$survey_id] [quesiton index:$index] [qid:$qid] [survey status: $survey_status]"); 
}

//-----------------------------------------------------------------------------
//	Libraries ...
//-----------------------------------------------------------------------------
require_once(LIB_ROOT . 'User.php');
require_once(LIB_ROOT . 'HTMLForm.php');
require_once(LIB_ROOT . 'Survey.php');

//-----------------------------------------------------------------------------
//	Validating the survey id
//-----------------------------------------------------------------------------
if(($survey_id == "") || ($survey_id < 1)) 
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	create survey object
//-----------------------------------------------------------------------------
$survey = new Survey($db, SERVER_ENV);

//-----------------------------------------------------------------------------
//	Get Survey Data
//-----------------------------------------------------------------------------
$ret_code = $survey->GetSurvey($survey_id, $survey_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>Couldn\t Get Survey Data [ret_code: '.$ret_code.'] ';
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	Validating the tool location 
//-----------------------------------------------------------------------------
$location = explode(",", $survey_data['wikis']);
if(!in_array($wiki_name, $location))
{
	design_top("ERROR: Survey ");
	echo '<P>Survey is not available for '.$wiki_name." wiki site";
	design_bottom();
	exit(0);
}

//-----------------------------------------------------------------------------
//	Validating the survey tool type  
//-----------------------------------------------------------------------------
if(SURVEY_DEBUG) { WriteLog("survey_quiz", __FILE__, __LINE__, time(), "DEBUG", "survey tool starts"); }
		
//---------------------------------------------
// Get Survey Questions 
//---------------------------------------------
$ret_code = $survey->GetAllQuestions($survey_id, $question_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo "<P>Can not get questions for the current survey !! [ret_code: $ret_code]";
	design_bottom();
	exit(0);
}
	
//--------------------------------------
//	Get User Information 
//--------------------------------------
$ret_code = User::GetUserInfo($user);
if($ret_code < 0)
{	
	design_top("ERROR: Survey");
	echo '<P>Can not get user information for save the survey results. !! [ret_code: '.$ret_code.']';
	design_bottom();
	exit(0);
}
	
//--------------------------------------
//	Checking the user finished 
//  the current survey
//--------------------------------------
$ret_code = $survey->IsUserFinishedSurvey($survey_id, $username);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>There is problem to get the current user\' s survey status.  [ret_code: $ret_code]';
	design_bottom();
	exit(0);
}
elseif($ret_code == 1)
{
	design_top("Survey: ".$survey_data['title']);
	echo '<h3> You are already finished the current survey. </h3>';
	design_bottom();
	exit(0);
}

//--------------------------------------
//	Get Survey Result for current user
//--------------------------------------
$ret_code = $survey->GetResult($survey_id, $question_data[$index]['id'], $user['uname'], $result_data);
if($ret_code < 0)
{
	design_top("ERROR: Survey");
	echo '<P>No such quiz or survey exists.';
	design_bottom();
	exit(0);
}
	
//---------------------------------------------
// Get HTML contents 
//---------------------------------------------
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
	
//---------------------------------------------
// Set Up HTML Replacement Strings 
//---------------------------------------------
$total_num_questions = count($question_data);
$current_display_index = $index + 1;
		
/* set up intro text string */
if($index == 0)
{
	$str_intro_text = "<tr><td>".$survey_data['intro_text']."</td></tr>\n";
	$str_intro_text .= "<tr><td>&nbsp;</td></tr>\n";
}
else
{	
	$str_intro_text = "";
}
$html = str_replace("<!--SURVEY_INTRO_TEXT-->", $str_intro_text, $html);
	
/* set up current question */
$str_current_question = $current_display_index . ". ".$question_data[$index]['question'];
$html = str_replace("<!--SURVEY_CURR_QUESTION-->",$str_current_question , $html);

/* set up the answers list */
$str_answer_list = GetAnswerListStatment($question_data[$index]['question_type'], $question_data[$index]['answers'], $result_data['answer']);
$html = str_replace("<!--SURVEY_ANSWER_LIST-->", $str_answer_list, $html);
	
/* Button Replacement */
if($index < ($total_num_questions -1))
{
	$next_index = $index+1;
	$str_skip_button = '<input type="button" value="Skip Question" style="background: yellow; color: black" onClick="window.location.href=\'surveytake.php?survey_id='.$survey_id.'&index='.$next_index.'&qid='.$question_data[$next_index]['id'].'\' ">';
	$str_submit_button = '<input type="submit" value="Vote" style="background: green; color: white" >';
}
else
{
	$next_index = 0;
	$str_skip_button   = '<input type="submit" value="Save Survey" style="background: yellow; color: black" >';
	$str_submit_button = '<input type="button" value="Finish Survey" style="background: green; color: white" onClick="SaveSurveyResult(\'survey_status\', 1) ">';
}
	
$html = str_replace("<!--SURVEY_SKIP_BUTTON-->", $str_skip_button, $html);
$html = str_replace("<!--SURVEY_SUBMIT_BUTTON-->", $str_submit_button, $html);

/* set up the survey name for gray box */
$str_survey_name = $survey_data['title']. ": All Questions";
$html = str_replace("<!--SURVEY_TITLE-->", $str_survey_name, $html);

/* set up question list */	
$str_questions = GetQuestionListStatment($index, $survey_id, $user['uname'], $question_data, $survey);

$html = str_replace("<!--QUESTION_LIST-->", $str_questions, $html);

/* set up hidden field */
$html = str_replace("<!--SURVEY_ID-->", $survey_id, $html);
$html = str_replace("<!--QUESTION_NEXT_ID-->", $question_data[$next_index]['id'], $html);
$html = str_replace("<!--QUESTION_ID-->", $question_data[$index]['id'], $html);
$html = str_replace("<!--QUESTION_INDEX-->", $index, $html);

//---------------------------------------------
// Set Up Page Displaying.
//---------------------------------------------
$page_title  = $survey_data['title']."(Question: ".$current_display_index." of ".$total_num_questions.")";

design_top($page_title);
echo $html;
design_bottom();

unset($survey);
exit(0);


//-----------------------------------------------------------------------------
//	Function Section ....
//-----------------------------------------------------------------------------
function GetAnswerListStatment($answer_type, $answer_string, $user_answer="")
{
	if(strlen($answer_string) < 1)
	{
		return "";
	}
	
	/* trim the user answer */
	$user_answer = trim($user_answer);
	
	/* trim the answer list */
	$answer_list = explode("|", $answer_string);
	for($i = 0; $i < count($answer_list); $i++)
	{
		$answer_list[$i] = trim($answer_list[$i]);
	}
	
	/* trim the user answer list */

	$str_answer_list = "<table>";
	
	if($answer_type === "multiple_choice")
	{
		for($i = 0; $i < count($answer_list); $i++)
		{	
			$checked = "";

			if(preg_match("/\[\[\[(.*)\]\]\](.*)/i", $answer_list[$i], $matches))
			{
				$answer = $matches[1]." ".$matches[2];
				$isOpenEnd = 1;
				
				$open_text = strstr($user_answer, $answer_list[$i]);
				if($open_text == false)
				{
					$checked = "";
					$user_open_text = "";
				}
				else
				{
					$checked = "checked";
					$user_open_text = substr($open_text, strlen($answer_list[$i]));
				}
			}
			else
			{
				$answer = $answer_list[$i];
				$isOpenEnd = 0;
				if($answer == $user_answer)
				{
					$checked = "checked";
				}
			}			

			$str_answer_list .= "<tr>";
			$str_answer_list .= "<td width='10px'>&nbsp;</td>";
			$str_answer_list .= "<td><input type='radio' name='answer_radio' value=\"".$answer_list[$i]."\" $checked></td>";
			$str_answer_list .= "<td>".$answer."</td>";
			$str_answer_list .= "</tr>\n";

			if($isOpenEnd)
			{		
				$str_answer_list .= "<tr>";
				$str_answer_list .= "<td width='10px'>&nbsp;</td>";
				$str_answer_list .= "<td>&nbsp;</td>";
				$str_answer_list .= "<td><textarea name='answer_open' style='width:300; height: 50'>".$user_open_text."</textarea></td>";
				$str_answer_list .= "</tr>\n";
			}		
		}
	}
	elseif($answer_type === "pulldown")
	{
		$str_answer_list .= "<tr>\n";
		$str_answer_list .= "<td width='10px'>&nbsp;</td>\n";
		$str_answer_list .= "<td colspan='2'><select name='answer_pulldown'>\n";
		for($i = 0; $i < count($answer_list); $i++)
		{
			if($answer_list[$i] === $user_answer)
			{
				$checked = "selected";
			}
			else
			{
				$checked = "";
			}

			$str_answer_list .= "<option value=\"".$answer_list[$i]."\" ".$checked." >".$answer_list[$i]."</option>\n";
		}

		$str_answer_list .= "</td></tr>\n";
	}
	elseif($answer_type === "checkbox")
	{
		/* parse user answers */
		$user_answer_list = explode("|", $user_answer);
		
		for($i = 0; $i < count($user_answer_list); $i++)
		{
			$user_answer_list[$i] = trim($user_answer_list[$i]);
		}

		for($i = 0; $i < count($answer_list); $i++)
		{
			if(preg_match("/\[\[\[(.*)\]\]\](.*)/i", $answer_list[$i], $matches))
			{
				$answer = $matches[1]." ".$matches[2];
				$isOpenEnd = 1;
				
				$open_text = strstr($user_answer, $answer_list[$i]);
				if($open_text == false)
				{
					$checked = "";
					$user_open_text = "";
				}
				else
				{
					$checked = "checked";
					$user_open_text = substr($open_text, strlen($answer_list[$i]));
				}
			}
			else
			{
				$answer = $answer_list[$i];
				$isOpenEnd = 0;
				if(in_array($answer, $user_answer_list))
				{
					$checked = "checked";
				}
				else
				{
					$checked = "";
				}
			}
						
			$str_answer_list .= "<tr>";
			$str_answer_list .= "<td width='10px'>&nbsp;</td>";
			$str_answer_list .= "<td><input type='checkbox' name='answer_checkbox[]' value=\"".$answer_list[$i]."\" $checked></td>";
			$str_answer_list .= "<td>".$answer."</td>";
			$str_answer_list .= "</tr>\n";

			if($isOpenEnd)
			{		
				$str_answer_list .= "<tr>";
				$str_answer_list .= "<td width='10px'>&nbsp;</td>";
				$str_answer_list .= "<td>&nbsp;</td>";
				$str_answer_list .= "<td><textarea name='answer_open' style='width:300; height: 50'>".$user_open_text."</textarea></td>";
				$str_answer_list .= "</tr>\n";
			}	
		}
	}
	elseif($answer_type === "open_ended")
	{
		// usually open ended don't have value in answers
		$str_answer_list .= "<tr>";
		$str_answer_list .= "<td width='10px'>&nbsp;</td>";
		$str_answer_list .= "<td colspan='2'><textarea name='answer_open' style='width:500; height: 100'>".$user_answer."</textarea></td>";
		$str_answer_list .= "</tr>\n";
	}
	else
	{
		error_log("[".__FILE__.":".__LINE__."] Invalid Question Type [$answer_type]\n");
		return "";
	}

	$str_answer_list .= "</table>";

	return $str_answer_list;
}

function GetQuestionListStatment($index, $survey_id, $username, $question_data, $survey)
{
	if(count($question_data) < 1)
	{
		return "";
	}

	$str_questions = "<table>";
		
	for($i = 0; $i < count($question_data); $i++)
	{	
		/*	Get Survey Result for current user & question */		
		$ret_code = $survey->GetResult($survey_id, $question_data[$i]['id'], $username, $result_data);
		if($ret_code < 0)
		{
			design_top("ERROR: Survey");
			echo '<P>No such quiz or survey exists.';
			design_bottom();
			exit(0);
		}
		
		$str_questions .= "<tr>";
		if($i == $index)
		{
			$str_questions .= '<td><img src="/graphics/arrow_bullet.gif" width="13" height="10"></td>';
			$str_questions .= '<td valign="baseline" style="font-size: 8pt; font-weight: bold;">'.$question_data[$i]['question']."</td>";	
		}
		else
		{
			if($result_data['id'] > 0)
			{
				$image_url = '<img src="/graphics/check_green.gif" width="13" height="10">';
			}
			else
			{
				$image_url = $i + 1;
			}
			
			$str_questions .= "<td>".$image_url."</td>\n";
			$str_questions .= '<td valign="baseline" style="font-size: 8pt;"><a href="surveytake.php?survey_id='.$survey_id.'&index='.$i.'&qid='.$question_data[$i]['id'].'" >'.$question_data[$i]['question']."</a></td>\n";		
		}
		$str_questions .= "</tr>\n";
	}
	
	$str_questions .= "</table>";

	return $str_questions;
}
?>
