<?php
//------------------------------------------------------------------------------
// Program Name: Survey Tool API	
// Aurthor Name: Christina Lee
// Create      Date: March 16, 2009	
// Last Modify Date:
// Description:	Survey Tool API
//------------------------------------------------------------------------------
DEFINE(SURVEY_DEBUG, 0);
DEFINE(LIB_ROOT, './MyLibPath/');
require_once(LIB_ROOT. "Util.php");
require_once(LIB_ROOT. "DB.php");

///////////////////////////////////////////////////////////////////////////////
// Survey Tool Class
///////////////////////////////////////////////////////////////////////////////
class Survey
{
	private $dbh;	
	private $isPersistant = 0;
	
	private $FLAG_SURVEY_DELETE   = 0x00000001;

	private $FLAG_QUESTION_DELETE = 0x00000001;

	private $FLAG_RESULT_DELETE   = 0x00000001;  
	private $FLAG_RESULT_LOCKED   = 0x00000002; 

	public $survey;
	public $survey_typs     = array("survey", "quiz");
	public $survey_fields   = array("id", "title", "intro_text", "wikis", "type", "anonymous", "locked", "flag", "ts");
	public $question_fields = array("id", "survey_id", "question", "question_type", "answers", "correct_answer", "rank", "flag", "ts");
	public $result_fields   = array("id", "survey_id", "question_id", "uid", "username", "channel", "answer", "correct", "flag", "ts");

	function __construct($db=null, $env='prod')
	{	
		if(is_null($db) )
		{
			if(SURVEY_DEBUG){ 
				error_log("[Survey.php:".__LINE__."] Create Database Connection ...");
			}			
			
			$db_object = new DB("localhost");
			$this->dbh = $db_object->DBConnect();

			if(!$this->dbh)
			{
				error_log("ERROR [".__FILE__.":".__LINE__."] DB::DBConnect() is failed.");
				return false;
			}
			
			$this->isPersistant = 0;		
			unset($db_object);
		}
		else
		{	
			if(SURVEY_DEBUG){ 
				error_log("[Survey.php:".__LINE__."] Using Existing Database Connection ... [$db]");
			}	
			$this->isPersistant = 1;
			$this->dbh = $db;
		}
		
		// get guidecomp database connection 
		if(!mysql_select_db("surveys", $this->dbh))
		{	
			error_log("ERROR [".__FILE__.":".__LINE__."] mysql_select_db() is failed [".mysql_error($this->dbh)."]");
			return false;
		}			
	}
	
	function __distruct() 
	{
		if(!$this->isPersistant){
			// close database connection
			if(!is_null($this->dbh)){
				mysql_close($this->dbh);
				$this->dbh = null;
			}			
		}
	}

