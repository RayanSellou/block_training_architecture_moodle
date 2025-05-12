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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Training Architecture block class
 *
 * @copyright 2024 IFRASS
 * @author    2024 Esteban BIRET-TOSCANO <esteban.biret@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   training_architecture
 */

require_once($CFG->dirroot . '/cohort/lib.php');

class block_training_architecture extends block_base {

    protected $display_context;

    /**
     * Sets the block title
     *
     * @return void
     */
    public function init() {
        // Custom title 
        $title = get_config('block_training_architecture', 'title');
        $this->title = $title ? $title : get_string('pluginname', 'block_training_architecture');

        // To know if we are in dashboard or course context
        $this->display_context = '';
    }

    function specialization() {
        // After the block has been loaded we customize the block's title display
        if (!empty($this->config) && !empty($this->config->title)) {
            // There is a customized block title, display it
            $this->title = $this->config->title;
        }
    }

    /**
     *  We have global config/settings data
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Creates the blocks main content
     *
     * @return string
     */
    public function get_content() {
        // If content has already been generated, don't waste time generating it again.
        if ($this->content !== null) {
            return $this->content;
        }

        // Attributes
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        // CSS and js files
        $this->page->requires->js('/blocks/training_architecture/amd/src/display_architecture_semester.js');
        $this->page->requires->css('/blocks/training_architecture/styles.css');

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Set display context depends on actual page
        if (self::on_site_page($this->page)) {
            $this->display_context = 'dashboard';

        } else {
            $this->display_context = 'course';
        }

        if (!$this->prepare_content()) {
            return $this->content;
        }

        return $this->content;
    }
    
    /**
     * Produce content.
     * @return boolean false if an early exit
     */

