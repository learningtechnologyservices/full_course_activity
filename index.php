<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Sample report
 *
 * @package     report
 * @subpackage  full_course_activity
 * @author      Shuai Zhang
 * @copyright   2013 Shuai Zhang <shuaizhang@lts.ie>
 */

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');


global $DB;

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id'=>$courseid));
$coursecontext = context_course::instance($courseid);

if (!$course){
    print_error('invalidcourse', 'report_full_course_activity', $courseid);
}
require_login($course);
if (!has_capability('report/full_course_activity:view', $coursecontext)){
    echo 'Only teacher can view this report.';
    close();
}

$strtitle = get_string('pluginname', 'report_full_course_activity');

$PAGE->set_context($coursecontext);
$PAGE->set_url('/report/full_course_activity/index.php',array('id' => $courseid));
$PAGE->set_pagelayout('report');
$PAGE->set_title($course->shortname .': '. $strtitle);
$PAGE->set_heading(format_string($course->fullname));

$table = new html_table();
$row   = new html_table_row();
$cell  = new html_table_cell();

$warning_a = "";
$warning_s = "";

$table->cellpadding = 15;
$table_head = array('Activity');

// sql query for getting students from the course
$get_students_sql = "SELECT u.firstname AS firstname, u.lastname AS lastname, c.id AS courseid, u.id AS userid
                       FROM {course} AS c
                            JOIN {context} AS ctx         ON c.id = ctx.instanceid
                            JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                            JOIN {user} AS u              ON u.id = ra.userid
                      WHERE c.id = $courseid AND ra.roleid = 5";
$students = $DB->get_records_sql($get_students_sql);
$user_id = array();

// put students into an array which will be print as column title
if ($students != NULL) {
    foreach ($students as $student) {   // put student's name into column title
        $student_fullname = ($student -> firstname).'.'.($student -> lastname);
        array_push($table_head, $student_fullname);
        array_push($user_id, $student -> userid);
    }
} else {
    $warning_s = "Warning: there is no student enrolled in this course. ";
}

// print students' full name into table as heading of each column
$table -> head = $table_head;

// sql query for getting each student's entry record for each activity
$get_entries_sql = "SELECT cm.id, COUNT('x') AS entries
          FROM {course_modules} AS cm
               JOIN {modules} AS m ON m.id   = cm.module
               JOIN {log}     AS l ON l.cmid = cm.id
               JOIN {user}    AS u ON u.id   = l.userid
         WHERE cm.course = $courseid AND l.action LIKE 'view%' AND m.visible = 1 AND u.id = ?
      GROUP BY cm.id";
$all_entries = array();

// gather all the students' entry records
foreach ($user_id as $userid) { 
    $num_entries = $DB->get_records_sql($get_entries_sql,array($userid));
    array_push($all_entries, $num_entries);
}

$mod_info = get_fast_modinfo($course);
foreach ($mod_info->sections as $section) {
    foreach ($section as $cmid) {
        $cm = $mod_info->cms[$cmid];
        if (!$cm->has_view()) {
            continue;
        }
        if (!$cm->uservisible) {
            continue;
        }
        $row = new html_table_row();
        
        // print each activity's name in table
        $module_name = get_string('modulename', $cm->modname);
        $activity_cell = new html_table_cell();
        $activity_cell->text = html_writer::link("$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id", format_string($cm->name));
        $row->cells[] = $activity_cell;

        // print value of log entries for each student 
        $n = 0;
        foreach($all_entries as $user_entries) {
            ${'entries_cell_'.$n} = new html_table_cell();
            if (!empty($user_entries[$cmid] -> entries)) {
                ${'entries_cell_'.$n}->text = $user_entries[$cmid] -> entries;
            } else {
                ${'entries_cell_'.$n}->text = '-';
            }
            $row->cells[] = ${'entries_cell_'.$n};
            $n++;
        }
        $table->data[] = $row;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Entry records in '.$course->fullname);
echo html_writer::table($table);
echo $warning_a;
echo $warning_s;
echo $OUTPUT->footer();