	public function GetSurvey($survey_id, &$survey)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id < 1)
		{
			return -2;
		}
		
		$field_list = join(", ", $this->survey_fields);

		$query = "SELECT $field_list FROM Survey WHERE id='$survey_id' and (flag & ".$this->FLAG_SURVEY_DELETE.") != ".$this->FLAG_SURVEY_DELETE;
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }

		$result = mysql_query($query, $this->dbh);
		if(!$result)
		{
			return -3;
		}
		
		if(mysql_num_rows($result) != 1)
		{
			return -4;
		}

		$survey = mysql_fetch_assoc($result);	
				
		return 0;
	}
	
	public function GetAllSurveys(&$survey_data, $sort_field='', $sort_order='')
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
			
		if(strlen($sort_field) > 0)
		{
			$str_sort = "ORDER BY $sort_field $sort_order";
		}

		$field_list = join(", ", $this->survey_fields);

		$query = "SELECT $field_list FROM Survey WHERE (flag & ".$this->FLAG_SURVEY_DELETE.") != ".$this->FLAG_SURVEY_DELETE." $str_sort";
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }

		$result = mysql_query($query, $this->dbh);
		if(!$result)
		{
			return -2;
		}
		
		
		$num_rows = mysql_num_rows($result);
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "number of rows from above query: $num_rows"); }

		for($i = 0; $i < $num_rows; $i++)
		{
			$row = mysql_fetch_assoc($result);

			$survey_data[$i] = $row;
		}
				
		return 0;
	}


	public function SetSurvey($survey_data)
	{
		if(!$this->dbh)
		{
			return -1;
		}
				
		$dataExist = 0;
		$id = 0;

		if($survey_data['id'] > 0)
		{
			$ret_code = $this->GetSurvey($survey_data['id'], $old_data);
			if($ret_code == 0)
			{	
				for ($i = 0; $i < count($this->survey_fields) ; $i++)
				{
					if(strcmp($survey_data[$this->survey_fields[$i]] , $old_data[$this->survey_fields[$i]]) != 0)
					{
						$dataExist = 1;
						break;
					}
				}
				
				// $survey_data and $old_data are same case
				if(!$dataExist){
					return $survey_data['id'];
				}
			}
		}
		
		// set up fields array
		$update_fields = array();
		for ($i = 0; $i < count($this->survey_fields) ; $i++)
		{
			if(($this->survey_fields[$i] === 'ts') || ($this->survey_fields[$i] === 'id') || ($this->survey_fields[$i] === 'flag'))
			{
				continue;
			}
			
			$string = $this->survey_fields[$i]."='".mysql_escape_string($survey_data[$this->survey_fields[$i]])."'";
			array_push($update_fields, $string);
		}
		
		$str_update_fields = join(",", $update_fields);
	
		if($dataExist)
		{
			$query = "UPDATE Survey SET ".$str_update_fields." WHERE id='".$survey_data['id']."' ";
			$id = $survey_data['id'];
		}
		else
		{
			$query = "INSERT INTO Survey SET ".$str_update_fields;
			$id = 0;
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		if(!mysql_query($query, $this->dbh))
		{
			return -2;
		}
		
		if($id == 0)
		{
			$id = mysql_insert_id($this->dbh);
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "survey id: $id"); }

		return $id;
	}
	
	public function DeleteSurvey($survey_id)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id < 1)
		{
			return -2;
		}
		
		/* update survey flag for deletion */
		$query="UPDATE Survey SET flag= flag | ".$this->FLAG_SURVEY_DELETE." WHERE id='".$survey_id."' ";
		if(!mysql_query($query, $this->dbh))
		{
			return -3;
		}
		
		/* update quesiton flag for deletion */
		$query="UPDATE Question SET flag= flag | ".$this->FLAG_SURVEY_DELETE." WHERE survey_id='".$survey_id."' ";
		if(!mysql_query($query, $this->dbh))
		{
			return -3;
		}

		/* update result flag for deletion */
		$query="UPDATE Result SET flag= flag | ".$this->FLAG_SURVEY_DELETE." WHERE survey_id='".$survey_id."' ";
		if(!mysql_query($query, $this->dbh))
		{
			return -3;
		}

	}

	public function GetTotalNumQuestion($survey_id)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id == 0) 
		{
			return -2;
		}

		$query = "SELECT COUNT(*) FROM Question WHERE survey_id='".$survey_id."' AND flag = flag & ".$this->FLAG_QUESTION_DELETE;
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -3;
		}

		$row = mysql_fetch_row($result);
		if($row[0] > 0)
		{
			$total_questions = $row[0];
		}
		else
		{
			$total_questions = 0;
		}

		return $total_questions;
	}

	public function GetQuestion($question_id, &$question)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($question_id == 0) 
		{
			return -2;
		}
		
		/* check the existing data */
		$field_list = join(", ", $this->question_fields);
		$query = "SELECT $field_list FROM Question WHERE id='".$question_id."' AND (flag & ".$this->FLAG_QUESTION_DELETE.") != ".$this->FLAG_QUESTION_DELETE." ORDER BY rank, id ";
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -3;
		}
				
		if(mysql_num_rows($result) != 1)
		{
			if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "WARN", "number of rows from above query: $num_rows"); }
			return -4;
		}
		
		$question = mysql_fetch_assoc($result);
		
		return 0;
	}
	
	public function DeleteQuestion($question_id)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($question_id == 0) 
		{
			return -2;
		}

		$query = "UPDATE Question SET rank = -1, flag= flag | ".$this->FLAG_QUESTION_DELETE." WHERE id='".$question_id."' ";
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -3;
		}

		return 0;
	}

	public function GetAllQuestions($survey_id, &$question_data)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id == 0) 
		{
			return -2;
		}
		
		/* check the existing data */
		$field_list = join(", ", $this->question_fields);
		$query = "SELECT $field_list FROM Question WHERE survey_id='$survey_id' AND (flag & ".$this->FLAG_QUESTION_DELETE.") != ".$this->FLAG_QUESTION_DELETE." ORDER BY rank, ts, id ";
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -3;
		}
		
		$num_rows = mysql_num_rows($result);
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "number of rows from above query: $num_rows"); }

		for($i = 0; $i < $num_rows; $i++)
		{
			$row = mysql_fetch_assoc($result);

			$question_data[$i] = $row;
		}
		
		return 0;
	}
	
	
	public function UpdateQuestionRank($data)
	{	
		if(!$this->dbh)
		{
			return -1;
		}
		
		
		for($i = 0; $i < count($data); $i++)
		{
			$rank = $i+1;
			
			/* only updates where the rank value is different */
			if($rank != $data[$i]['rank'])
			{
				$query = "UPDATE Question SET rank='$rank' WHERE id='".$data[$i]['id']."' ";
				if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }

				$result = mysql_query($query, $this->dbh);
				if(!$result){
					return -3;
				}
			}
		}

		return 0;
	}

	public function SetQuestion($question_data)
	{
		if(!$this->dbh)
		{
			return -1;
		}
				
		$dataExist = 0;
		$id = 0;
		if($question_data['id'] > 0)
		{
			$ret_code = $this->GetQuestion($question_data['id'], $old_data);
			if($ret_code == 0)
			{	
				for ($i = 0; $i < count($this->question_fields) ; $i++)
				{
					if(strcmp($question_data[$this->question_fields[$i]] , $old_data[$this->question_fields[$i]]))
					{
						$dataExist = 1;
						break;
					}
				}
				
				// $question_data and $old_data are same case
				if(!$dataExist){
					return $question_data['id'];
				}
			}
		}
		
		// set up fields array
		$update_fields = array();
		for ($i = 0; $i < count($this->question_fields) ; $i++)
		{
			if(($this->question_fields[$i] === 'ts') || ($this->question_fields[$i] === 'id') || ($this->question_fields[$i] === 'flag'))
			{
				continue;
			}
			
			$string = $this->question_fields[$i]."='".mysql_escape_string($question_data[$this->question_fields[$i]])."'";
			array_push($update_fields, $string);
		}
		
		$str_update_fields = join(",", $update_fields);
	
		if($dataExist)
		{
			$query = "UPDATE Question SET ".$str_update_fields." WHERE id='".$question_data['id']."' ";
			$id = $question_data['id'];
		}
		else
		{
			$query = "INSERT INTO Question SET ".$str_update_fields;
			$id = 0;
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		if(!mysql_query($query, $this->dbh))
		{
			return -2;
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "question id: $id"); }
		if($id == 0)
		{
			if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "question id: $id"); }
			$id = mysql_insert_id();
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "question id: $id"); }

		return $id;		
	}


	public function GetResult($survey_id, $question_id, $username, &$result_data)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id == 0) 
		{
			return -2;
		}

		if($question_id == 0) 
		{
			return -3;
		}

		if(strlen($username) == 0) 
		{
			return -4;
		}
		
		/* check the existing data */
		$field_list = join(", ", $this->result_fields);
		$query = "SELECT $field_list FROM Result WHERE survey_id='$survey_id' AND question_id='$question_id' AND username='$username' ";
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -5;
		}
		
		$num_rows = mysql_num_rows($result);
		if($num_rows > 1)
		{
			if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "WARN", "number of rows from above query: $num_rows"); }
			return -6;
		}
		elseif($num_rows == 1)
		{
			$result_data = mysql_fetch_assoc($result);
		}
		else
		{
			$result_data['id'] = 0;
		}
		
		return $result_data['id'];
	}

	public function SetResult($result_data)
	{
		if(!$this->dbh)
		{
			return -1;
		}
				
		$dataExist = 0;
		$id = 0;

		$result_id = $this->GetResult($result_data['survey_id'], $result_data['question_id'], $result_data['username'], $old_data);
		if($result_id < 0)
		{	
			return -2;
		}
		elseif($result_id > 0)
		{			
			$dataExist = 1;			
		}
				
		// set up fields array
		$update_fields = array();
		for ($i = 0; $i < count($this->result_fields) ; $i++)
		{
			if(($this->result_fields[$i] === 'ts') || ($this->result_fields[$i] === 'id') || ($this->result_fields[$i] === 'flag'))
			{
				continue;
			}
			
			$string = $this->result_fields[$i]."='".mysql_escape_string($result_data[$this->result_fields[$i]])."'";
			array_push($update_fields, $string);
		}
		
		$str_update_fields = join(",", $update_fields);
	
		if($dataExist)
		{
			$query = "UPDATE Result SET ".$str_update_fields." WHERE id='".$result_id."' ";
			$id = $result_id;
		}
		else
		{
			$query = "INSERT INTO Result SET ".$str_update_fields;
			$id = 0;
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		if(!mysql_query($query, $this->dbh))
		{
			return -2;
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "result id: $id"); }

		if($id == 0)
		{
			$id = mysql_insert_id($this->dbh);
		}
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "result id: $id"); }

		return $id;

	}

	public function GetResultsByQuestion($survey_id, $question_id, &$results_data, $answer='', $sort)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id < 0)
		{	
			return -2;
		}
		
		if($question_id < 0)
		{	
			return -3;
		}
		
		if(strlen($answer) > 0)
		{
			$answer = trim($answer);
			$answer = str_replace("'", "\'", $answer);
			$answer_clause = " AND answer like '%".$answer."%'";
		}
		
		if(count($sort) > 0)
		{
			$sort_clause = "ORDER BY ".$sort['field']." ".$sort['direction'];
		}

		$field_list = join(", ", $this->result_fields);
		$query = "SELECT $field_list FROM Result WHERE survey_id='$survey_id' AND question_id='$question_id' AND (flag & ".$this->FLAG_RESULT_DELETE.") != ".$this->FLAG_RESULT_DELETE." $answer_clause $sort_clause";
		
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -5;
		}
		
		$num_rows = mysql_num_rows($result);
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "num_results: $num_rows"); }
		for($i = 0 ; $i < $num_rows; $i++)
		{
			$results_data[$i] = mysql_fetch_assoc($result);
		}
				
		return $num_rows;
	}
	
	public function LockSurveyResult($survey_id, $username)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id < 0)
		{	
			return -2;
		}
		
		if(strlen($username) < 1)
		{	
			return -3;
		}

		$query = "UPDATE Result SET flag = flag | ".$this->FLAG_RESULT_LOCKED." WHERE survey_id='$survey_id' AND username='$username'";
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -4;
		}

		return 0;
	}

	public function IsUserFinishedSurvey($survey_id, $username)
	{
		if(!$this->dbh)
		{
			return -1;
		}
		
		if($survey_id < 0)
		{	
			return -2;
		}
		
		if(strlen($username) < 1)
		{	
			return -3;
		}

		$query = "SELECT flag FROM Result WHERE survey_id='$survey_id' AND username='$username' LIMIT 1";
		if(SURVEY_DEBUG){ WriteLog("", __FILE__, __LINE__, time(), "DEBUG", "query: $query"); }
		$result = mysql_query($query, $this->dbh);
		if(!$result){
			return -4;
		}

		$row = mysql_fetch_assoc($result);

		if($row['flag'] & $this->FLAG_RESULT_LOCKED)
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}
}


?>