    protected function prepare_content() {
        global $USER, $DB, $CFG;

        $renderer = $this->page->get_renderer('block_training_architecture');

        $courses_in_architecture = [];
        $courses_not_in_architecture = [];
        $user_courses = [];

        // Show a message when the user is not enrolled in any courses.
        $courses = enrol_get_my_courses();
        if (($this->page->user_is_editing() || is_siteadmin()) && empty($courses)) {
            $this->content->text = get_string('no_courses', 'block_training_architecture');
            return false;
        }
        
        foreach ($courses as $course) {
            $user_courses[] = $course->id;
        }

        $cohorts = cohort_get_user_cohorts($USER->id);

        // Get courses in and outside architecture
        foreach ($cohorts as $cohort) {    
            $cohortsToTrainings = $DB->get_records('local_training_architecture_cohort_to_training', ['cohortid' => $cohort->id]);

            foreach ($cohortsToTrainings as $cohortToTraining) {
                $trainings = $DB->get_records('local_training_architecture_training', ['id' => $cohortToTraining->trainingid]);

                foreach ($trainings as $training) {
                    $coursesNotInArchitecture = $DB->get_records('local_training_architecture_courses_not_architecture', ['trainingid' => $training->id]);
                    
                    foreach ($coursesNotInArchitecture as $courseNotInArchitecture) {
                        if (!in_array($courseNotInArchitecture->courseid, $courses_not_in_architecture)) { // Avoid duplication
                            $courses_not_in_architecture[] = $courseNotInArchitecture->courseid;
                        }                    
                    }

                    $root_levels = $this->get_root_levels($training, $DB); // First granularity level
                    
                    foreach ($root_levels as $root_level) {
                        $courses_in_architecture = $this->get_courses_in_architecture($root_level->luid1, $courses_in_architecture);
                    }
                }
            }
        }

        if (!empty($user_courses)) {
            // Display courses not in architecture
            if (!empty($courses_not_in_architecture)) {
                $this->display_courses_not_in_architecture($courses_not_in_architecture);
            }
        }

        // In course context, display path for courses not in architecture
        if($this->display_context == 'course') {

            foreach($courses_not_in_architecture as $courseId) {

                if ($courseId == optional_param('id', 0, PARAM_INT)) {  
                    $hasPath = true;
                    $course_name = $DB->get_field('course', 'shortname', ['id' => $courseId]);
                    // $this->content->text .= '<hr></hr>';
                    // $this->content->text .= "<div class='path'>" . get_string('path', 'block_training_architecture') . "</div>";
                    // $this->content->text .= "<div class='path'>" . $course_name . "</div>";
                    $this->content->text .= $renderer->render_course_path($course_name);
                    break;
                }
            }
        }

        // Display levels
        foreach ($cohorts as $cohort) {    
            $cohortsToTrainings = $DB->get_records('local_training_architecture_cohort_to_training', ['cohortid' => $cohort->id]);

            foreach ($cohortsToTrainings as $cohortToTraining) {
                $trainings = $DB->get_records('local_training_architecture_training', ['id' => $cohortToTraining->trainingid]);

                foreach ($trainings as $training) {
                    $courses_by_semester = [];
                    $links = $DB->get_records('local_training_architecture_training_links', ['trainingid' => $training->id]);

                    // Organizes courses by semester in the array $courses_by_semester
                    foreach ($links as $link) {
                        if($link->courseid && $link->semester && $DB->record_exists('local_training_architecture_lu_to_lu', ['luid2' => $link->courseid, 'isluid2course' => 'true', 'trainingid' => $training->id])) {
                            // Check if the semester is already present in the array, otherwise initialize it
                            if (!isset($courses_by_semester[$link->semester])) {
                                $courses_by_semester[$link->semester] = [];
                            }
                            // Add the course to the corresponding semester
                            $courses_by_semester[$link->semester][] = $link->courseid;
                        }
                    }

                    // Order array by key name (semesterid)
                    ksort($courses_by_semester);

                    // Display path for courses in architecture
                    $hasPath = false;

                    if($this->display_context == 'course') {
                        // $this->content->text .= '<hr></hr>';
                        $this->content->text .= $renderer->render_double_hr();
                        
                        $coursesAlreadySeen = [];

                        foreach($courses_in_architecture as $courseId) {

                            if (!in_array($courseId, $coursesAlreadySeen)) { //Avoid duplication
                                $coursesAlreadySeen[] = $courseId;

                                if ($courseId == optional_param('id', 0, PARAM_INT) &&
                                $DB->record_exists('local_training_architecture_lu_to_lu', ['luid2' => $courseId, 'trainingid' => $training->id, 'isluid2course' => 'true']))
                                {
                                    $hasPath = true;
                                    $this->display_path($training->id, $courseId);
                                }
                            }
                        }
                    }
                    else {
                        // $this->content->text .= '<hr></hr>';
                        $this->content->text .= $renderer->render_double_hr();
                    }

                    $root_levels = $this->get_root_levels($training, $DB);

                    $sortedRootLevels = [];
                    
                    foreach ($root_levels as $root_level) {
                        $sortedRootLevels[$root_level->luid1] = $root_level;
                    }

                    // Sort first level, based on sortorder attributes for each Learning Unit
                    $trainingId = $training->id;
                    uasort($sortedRootLevels, function($a, $b) use ($trainingId, $DB) {
                        $sortOrderA = $DB->get_field('local_training_architecture_order', 'sortorder', ['trainingid' => $trainingId, 'luid' => $a->luid1]);
                        $sortOrderB = $DB->get_field('local_training_architecture_order', 'sortorder', ['trainingid' => $trainingId, 'luid' => $b->luid1]);
                        return $sortOrderA - $sortOrderB;
                    });

                    if ($hasPath) {
                        // $this->content->text .= '<hr></hr>';
                        $this->content->text .= $renderer->render_double_hr();
                    }

                    // $div_class = $this->display_context == 'course' ? 'training-title-elements-course' : 'training-title-elements';
                    // $training_name = $this->display_context == 'course' ? $training->shortname : $training->fullname;

                    // $header_tag = ($this->display_context == 'course') ? 'h5' : 'h4';

                    // $this->content->text .= "<div class='$div_class'><" . $header_tag . " class='h-4-5-training'>" . get_string('training', 'block_training_architecture') . $training_name . ' (' . $cohort->name . ')</' . $header_tag . '>';
                    $this->content->text .= $renderer->render_training_header($training, $cohort->name, $this->display_context);


                    // Get the description of the training
                    $trainingDescription = $DB->get_field('local_training_architecture_training', 'description', ['id' => $trainingId]);

                    if($trainingDescription && $this->display_context != 'course') {
                        // $this->addDescriptionModal($trainingDescription, $trainingId, 'Training');
                        $this->content->text .= $renderer->render_description_modal($trainingDescription, $trainingId, 'Training');
                    }

                    $span_class = $this->display_context == 'course' ? 'course-context' : 'dashboard-context';

                    // Display by semester
                    if($training->issemester == 1) {
                        // $this->content->text .= "<div class='semester-elements'> <span class='$span_class'>" . get_string('view_by_semester', 'block_training_architecture') . "</span>
                        // <label class='switch'>
                        //     <input id=\"switch-{$training->id}\" type='checkbox' checked>
                        //     <span class='slider round'></span>
                        // </label></div>";

                        // $this->content->text .= "</div>";
                        $this->content->text .= $renderer->render_div_close();

                        // $this->content->text .= "<div id=\"semester-levels-semester-{$training->id}\">"; 
                        $this->content->text .= $renderer->render_semester_levels_semester_open($training->id);

                        // Check if there is architecture to display
                        if ($DB->record_exists('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'true'])) {
                            foreach ($courses_by_semester as $semesterId => $courses_semester) {
                                $semesterLevels = $this->get_levels_semester($courses_semester, $semesterId, $training->id);
    
                                $orderedSemesters = $this->orderLevelsSemester($semesterLevels, $training->id);
                                $numberOfLevel = $this->get_number_of_level($training->id);
                                $this->display_levels_by_semester($orderedSemesters, $numberOfLevel);
                            }
    
                            // $this->content->text .= '</div>'; 
                            $this->content->text .= $renderer->render_div_close();
    
                            // And display levels not semester, and hide them (in js we will hide or display this section, depends on user choice)
                            // $this->content->text .= "<div class='semester-levels' id=\"semester-levels-{$training->id}\">"; 
                            $this->content->text .= $renderer->render_semester_levels_open($training->id);

                            foreach ($sortedRootLevels as $root_level) {
                                $this->display_levels($root_level->luid1, $training->id);
                            }
    
                            // $this->content->text .= '</div>'; 
                            $this->content->text .= $renderer->render_div_close();
                        }
                        else {
                            // $this->content->text .= '<div class="training-no-courses">' . get_string('training_no_courses', 'block_training_architecture') . '</div>'; 
                            $this->content->text .= $renderer->render_training_no_courses();
                        }
                    }

                    else {
                        // $this->content->text .= '</div>'; 
                        $this->content->text .= $renderer->render_div_close();

                        // Check if there is architecture to display
                        if ($DB->record_exists('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'true'])) {
                            foreach ($sortedRootLevels as $root_level) {
                                $this->display_levels($root_level->luid1, $training->id);
                            }
                        }
                        else {
                            // $this->content->text .= '<div class="training-no-courses">' . get_string('training_no_courses', 'block_training_architecture') . '</div>'; 
                            $this->content->text .= $renderer->render_training_no_courses();
                        }
                    }

                }
            }
        }

        // Link to all courses
        $allCoursesUrl = $CFG->wwwroot . "/course/index.php";
        // $this->content->footer = '<a href="' . $allCoursesUrl . '">'. get_string('footer', 'block_training_architecture') . '</a> ...';
        $this->content->footer = $renderer->render_footer($CFG->wwwroot . "/course/index.php");

        return true;
    }

    /**
     * Retrieves the URL of the course image.
     * @param int $course_id The ID of the course.
     * @return string The URL of the course image.
     */
    protected function get_course_image_url($course_id) {
        global $CFG;

        $course = get_course($course_id);
        $imageUrl = \core_course\external\course_summary_exporter::get_course_image($course);

        // No image, return a default image url
        if($imageUrl == false) {
            $imageUrl = $CFG->wwwroot . "/blocks/training_architecture/images/no_image.jpg";
        }
        return $imageUrl;
    }

    /**
     * Inverts the keys and values of an array.
     * @param array $array The array to invert.
     * @param int $levels The number of levels.
     * @return array The inverted array.
     */
    protected function invert_array($array, $levels) {
        $inverted_array = [];
    
        foreach ($array as $child_key => $parents) {
            foreach ($parents as $parent_key => $parent_value) {
                if ($levels == '1') {
                    // If $levels is 1, parents are directly accessible in $parents
                    if (!isset($inverted_array[$parent_value][$parent_key])) {
                        $inverted_array[$parent_value][$parent_key] = [];
                    }
                    $inverted_array[$parent_value][$parent_key][] = $child_key;
                } else {
                    // If $levels is 2, parents are in an associative array
                    foreach ($parent_value as $grand_parent) {
                        if (!isset($inverted_array[$grand_parent][$parent_key])) {
                            $inverted_array[$grand_parent][$parent_key] = [];
                        }
                        $inverted_array[$grand_parent][$parent_key][] = $child_key;
                    }
                }
            }
        }
    
        return $inverted_array;
    }

    /**
     * Retrieves distinct luid1 values from the local_training_architecture_lu_to_lu table based on the provided training ID and granularity level.
     * @param object $training The training object.
     * @param object $DB The Moodle database object.
     * @return array The array of root levels.
     */
    function get_root_levels($training, $DB) {
        $sql = 'SELECT DISTINCT luid1 FROM {local_training_architecture_lu_to_lu} WHERE trainingid = ?';

        if ($training->granularitylevel != '1') {
            $sql .= ' AND isluid2course = ?';
            $params = [$training->id, 'false'];
        } else {
            $params = [$training->id];
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retrieves the sort order of a LU within a training.
     * @param int $trainingId The ID of the training.
     * @param int $luId The ID of the LU.
     * @return int The sort order of the LU within the training.
     */
    protected function get_sort_order($trainingId, $luId) {
        global $DB;
        return $DB->get_field('local_training_architecture_order', 'sortorder', ['trainingid' => $trainingId, 'luid' => $luId]);
    }

    /**
     * Orders the levels within a semester according to their sort order.
     * @param array $semesterLevels The levels within the semester.
     * @param int $trainingId The ID of the training.
     * @return array The ordered levels within the semester.
     */
    protected function orderLevelsSemester($semesterLevels, $trainingId) {

        $numberOfLevels = $this->get_number_of_level($trainingId);
        $sortedSemesterLevels = [];

        if($numberOfLevels == "1") {

            foreach ($semesterLevels as $luId => $modules) {
                // Retrieve the sort order of the training LU from the order table
                $sortOrder = $this->get_sort_order($trainingId, $luId);

                // Store the data in the temporary array
                $sortedSemesterLevels[$sortOrder] = [$luId => $modules];
            }
    
            // Sort the temporary array by order
            ksort($sortedSemesterLevels);
    
            // Rebuild the sorted array of training levels
            $semesterLevels = [];
            foreach ($sortedSemesterLevels as $data) {
                $semesterLevels += $data;
            }
        }

        else {

            foreach ($semesterLevels as $levelId => $modulesAndCourses) {
                $sortedModulesAndCourses = [];
    
                foreach ($modulesAndCourses as $moduleId => $courses) {
                    // Retrieve the sort order of the LU from the order table
                    $sortOrder = $this->get_sort_order($trainingId, $moduleId);
        
                    // Store the courses in a temporary array under the 2nd LU order
                    $sortedModulesAndCourses[$sortOrder][$moduleId] = $courses;
                }
    
                // Sort the courses of each 2nd LU by order
                ksort($sortedModulesAndCourses);
        
                // Rebuild the sorted array of 2nd LU and courses for this level
                $semesterLevels[$levelId] = [];
                foreach ($sortedModulesAndCourses as $moduleCourses) {
                    foreach ($moduleCourses as $moduleId => $courses) {
                        $semesterLevels[$levelId][$moduleId] = $courses;
                    }
                }
            }
    
            // Sorting the blocks by their sort order
            $sortedBlocks = [];
            foreach ($semesterLevels as $blockId => $modules) {
                $sortOrder = $this->get_sort_order($trainingId, $blockId);    
                $sortedBlocks[$sortOrder][$blockId] = $modules;
            }
    
            // Sort the temporary array by order
            ksort($sortedBlocks);
    
            // Rebuild the sorted array of blocks
            $semesterLevels = [];
            foreach ($sortedBlocks as $sortedModules) {
                foreach ($sortedModules as $blockId => $modules) {
                    $semesterLevels[$blockId] = $modules;
                }
            }
        }
        
        return $semesterLevels;
    }

    /**
     * Displays the levels recursively along with their courses.
     * @param int $level_id The ID of the current level.
     * @param int $trainingId The ID of the training.
     * @param int $depth The depth of the current level in the hierarchy (default: 0).
     */
    protected function display_levels($level_id, $trainingId, $depth = 0) {
        global $DB;
    
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        $level_name = $this->get_level_name($level_id);
        $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $level_id]);
        $children = $DB->get_records('local_training_architecture_lu_to_lu', [
            'luid1' => $level_id,
            'trainingid' => $trainingId
        ]);
    
        if (empty($children)) {
            $courseLinks = $DB->get_records('local_training_architecture_lu_to_lu', [
                'luid1' => $level_id,
                'trainingid' => $trainingId,
                'isluid2course' => 'true'
            ]);
    
            $courses = array_map(function($record) {
                return $record->luid2;
            }, $courseLinks);
    
            if (!empty($courses)) {
                $this->render_summary_block($courses, $level_name, $description, $level_id, $depth);
            }
    
            return; 
        }
    

        foreach ($children as $child) {
            $isCourse = $child->isluid2course === 'true';
            $hasGrandChildren = $DB->record_exists('local_training_architecture_lu_to_lu', [
                'luid1' => $child->luid2,
                'trainingid' => $trainingId
            ]);
    
            if (!$isCourse && $hasGrandChildren) {
                $class = $this->display_context == 'course' ? 'course-context' : 'first-level';
                $this->content->text .= $renderer->render_section_header($level_name, $description, $level_id, $class);
                break;
            }
        }
    
        $courses = [];
    
        foreach ($children as $child) {
            if ($child->isluid2course === 'true') {
                $courses[] = $child->luid2;
            } else {
                if ($DB->record_exists('local_training_architecture_lu_to_lu', [
                    'luid1' => $child->luid2,
                    'trainingid' => $trainingId
                ])) {
                    $this->display_levels($child->luid2, $trainingId, $depth + 1);
                }
            }
        }
    
        if (!empty($courses)) {
            $this->render_summary_block($courses, $level_name, $description, $level_id, $depth);
        }
    }

    protected function render_summary_block(array $courses, string $level_name, ?string $description, int $level_id, int $depth) {
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        $margin_left = $depth * 20;
        $margin_left_courses = $margin_left + 20;
    
        $margin_style = "style=\"margin-left: {$margin_left}px;\"";
        $margin_style_courses = "style=\"margin-left: {$margin_left_courses}px;\"";
    
        $this->generate_summary($margin_style, $margin_style_courses, $courses, $level_name);
    }

    /**
     * Retrieves the name of a level based on its ID.
     * @param int $level_id The ID of the level.
     * @return string The fullname or shortname of the level, depends on the actual display context.
     */
    protected function get_level_name($level_id) {
        global $DB;
        return $this->display_context == 'course' ?
            $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $level_id]) :
            $DB->get_field('local_training_architecture_lu', 'fullname', ['id' => $level_id]);
    }

