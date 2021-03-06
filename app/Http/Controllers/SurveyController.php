<?php

namespace App\Http\Controllers;

use App\Survey;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    /**
     * Show the selected survey
     *
     * @param int $id
     * @return Response
     */

    public function show($id)
    {
      $survey = Survey::with('questions')->findOrFail($id);
      if( $survey->active ) {
        return view('survey.form', ['survey'=> $survey]);
      } else {
        return view('survey.thanks', ['survey'=> $survey]);
      }
    }

    /**
     * Save a new survey
     * @param  Request $request Form request data
     * @return redirect to add question form
     */
    public function create(Request $request)
    {

      $this->validate($request, [
          'name' => 'required|max:255',
          'kiosk_mode' => 'boolean',
          'begin_at' => 'nullable|date',
          'end_at' => 'nullable|date'
      ]);

      $survey_details = $request->intersect([
        'name', 'description', 'return_url', 'css', 'thank_you_message',
        'slug', 'kiosk_mode', 'begin_at', 'end_at'
      ]);

      if(isset($survey_details['slug'])) {
        $survey_details['slug'] = str_replace(' ','_', $survey_details['slug']);
      }

      if(isset($survey_details['begin_at'])) {
        $survey_details['begin_at'] = date('Y-m-d H:i:s', strtotime($survey_details['begin_at']));
      }

      if(isset($survey_details['end_at'])) {
        $survey_details['end_at'] = date('Y-m-d H:i:s', strtotime($survey_details['end_at']));
      }

      //ok, we're valid, now to save form data as a new survey:
      $survey = Survey::create(
        $survey_details
      );
      return redirect('/addquestion/' . $survey->id . '#new-question-form');
    }

    /**
     * Save a new question for a survey
     * @param  Request $request submitted question form
     * @param  Survey  $survey  Survey to add the question to
     * @return view
     */
    public function addquestion(Request $request, Survey $survey)
    {
      $this->validate($request, [
          'label'=>'required|max:255',
          'options'=>'required_if:type,select,checkbox-list,section',
          'required'=>'boolean'
        ]);
      $data = array();
      $data = $request->intersect([
        'label', 'question_type', 'options', 'required', 'css_class'
      ]);
      /*
      $data['label'] = $request->input('label');
      $data['question_type'] = $request->input('type');
      if($request->has('options')) {
        $data['options'] = $request->input('options');
      }
      if($request->has('required') && $request->input('type') != 'section') {
        $data['required'] = '1';
      }
      */
      // return($data);
      $survey->questions()->create($data);
      return redirect('/addquestion/' . $survey->id . '#new-question-form');
    }

    /**
     * Post a response to a survey.
     *
     * @param  Request $request Form request data
     * @param  int  $id      survey to post a response to
     * @return redirect      redirects to success page or form w/errors
     */
    public function submit(Request $request, $id)
    {
      if($id != $request->input('survey_id')) {
        if($request->has('return_url')
            && strlen($request->input('return_url'))>5) {
           return response()
           ->header('Location', $request->input('return_url'));
        }
      }

      $survey = \App\Survey::find($id);
      $answerArray = array();
      $validationArray = array();
      $messageArray = array();

      //loop through questions and check for answers
      foreach($survey->questions as $question) {
        if($question->required)
        {
          $validationArray['q-' . $question->id] =  'required';
          $messageArray['q-' . $question->id . '.required'] = $question->label . ' is required';
        }
        if($request->has('q-' . $question->id)) {
          if(is_array($request->input('q-' . $question->id)) && count($request->input('q-' . $question->id))) {
            $answerArray[$question->id] = array(
              'value' => implode('|', $request->input('q-' . $question->id)),
              'question_id' => $question->id
            );
          } elseif ( strlen(trim($request->input('q-' . $question->id))) > 0) {

            $answerArray[$question->id] = array(
              'value'=> $request->input('q-' . $question->id),
              'question_id'=>$question->id
            );

          } // I guess there is an empty string
        }
      }



      $this->validate($request, $validationArray, $messageArray);

      //if no errors, submit form!
      if(count($answerArray) > 0) {
        $sr = new \App\SurveyResponse(['survey_id'=>$id, 'ip'=>$_SERVER['REMOTE_ADDR']]);
        $sr->save();
        foreach($answerArray as $qid => $ans) {
          // print_r($ans);
          $sr->answers()->create($ans);
        }
      }


        if($survey->return_url
            && strlen($survey->return_url)>5
            && !$survey->kiosk_mode ) {
           return redirect()->away($survey->return_url);
        } else {
          return redirect('thanks/' . $survey->id);
        }
    }

    /**
     * Show form to edit Survey
     * @param  Survey $survey
     * @return view
     */
    public function editSurveyForm(Survey $survey)
    {
      return view('survey.editsurvey', ['survey'=>$survey]);
    }

    public function editSurvey(Request $request, Survey $survey)
    {
      $this->validate($request, [
        'name'  =>  'required|max:255',
        'begin_at' => 'nullable|date',
        'end_at' => 'nullable|date'
      ]);

      $survey->name = $request->input('name');
      $survey->description = $request->input('description');
      $survey->css = strip_tags($request->input('css'));
      $survey->return_url = $request->input('return_url');
      $survey->thank_you_message = $request->input('thank_you_message');
      $survey->slug = str_replace(' ', '_', $request->input('slug'));
      if($request->input('begin_at') !== null ) {
        $survey->begin_at = date('Y-m-d H:i:s', strtotime($request->input('begin_at')));
      }
      if($request->input('end_at') !== null ) {
        $survey->end_at = date('Y-m-d H:i:s', strtotime($request->input('end_at')));
      }
      $survey->save();
      return redirect('list')->with('status',"Successfully edited Survey " . $survey->id);
    }
}
