<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clee
 * Date: 4/29/13
 * Time: 5:27 PM
 * To change this template use File | Settings | File Templates.
 */

class SurveyViewUtil {
    static public function DisplayResultsInTable($survey_data, $survey)
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

    static public function BuildResultsInArray($survey, $survey_id, $question_id, &$summary_results, &$detail_results)
    {
        if(!$survey){
            return -1;
        }

        /* Get Question Data */
        $ret_code = $survey->GetQuestion($question_id, $question_data);
        if($ret_code < 0)
        {
            return -2;
        }

        /* parse the answers */
        $answers = explode("|", $question_data['answers']);

        if(count($answers)== 0)
        {
            return 0 ;
        }

        /* set up sort option for data */
        $sort['field'] = "answer";
        $sort['direction'] = "asc";

        /* Get all total Results for current questions */
        $total_results = $survey->GetResultsByQuestion($survey_id, $question_id, $detail_results, "", $sort);
        if($total_results < 0)
        {
            return -3;
        }

        for ($i = 0, $k = 0; $i < count($answers); $i++)
        {
            $num_results = $survey->GetResultsByQuestion($survey_id, $question_id, $results_data, $answers[$i], $sort);

            if($total_results > 0)
            {
                $percentage = $num_results / $total_results * 100;
                $percentage = round($percentage, 2);
            }
            else
            {
                $percentage = 0;
            }

            /* openend answer doesn't count in percentage */
            if(preg_match("/\[\[\[(.*)\]\]\](.*)/i", $answers[$i], $matches))
            {
                $answer_text =  $matches[1]." ".$matches[2];
                $num_results = 0;
                $percentage = 0;
            }
            else
            {
                $answer_text = $answers[$i];
            }

            if($num_results > 0)
            {
                $summary_results[$k]['Answer']     = $answer_text;
                $summary_results[$k]['Count']      = $num_results;
                $summary_results[$k]['Percentage'] = $percentage;
                $k++;
            }
        }

        return 0;
    }


    static public function BuildResultsInTable($data, $columns, $showTotal)
    {
        $str_table = "";

        if(count($columns) < 0)
        {
            return "";
        }

        $str_table = '
			<table border="0" cellpadding="5">
				<tr class="red_header">
				';

        /* build table header row*/
        for($i = 0 ; $i < count($columns); $i++)
        {
            if($columns[$i] === 'username')
            {
                $col_name = "Respondent";
            }
            else
            {
                $col_name = ucfirst($columns[$i]);
            }

            $str_table .= '<td nowrap><b>'.$col_name.'</b></td>';
        }

        $str_table .= '</tr>';

        /* build rows */
        for($i = 0; $i < count($data); $i++)
        {
            if($i % 2 == 1)
            {
                $row_color = 'dark_gray_table';
            }
            else
            {
                $row_color = 'light_gray_table';
            }

            $str_table .= '<tr class="'.$row_color.'">';

            for($k = 0 ; $k < count($columns); $k++)
            {
                $str_table .= '<td nowrap>'.$data[$i][$columns[$k]].'</td>';
            }

            $str_table .= '</tr>';
        }

        if($showTotal)
        {
            if(count($data) % 2 == 1)
            {
                $row_color = 'dark_gray_table';
            }
            else
            {
                $row_color = 'light_gray_table';
            }

            $str_table .= "<tr class='".$row_color."'>
					<td><strong>Total Respondent</strong></td>
					<td> <strong>".count($data)."</strong></td>
					</tr>";
        }

        $str_table .= "</table>";

        return $str_table;
    }
    static public function GetAnswerListStatment($answer_type, $answer_string, $user_answer="")
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

    static public function GetQuestionListStatment($index, $survey_id, $username, $question_data, $survey)
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
}