    /**
     * Retrieves the granularity level of a training.
     * @param int $training_id The ID of the training.
     * @return int The granularity level of the training.
     */
    protected function get_number_of_level($training_id) {
        global $DB;
        return $DB->get_field('local_training_architecture_training', 'granularitylevel', ['id' => $training_id]);
    }

    /**
     * Generates a summary for a level with courses and displays them in a collapsible section.
     * @param string $margin_style_1 The CSS margin style for the summary element.
     * @param string $margin_style_2 The CSS margin style for the courses container.
     * @param array $courses The array of course IDs.
     * @param string $level_name The name of the level.
     */
    // protected function generate_summary($margin_style_1, $margin_style_2, $courses, $level_name) {
    //     global $DB;

    //     // Get the description and ID of the LU
    //     $description = $DB->get_field('local_training_architecture_lu', 'description', ['fullname' => $level_name]);
    //     $id = $DB->get_field('local_training_architecture_lu', 'id', ['fullname' => $level_name]);

    //     // Determine if details should be initially open based on the display context and current course ID
    //     $openDetails = $this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses);

    //     // Start the details element with or without initial open attribute
    //     $this->content->text .= '<details';
    //     $this->content->text .= $openDetails ? ' open>' : '>';

    //     // Determine the CSS class for the summary element based on the display context
    //     $class = $this->display_context == 'course' ? 'course-context' : 'dashboard-context';

