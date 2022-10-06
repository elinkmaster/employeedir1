<?php

/**
 * The SurveyResultsController class is a Controller that shows survey results
 * in a table and allows a user to download a csv of the results.
 *
 * @author David Barnes
 * @copyright Copyright (c) 2013, David Barnes
 */
class ManageResultsController extends Controller
{
    /**
     * Handle the page request.
     *
     * @param array $request the page parameters from a form post or query string
     */
    protected function handleRequest(&$request)
    {
        $array = ['form_id' => 14];

        if(isset($request['del']) && $request['del'] > 0):
            SURVEY::markSurvey($request['del'],$this->pdo);
        endif;

        $survey = $this->getSurvey($array);
        $responses = $survey->getSurveyResponses($this->pdo);
        $this->assign('survey', $survey);

        if (isset($request['action'])) {
            $this->handleAction($request, $survey);
        }
    }

    /**
     * Handle a user submitted action.
     *
     * @param array  $request the page parameters from a form post or query string
     * @param Survey $survey  the survey object containing the questions and the survey responses
     */
    protected function handleAction(&$request, Survey $survey)
    {
        switch ($request['action']) {
            case 'download_csv':
                $this->downloadCsv($survey);
                break;
        }
    }

    /**
     * Download a CSV formatted file of the survey responses, encoded in WINDOWS-1252 for Excel compatibility.
     *
     * @param Survey $survey the survey object containing the questions and the survey responses
     */
    protected function downloadCsv(Survey $survey)
    {
        header('Pragma: private');
        header('Cache-control: private, must-revalidate');
        header('Content-type: text/csv');
        $csvFileName = preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', $survey->survey_name));
        header('Content-Disposition: attachment; filename=' . $csvFileName . '.csv');

        $fp = fopen('php://output', 'w');

        $headers = [];
        foreach ($survey->questions as $question) {
            $headers[] = iconv('UTF-8', 'WINDOWS-1252', $question->question_text);
        }
        $headers[] = iconv('UTF-8', 'WINDOWS-1252', 'Time Taken');

        fputcsv($fp, $headers);

        foreach ($survey->responses as $response) {
            $row = [];
            foreach ($survey->questions as $question) {
                $field = 'question_' . $question->question_id;
                $row[] = iconv('UTF-8', 'WINDOWS-1252', $response->$field);
            }
            $row[] = $this->formatDate($response->time_taken);
            fputcsv($fp, $row);
        }

        fclose($fp);
        exit;
    }

    /**
     * Query the database for a survey_id.
     *
     * @param array $request the page parameters from a form post or query string
     *
     * @return Survey $survey returns a Survey object
     *
     * @throws Exception throws exception if survey id is not specified or not found
     */
    protected function getSurvey(&$request)
    {
        if (! empty($request['form_id'])) {
            $survey = Survey::queryRecordById($this->pdo, $request['form_id']);
            if (! $survey) {
                throw new Exception('Form ID not found in database');
            }
        } else {
            throw new Exception('Form ID must be specified');
        }

        return $survey;
    }

    protected function formatDate($date) {
        $date = new DateTime($date, new DateTimeZone('GMT'));
        $date->setTimezone(new DateTimeZone('Asia/Manila'));
        return $date->format('Y-m-d H:i:s');
    }

}
