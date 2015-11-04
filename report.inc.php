<?php

//////////////////////////////////////////////////////////////////////////////
// Function: rpt_run()
//
// Description:
//   Returns the output for this report.
//   It will first get the DATA for the report by executing whatever code gathers
//   data used by the report.  This is handled by the rpt_get_data() function.
//   It will then pass that data to the appropriate output generator.
//
//   A rpt_output_XYZ() function should be written for each type of output format
//   you want to support.  The data from rpt_get_data will be used by this function.
//
//   IN GENERAL, YOU SHOULD NOT NEED TO EDIT THIS FUNCTION
//
//////////////////////////////////////////////////////////////////////////////
function rpt_run($form, $output_format='html') {

    $status=0;

    // See if the output function they requested even exists
    $func_name = "rpt_output_{$output_format}";
    if (!function_exists($func_name)) {
        $rptoutput = "ERROR => This report does not support an '{$form['format']}' output format.";
        return(array(1,$rptoutput));
    }

    // if we are looking for the usage, skip gathering data.  Otherwise, gather report data.
    if (!$form['rpt_usage']) list($status, $rptdata) = rpt_get_data($form);

    if ($status) {
        $rptoutput = "ERROR => There was a problem getting the data. {$rptdata}";
    }
    // Pass the data to the output type
    else {
        // If the rpt_usage option was passed, add it to the gathered data
        if ($form['rpt_usage']) $rptdata['rpt_usage'] = $form['rpt_usage'];

        // Pass the data to the output generator
        list($status, $rptoutput) = $func_name($rptdata);
        if ($status)
            $rptoutput = "ERROR => There was a problem getting the output: {$rptoutput}";
    }

    return(array($status,$rptoutput));
}



//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////START EDITING BELOW////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





//////////////////////////////////////////////////////////////////////////////
// Function: rpt_html_form()
//
// Description:
//   Returns the HTML form text for this report.
//   This is used by the display report code to present an html form to
//   the user.  This simply provides a gui to gather all the input variables.
//////////////////////////////////////////////////////////////////////////////
function rpt_html_form($report_name, $rptform='',$rptjs='') {
    global $images, $color, $style;
    $rpthtml = '';
    $rptjs = '';

    // In addition to this form, you can provide usage information here if desired
    $rpthtml .= <<<EOL

<div style="{$style['content_box']}">
    <form id="{$report_name}_report_form" onsubmit="el('rpt_submit_button').onclick(); return false;">
        <input type="hidden" value="{$report_name}" name="report"/>
        <input id="date" name="date" value="{$rptform['date']}" class="edit" type="text" size="15" />
        <input type="submit"
                id="rpt_submit_button"
                title="Search"
                value="Run Report"
                class="act"
                onClick="el('report_content').innerHTML='<br><center><img src={$images}/loading.gif></center><br>';xajax_window_submit('display_report', xajax.getFormValues('{$report_name}_report_form'), 'run_report');"
        />
        <input class="act" type="button" name="reset" value="Clear" onClick="clearElements('{$report_name}_report_form');">
    </form>
</div>


EOL;


    if (!$rptform['date']) $rptjs .= "el('date').value='". date('Y-m-d') . "';";


    return(array(0,$rpthtml,$rptjs));

}


// Gather data for this report and store it in the $rptdata array for use by the output generation functions
function rpt_get_data($form) {

    // Run our existing sql code
    list($status, $rptdata) = run_module('ona_sql', array('sql' => 'check_last_response.sql', 'commit' => 'N', 'dataarray' => 'Y', '1' => $form['date']));

    if ($status) {
        return(array(1,"Unable to execute query, check that 'check_last_response.sql' exists in the SQL directory"));
    }

    $rptdata['global']['date'] = $form['date'];
    $rptdata['global']['rpt_usage'] = $form['rpt_usage'];


    return(array(0,$rptdata));
}












// Take the data gathered in the rpt_get_data function and format it as desired for HTML ouput
function rpt_output_html($form) {
    global $style;

    if (!$form['global']['date']) {
        $text .= "Please enter a date above and hit submit.";
        return(array(0,$text));
    }

    $rptoutput .= <<<EOL
Hosts that have not responded prior to {$form['global']['date']}.<br><br>
    <table class="list-box" cellspacing="0" border="0" cellpadding="0" style="margin-bottom: 0;">
            <!-- Table Header -->
            <tr>
                <td class="list-header" align="left">Host information</td>
                <td class="list-header" align="center">Last Response Time</td>
            </tr>
    </table>
    <div id="check_last_response" style="overflow: auto; width: 100%; height: 89%;border-bottom: 1px solid;">
        <table class="list-box" cellspacing="0" border="0" cellpadding="0">
EOL;

    // Print our data
    foreach ($form as $record) {
        //print_r($record);
        if ($record['last_response']) $rptoutput .= <<<EOL
                <tr onMouseOver="this.className='row-highlight'" onMouseOut="this.className='row-normal'">
                    <td class="list-row" align="left">
                        {$record['fqdn']}
                    </td>
                    <td class="list-row" align="left" style="{$style['borderR']};">
                        <a class="act"
                           title="Click to view host."
                           onclick="xajax_window_submit('work_space', 'xajax_window_submit(\'display_host\', \'host=>{$record['ip']}\', \'display\')');">{$record['ip']}</a>
                    </td>
                    <td class="list-row" align="left">{$record['last_response']}</td>
                </tr>
EOL;

    }


    $rptoutput .= "</table><br><center>END OF REPORT</center></div>";

    return(array(0,$rptoutput));
}









// Take the data gathered in the rpt_get_data function and format it as desired for text ouput
function rpt_output_text($form) {

//echo hello;

    // Provide a usage message here
    $usagemsg = <<<EOL
Report: check_last_response
  Displays a list of hostnames with the IPs that have not responded
  since prior to the specified date.

  Required:
    date=YYYY-MM-DD    Date to search last responses from

  Output Formats:
    html
    text

EOL;

    // Provide a usage message
    if ($form['global']['rpt_usage'] or !$form['global']['date']) {
        return(array(0,$usagemsg));
    }

    $rptoutput .= "\nHosts that have not responded prior to {$form['global']['date']}.\n\n";
    $rptoutput .= sprintf("%-30s %-15s %s\n", 'FQDN', 'IP', 'Last Response Time');

    // Print our data
    foreach ($form as $record) {
        //print_r($record);
        $rptoutput .= sprintf("%-30s %-15s %s\n", $record['fqdn'], $record['ip'], $record['last_response']);
    }

    $rptoutput .= "END OF REPORT.\n";

    return(array(0,$rptoutput));
}

?>