    //     // Add the summary element with the specified margin style and level name
    //     $this->content->text .= "<summary class='$class' $margin_style_1>$level_name";

    //     if($description && $this->display_context != 'course') {
    //         $this->addDescriptionModal($description, $id, 'Lu');
    //     }

    //     $this->content->text .= "</summary>";

    //     // Start the courses container div with the specified margin style
    //     $this->content->text .= '<div ' . $margin_style_2 . '>';

    //     if($this->display_context == 'course') {
    //         $this->display_courses($courses);
    //     }
    //     else {
    //         $this->content->text .= '<div class="courses row row">';
    //         $this->display_courses($courses);
    //         $this->content->text .= '</div>';
    //     }

    //     $this->content->text .= '</div>';
    //     $this->content->text .= '</details>';
    // }
    protected function generate_summary($margin_style_1, $margin_style_2, $courses, $level_name) {
        global $DB;
    
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        // Récupération des infos LU
        $description = $DB->get_field('local_training_architecture_lu', 'description', ['fullname' => $level_name]);
        $id = $DB->get_field('local_training_architecture_lu', 'id', ['fullname' => $level_name]);
    
        $openDetails = $this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses);
        $class = $this->display_context == 'course' ? 'course-context' : 'dashboard-context';
    
        // Génération HTML des cours
        $courses_html = $this->display_courses($courses);
    
        // Vérification du contexte pour l'affichage des cours
        if ($this->display_context != 'course') {
            $content_courses_html = $renderer->render_courses_container($courses_html); // Ajouter un wrapper si ce n'est pas en contexte de cours
        } else {
            $content_courses_html = $courses_html; // Sinon utiliser directement les cours
        }
    
        // Création du contenu HTML final
        // Assurez-vous de ne pas perturber la structure de la page avec des modifications non contrôlées
        $this->content->text .= $renderer->render_summary(
            $level_name,         // Le nom du niveau
            $description,        // Description récupérée
            $id,                 // ID du niveau
            $openDetails,        // Déterminer si le résumé doit être ouvert
            $class,              // Classe CSS basée sur le contexte
            trim(str_replace(['style="', '"'], '', $margin_style_1)), // Marges
            trim(str_replace(['style="', '"'], '', $margin_style_2)), // Marges
            $content_courses_html // Contenu des cours
        );
    }


    /**
     * Retrieves the hierarchy of levels within a semester for a given training.
     * @param array $courses_semester The array of course IDs in the semester.
     * @param int $semesterId The ID of the semester.
     * @param int $trainingId The ID of the training.
     * @return array The hierarchy of levels within the semester.
     */
    protected function get_levels_semester($courses_semester, $semesterId, $trainingId) {
        global $DB;
        $course_hierarchy = [];
        $levels = $this->get_number_of_level($trainingId);

        // Define the CSS class for section headers based on the display context
        $section_header_class = $this->display_context == 'course' ? 'course-section-header-block course-context' : 'course-section-header-block first-level';

        $this->content->text .= $this->page->get_renderer('block_training_architecture')->render_semester_header($semesterId, $this->display_context);

        // Recursive function to retrieve the hierarchy of a course
        $get_course_hierarchy = function ($initial_course_id, $parent_id, &$course_hierarchy, $isCourse) use (&$get_course_hierarchy, $trainingId, $DB) {
            $levels = $this->get_number_of_level($trainingId);

            if($isCourse) {
                $parents = $DB->get_records('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'true', 'luid2' => $initial_course_id]);
            }
            else {
                $parents = $DB->get_records('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'false', 'luid2' => $parent_id]);
            }

            foreach ($parents as $parent) {
                // If the parent has a parent, recursively get its hierarchy
                if ($DB->record_exists('local_training_architecture_lu_to_lu', ['luid2' => $parent->luid1, 'isluid2course' => 'false', 'trainingid' => $trainingId])) {

                    if (!isset($course_hierarchy[$initial_course_id][$parent->luid1])) {
                        $course_hierarchy[$initial_course_id][$parent->luid1] = [];
                    }
                    $get_course_hierarchy($initial_course_id, $parent->luid1, $course_hierarchy, false);

                } else {

                    if (isset($course_hierarchy[$initial_course_id][$parent->luid2])) {
                        // If the parent's parent already has a child array, add the parent to it
                        $course_hierarchy[$initial_course_id][$parent->luid2][] = $parent->luid1;

                    } else {
                        // If the parent's parent does not have a child array yet, check if it has a grandparent
                        if (isset($course_hierarchy[$initial_course_id][$parent->luid1])) {
                            // If the grandparent exists, add the parent as its child
                            $course_hierarchy[$initial_course_id][$parent->luid1][] = $parent->luid1;
                        } else {
                            // If the grandparent does not exist, initialize the parent's parent array and add the parent to it
                            if($levels == '1') { //training in 1 level
                                $course_hierarchy[$initial_course_id] = [$parent->luid1];
                            }
                            else { //training in 2 levels
                                $course_hierarchy[$initial_course_id][$parent->luid2] = [$parent->luid1];
                            }
                        }
                    }
                }
            }
        };

        // Get the hierarchy for each course in the semester
        foreach ($courses_semester as $initial_course_id) {
            $get_course_hierarchy($initial_course_id, $initial_course_id, $course_hierarchy, true);
        }

        $semesterLevels = $this->invert_array($course_hierarchy, $levels);

        return $semesterLevels;
    }

    /**
     * Displays levels grouped by semester and their respective courses.
     * @param array $level_data The data of levels and their courses.
     * @param string $granularityLevel The granularity level of the architecture.
     * @param int|null $lu_id The ID of the LU (Learning Unit) if available.
     * @param int $depth The depth of recursion.
     */
    protected function display_levels_by_semester($level_data, $granularityLevel, $lu_id = null, $depth = 0) {
        $renderer = $this->page->get_renderer('block_training_architecture');
        $levels = $this->prepare_levels_recursive($level_data, $granularityLevel, $lu_id, $depth);
        $this->content->text .= $renderer->render_levels_by_semester(['levels' => $levels]);
    }

    protected function prepare_levels_recursive($level_data, $granularityLevel, $lu_id = null, $depth = 0) {
        global $DB;
    
        $levels = [];
        $margin_left = ($depth + 1) * 20;
        $luId = $lu_id;
    
        foreach ($level_data as $key => $value) {
            if ($key !== '0' && is_array($value)) {
                $luId = $key;
                $level_name = $this->get_level_name($luId);
                $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $luId]);
                $description_modal = ($description && $this->display_context !== 'course')
                    ? $this->page->get_renderer('block_training_architecture')->render_description_modal($description, $luId, 'Lu')
                    : '';
    
                $children = $this->prepare_levels_recursive($value, $granularityLevel, $luId, $depth + 1);
    
                // Extraire les cours associés à ce niveau
                $courses = [];
                foreach ($value as $child) {
                    if ($granularityLevel == '2') {
                        if (!is_array($child)) {
                            $courses[] = $child;
                        }
                    } else {
                        foreach ($child as $element) {
                            if (!is_array($element)) {
                                $courses[] = $element;
                            }
                        }
                    }
                }
    
                // Générer HTML des cours
                $courses_html = '';
                if (!empty($courses)) {
                    foreach ($courses as $course_id) {
                        $course_name = $DB->get_field('course', 'shortname', ['id' => $course_id]);
                        $course_url = $course_name ? "{$GLOBALS['CFG']->wwwroot}/course/view.php?id=$course_id" : '#';
    
                        if ($this->display_context == 'course') {
                            $courses_html .= $this->page->get_renderer('block_training_architecture')->render_course_course_context($course_name, $course_url, $GLOBALS['OUTPUT']);
                        } else {
                            $image_url = $this->get_course_image_url($course_id);
                            $courses_html .= $this->page->get_renderer('block_training_architecture')->render_course_dashboard_context($course_name, $course_url, $image_url, $course_id);
                        }
                    }
                }
    
                $levels[] = [
                    'level_name' => $level_name,
                    'description_modal' => $description_modal,
                    'class' => $this->display_context == 'course' ? 'course-context first-level-margin' : 'first-level',
                    'margin_left' => $margin_left,
                    'is_first_level' => ($depth == 0 && $granularityLevel == '2'),
                    'children' => $this->page->get_renderer('block_training_architecture')->render_levels_by_semester(['levels' => $children]),
                    'has_courses' => !empty($courses),
                    'courses_html' => $courses_html,
                    'margin_style_1' => $margin_left - 20,
                    'margin_style_2' => $margin_left,
                    'summary_class' => $this->display_context == 'course' ? 'course-context' : 'dashboard-context',
                    'open' => ($this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses)),
                    'is_course_context' => $this->display_context == 'course'
                ];
            }
        }
    
        return $levels;
    }

    /**
     * Recursively retrieves courses included in the architecture for a given level.
     * @param int $level_id The ID of the level in the architecture.
     * @param array $courses An array to store the course IDs found in the architecture.
     * @return array The array of course IDs included in the architecture for the given level.
     */
    protected function get_courses_in_architecture($level_id, &$courses) {
        global $DB;

        $children = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $level_id]);
        
        // If there are children, process them
        if ($children) {
            foreach ($children as $child) {

                // Check if the child has further children
                $has_children = $DB->record_exists('local_training_architecture_lu_to_lu', ['luid1' => $child->luid2]);

                if ($has_children && $child->isluid2course === 'false') {  

                    // Recursively call the function for child levels
                    $this->get_courses_in_architecture($child->luid2, $courses);

                } else { // If no further children, add the course to the array (last level)
                    $courses[] = $child->luid2;
                }
            }
        }
        return $courses;
    }

    /**
     * Displays a list of courses that are not included in the architecture (At the top of the plugin).
     * @param array $courses_not_in_architecture An array of course IDs not included in the architecture.
     */
    protected function display_courses_not_in_architecture($courses_not_in_architecture) {
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        $courses_html = $this->display_courses($courses_not_in_architecture);
    
        // Déterminer si on doit afficher le wrapper
        $show_wrapper = ($this->display_context != 'course');
    
        // Ajouter au contenu final via le renderer
        $this->content->text .= $renderer->render_courses_not_in_architecture($courses_html, $show_wrapper);
    }

    /**
     * Displays a list of courses either in course context or dashboard context.
     * @param array $courses An array of course IDs.
     */
    // protected function display_courses($courses) {
    //     global $DB, $CFG, $OUTPUT;

    //     $renderer = $this->page->get_renderer('block_training_architecture');

    //     foreach ($courses as $course_id) {

    //         $course_name = $DB->get_field('course', 'shortname', ['id' => $course_id]);
    //         $course_url = $course_name ? "$CFG->wwwroot/course/view.php?id=$course_id" : '#';

    //         // Display the course context based on the chosen display context
    //         if ($this->display_context == 'course') {
    //             // $this->content->text .= $this->generate_course_course_context_html($course_name, $course_url, $OUTPUT);
    //             $this->content->text .= $renderer->render_course_course_context($course_name, $course_url, $OUTPUT);
    //         } else {
    //             $imageUrl = $this->get_course_image_url($course_id);
    //             // $this->content->text .= $this->generate_course_dashboard_context_html($course_name, $course_url, $imageUrl, $course_id);
    //             $this->content->text .= $renderer->render_course_dashboard_context($course_name, $course_url, $imageUrl, $course_id);
    //         }
    //     }
    // }
    protected function display_courses($courses) {
        global $DB, $CFG, $OUTPUT;
    
        $renderer = $this->page->get_renderer('block_training_architecture');
        $output = '';
    
        foreach ($courses as $course_id) {
            $course_name = $DB->get_field('course', 'shortname', ['id' => $course_id]);
            $course_url = $course_name ? "$CFG->wwwroot/course/view.php?id=$course_id" : '#';
    
            if ($this->display_context == 'course') {
                $output .= $renderer->render_course_course_context($course_name, $course_url, $OUTPUT);
            } else {
                $imageUrl = $this->get_course_image_url($course_id);
                $output .= $renderer->render_course_dashboard_context($course_name, $course_url, $imageUrl, $course_id);
            }
        }
    
        return $output;
    }

    /**
     * Displays the path of a course within a training architecture.
     * @param int $trainingId The ID of the training.
     * @param int $courseId The ID of the course.
     */
    protected function display_path($trainingId, $courseId) {
        global $DB;
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        $numberOfLevels = $this->get_number_of_level($trainingId);
        $isSemester = $DB->get_field('local_training_architecture_training', 'issemester', ['id' => $trainingId]);
        $course_name = $DB->get_field('course', 'shortname', ['id' => $courseId]);
        $semester = $DB->get_field('local_training_architecture_training_links', 'semester', ['trainingid' => $trainingId, 'courseid' => $courseId]);
    
        $records = $DB->get_records('local_training_architecture_lu_to_lu', [
            'trainingid' => $trainingId,
            'luid2' => $courseId,
            'isluid2course' => 'true'
        ]);
    
        $paths = [];
    
        foreach ($records as $record) {
            $module_name = $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $record->luid1]);
    
            if ($numberOfLevels == "1") {
                if ($isSemester == "1" && $semester) {
                    $paths[] = [
                        'id' => "path-training-{$trainingId}",
                        'steps' => [
                            ['cssClass' => '', 'name' => $module_name],
                            ['cssClass' => 'path-1', 'name' => $course_name]
                        ]
                    ];
                    $paths[] = [
                        'id' => "path-training-semester-{$trainingId}",
                        'steps' => [
                            ['cssClass' => '', 'name' => get_string('semester', 'block_training_architecture') . $semester],
                            ['cssClass' => 'path-1', 'name' => $module_name],
                            ['cssClass' => 'path-2', 'name' => $course_name]
                        ]
                    ];
                } else {
                    $paths[] = [
                        'id' => '',
                        'steps' => [
                            ['cssClass' => '', 'name' => $module_name],
                            ['cssClass' => 'path-1', 'name' => $course_name]
                        ]
                    ];
                }
            } else {
                $block_id = $DB->get_record('local_training_architecture_lu_to_lu', [
                    'trainingid' => $trainingId,
                    'luid2' => $record->luid1,
                    'isluid2course' => 'false'
                ]);
                $block_name = $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $block_id->luid1]);
    
                if ($isSemester == "1" && $semester) {
                    $paths[] = [
                        'id' => "path-training-{$trainingId}",
                        'steps' => [
                            ['cssClass' => '', 'name' => $block_name],
                            ['cssClass' => 'path-1', 'name' => $module_name],
                            ['cssClass' => 'path-2', 'name' => $course_name]
                        ]
                    ];
                    $paths[] = [
                        'id' => "path-training-semester-{$trainingId}",
                        'steps' => [
                            ['cssClass' => '', 'name' => get_string('semester', 'block_training_architecture') . $semester],
                            ['cssClass' => 'path-1', 'name' => $block_name],
                            ['cssClass' => 'path-2', 'name' => $module_name],
                            ['cssClass' => 'path-3', 'name' => $course_name]
                        ]
                    ];
                } else {
                    $paths[] = [
                        'id' => '',
                        'steps' => [
                            ['cssClass' => '', 'name' => $block_name],
                            ['cssClass' => 'path-1', 'name' => $module_name],
                            ['cssClass' => 'path-2', 'name' => $course_name]
                        ]
                    ];
                }
            }
        }
    
        $this->content->text .= $renderer->render_path_display($paths);
    }

    // /**
    //  * Generates HTML markup for displaying a course in a course context.
    //  * @param string $course_name The name of the course.
    //  * @param string $course_url The URL of the course.
    //  * @param object $OUTPUT The Moodle output object.
    //  * @return string HTML markup for the course in a course context.
    //  */
    // protected function generate_course_course_context_html($course_name, $course_url, $OUTPUT) {
    //     $icon = $OUTPUT->pix_icon('i/course', get_string('course'));
    //     $courseId = optional_param('id', 0, PARAM_INT);
    //     $courseUrlId = $this->getCourseUrlId($course_url);

    //     // Determine if the current course matches the course in the URL
    //     $actualCourseIcon = ($courseId == $courseUrlId) ? $OUTPUT->pix_icon('t/online', get_string('actualCourse', 'block_training_architecture'), 'moodle', ['class' => 'green']) : '';
        
    //     return '
    //     <div class="course-context">
    //         <a class="blue" href="' . $course_url . '">' . $actualCourseIcon . $icon . $course_name . '</a>
    //     </div>';
    // }
    
    // /**
    //  * Generates HTML markup for displaying a course in a dashboard context.
    //  * @param string $course_name The name of the course.
    //  * @param string $course_url The URL of the course.
    //  * @param string $imageUrl The URL of the course image.
    //  * @return string HTML markup for the course in a dashboard context.
    //  */
    // protected function generate_course_dashboard_context_html($course_name, $course_url, $imageUrl, $course_id) {

    //     global $DB;
    //     $course = $DB->get_record('course', ['id' => $course_id]);

    //     $formattedSummary = format_text($course->summary, $course->sumaryformat, ['noclean' => false]);

    //     return '
    //         <div class="course-box" title="' . htmlspecialchars(strip_tags($formattedSummary), ENT_QUOTES, 'UTF-8') . '">
    //             <div class="frontpage-course-box">
    //                 <div class="course-item">
    //                     <div class="course-item-img">
    //                         <a href="' . $course_url . '" style="background-image: url(\'' . $imageUrl . '\')"></a>                                
    //                     </div>
    //                     <div class="course-content-block">
    //                         <div class="title">
    //                             <a class="title-a" href="' . $course_url . '">' . $course_name . '</a>
    //                         </div>
    //                     </div>
    //                 </div>
    //             </div>
    //         </div>';
    // }

    // /**
    //  * Retrieves the course ID from a given course URL.
    //  * Parses the URL to extract query parameters and retrieve the course ID.
    //  * @param string $courseUrl The URL of the course.
    //  * @return int The ID of the course extracted from the URL, or 0 if not found.
    //  */
    // protected function getCourseUrlId($courseUrl) {
    //     $urlParts = parse_url($courseUrl);

    //     // Extract the query string from the URL parts, or an empty string if not present
    //     $query = $urlParts['query'] ?? '';
    //     $parameters = [];
    //     parse_str($query, $parameters);

    //     // Retrieve the course ID from the parameters, or set to 0 if not found
    //     $courseUrlId = isset($parameters['id']) ? $parameters['id'] : 0;

    //     return $courseUrlId;
    // }

    // /**
    //  * Prepares and renders the modal for course descriptions.
    //  * @param string $description The description to display in the modal.
    //  * @param int $id The unique identifier for the modal.
    //  * @param string $type The type of modal (e.g., course, module, etc.).
    //  */
    // protected function addDescriptionModal($description, $id, $type) {
    //     global $PAGE;

    //     $renderer = $PAGE->get_renderer('block_training_architecture');
    //     $modal_html = $renderer->render_description_modal($description, $id, $type);

    //     $this->content->text .= $modal_html;
    // }

    /**
     * Checks whether the given page is site-level (Dashboard or Front page) or not.
     *
     * @param moodle_page $page the page to check, or the current page if not passed.
     * @return boolean True when on the Dashboard or Site home page.
     */
    public static function on_site_page($page = null) {
        global $PAGE;   // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal

        $page = $page ?? $PAGE; // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal
        $context = $page->context ?? null;

        if (!$page || !$context) {
            return false;
        } else if ($context->contextlevel === CONTEXT_SYSTEM && $page->requestorigin === 'restore') {
            return false; // When restoring from a backup, pretend the page is course-level.
        } else if ($context->contextlevel === CONTEXT_COURSE && $context->instanceid == SITEID) {
            return true;  // Front page.
        } else if ($context->contextlevel < CONTEXT_COURSE) {
            return true;  // System, user (i.e. dashboard), course category.
        } else {
            return false;
        }
    }

}