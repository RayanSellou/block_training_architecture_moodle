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
        $this->content->text = '';
        $user_courses = [];
    
        $courses = enrol_get_my_courses();
        if (($this->page->user_is_editing() || is_siteadmin()) && empty($courses)) {
            $this->content->text = get_string('no_courses', 'block_training_architecture');
            return false;
        }
        
        // Collect user course IDs
        foreach ($courses as $course) {
            $user_courses[] = $course->id;
        }
        
        // Get user cohorts
        $cohorts = cohort_get_user_cohorts($USER->id);
        $cohortIds = array_map(fn($c) => $c->id, $cohorts);
    
        // Preload cohort to training associations
        $cohortToTrainings = $DB->get_records_list('local_training_architecture_cohort_to_training', 'cohortid', $cohortIds);
        $trainingIds = array_unique(array_column($cohortToTrainings, 'trainingid'));
    
        // Preload training records
        $trainings = $DB->get_records_list('local_training_architecture_training', 'id', $trainingIds);
    
        // Preload courses not in architecture
        $coursesNotArchRaw = $DB->get_records_list('local_training_architecture_courses_not_architecture', 'trainingid', $trainingIds);
        $courses_not_in_architecture = [];
        foreach ($coursesNotArchRaw as $record) {
            $courses_not_in_architecture[$record->courseid] = $record->courseid;
        }
        $courses_not_in_architecture = array_values($courses_not_in_architecture);
    
        // Preload LU to LU links to detect trainings with architecture
        $luToLu = $DB->get_records_list('local_training_architecture_lu_to_lu', 'trainingid', $trainingIds);
        $hasArch = [];
        foreach ($luToLu as $record) {
            if ($record->isluid2course === 'true') {
                $hasArch[$record->trainingid] = true;
            }
        }
    
        // Build list of courses included in architecture by traversing root LUs
        $courses_in_architecture = [];
        $root_levels_by_training = [];
        foreach ($trainings as $training) {
            if (!isset($root_levels_by_training[$training->id])) {
                $root_levels = $this->get_root_levels($training, $DB);
                $root_levels_by_training[$training->id] = $root_levels;
            }
    
            foreach ($root_levels_by_training[$training->id] as $root_level) {
                $courses_in_architecture = $this->get_courses_in_architecture($root_level->luid1, $courses_in_architecture);
            }
        }
    
        // Display courses not in architecture if present
        if (!empty($user_courses) && !empty($courses_not_in_architecture)) {
            $this->display_courses_not_in_architecture($courses_not_in_architecture);
        }
    
        // If in course context, show path for current course if it's outside architecture
        if ($this->display_context === 'course') {
            $currentCourseId = optional_param('id', 0, PARAM_INT);
            if (in_array($currentCourseId, $courses_not_in_architecture)) {
                $course_name = $DB->get_field('course', 'shortname', ['id' => $currentCourseId]);
                $this->content->text .= $renderer->render_double_hr();
                $this->content->text .= $renderer->render_course_path($course_name);
            }
        }
    
        // Index cohorts by ID for quick lookup
        $cohortById = [];
        foreach ($cohorts as $c) $cohortById[$c->id] = $c;
    
        // Group training IDs by cohort ID
        $trainingIdsByCohort = [];
        foreach ($cohortToTrainings as $link) {
            $trainingIdsByCohort[$link->cohortid][] = $link->trainingid;
        }
    
        foreach ($cohortIds as $cohortId) {
            if (!isset($trainingIdsByCohort[$cohortId])) continue;
            $cohort = $cohortById[$cohortId];
            foreach ($trainingIdsByCohort[$cohortId] as $trainingId) {
                if (!isset($trainings[$trainingId])) continue;
                $training = $trainings[$trainingId];
                $hasPath = false;
                $courses_by_semester = [];
    
                $links = $DB->get_records('local_training_architecture_training_links', ['trainingid' => $trainingId]);
                foreach ($links as $link) {
                    if ($link->courseid && $link->semester &&
                        $DB->record_exists('local_training_architecture_lu_to_lu', [
                            'luid2' => $link->courseid,
                            'isluid2course' => 'true',
                            'trainingid' => $trainingId
                        ])) {
                        $courses_by_semester[$link->semester][] = $link->courseid;
                    }
                }
    
                ksort($courses_by_semester);
    
                // In course context, display path for current course if it belongs to this training architecture
                if ($this->display_context == 'course') {
                    $this->content->text .= $renderer->render_double_hr();
                    foreach ($courses_in_architecture as $courseId) {
                        if ($courseId == optional_param('id', 0, PARAM_INT) &&
                            $DB->record_exists('local_training_architecture_lu_to_lu', [
                                'luid2' => $courseId,
                                'trainingid' => $trainingId,
                                'isluid2course' => 'true'
                            ])) {
                            $hasPath = true;
                            $this->display_path($trainingId, $courseId);
                            break;
                        }
                    }
                } else {
                    $this->content->text .= $renderer->render_double_hr();
                }
    
                // Title + description
                $div_class = $this->display_context == 'course' ? 'blocktrainingarchitecture-training-title-elements-course' : 'blocktrainingarchitecture-training-title-elements';
                $training_name = $this->display_context == 'course' ? $training->shortname : $training->fullname;
                $header_tag = ($this->display_context == 'course') ? 'h5' : 'h4';
    
                $this->content->text .= "<div class='$div_class'><$header_tag class='h-4-5-training'>" .
                    get_string('training', 'block_training_architecture') .
                    $training_name . ' (' . $cohort->name . ")</$header_tag>";
    
                // Description 
                if ($training->description && $this->display_context != 'course') {
                    $this->content->text .= $renderer->render_description_modal($training->description, $trainingId, 'Training');
                }
    
                // Architecture by semester
                if ($training->issemester == 1) {
                    $this->content->text .= $renderer->render_semester_toggle($trainingId);
                    $this->content->text .= $renderer->render_div_close();
                    $this->content->text .= "<div id=\"semester-levels-semester-{$trainingId}\">";
    
                    if (isset($hasArch[$trainingId])) {
                        foreach ($courses_by_semester as $semesterId => $courses_semester) {
                            $semesterLevels = $this->get_levels_semester($courses_semester, $semesterId, $trainingId);
                            $orderedSemesters = $this->orderLevelsSemester($semesterLevels, $trainingId);
                            $numberOfLevel = $this->get_number_of_level($trainingId);
                            $this->display_levels_by_semester($orderedSemesters, $numberOfLevel);
                        }
    
                        $this->content->text .= $renderer->render_div_close();
                        $this->content->text .= "<div class='semester-levels' id=\"semester-levels-{$trainingId}\">";
    
                        foreach ($root_levels_by_training[$trainingId] as $root_level) {
                            $this->display_levels($root_level->luid1, $trainingId);
                        }
    
                        $this->content->text .= $renderer->render_div_close();
                    } else {
                        $this->content->text .= $renderer->render_training_no_courses();
                    }
                } else {
                    $this->content->text .= $renderer->render_div_close();
                    if (isset($hasArch[$trainingId])) {
                        foreach ($root_levels_by_training[$trainingId] as $root_level) {
                            $this->display_levels($root_level->luid1, $trainingId);
                        }
                    } else {
                        $this->content->text .= $renderer->render_training_no_courses();
                    }
                }
            }
        }
    
        // Footer
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
        $renderer = $this->page->get_renderer('block_training_architecture');
        $data = $this->get_levels_data($level_id, $trainingId, $depth);
        if ($data !== null) {
            $this->content->text .= $renderer->render_levels_recursive($data);
        }
    }
    

    /**
     * Recursively retrieves and formats hierarchical levels (LUs) and their courses
     * for display in a training architecture view. 
     * @param int $level_id ID of the current LU (Learning Unit) node.
     * @param int $trainingId ID of the training session.
     * @param int $depth Current depth in the recursive hierarchy (used for indentation/margins).
     * @return array|null Structured data ready to be rendered, or null if nothing found.
     */
    protected function get_levels_data($level_id, $trainingId, $depth = 0) {
        global $DB;
        // Renderer for outputting HTML elements
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        // Calculate left margin based on depth to indent hierarchy visually
        $margin_left = $depth * 20;
        $margin_left_courses = $margin_left + 20;
    
        // Get the name and description of the current Learning Unit (LU)
        $level_name = $this->get_level_name($level_id);
        $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $level_id]);
    
        // Get child links: LUs or courses related to this LU
        $children_links = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $level_id, 'trainingid' => $trainingId]);
    
        // Check if all children links point to LUs (no direct courses)
        $all_false = true;
        foreach ($children_links as $child) {
            if ($child->isluid2course !== 'false') {
                $all_false = false;
                break;
            }
        }
    
        // If all children are LUs (no courses), sort them by defined training order
        if ($all_false) {
            uasort($children_links, function($a, $b) use ($trainingId, $DB) {
                $sortOrderA = $DB->get_field('local_training_architecture_order', 'sortorder', [
                    'trainingid' => $trainingId,
                    'luid' => $a->luid2
                ]) ?? 0;
    
                $sortOrderB = $DB->get_field('local_training_architecture_order', 'sortorder', [
                    'trainingid' => $trainingId,
                    'luid' => $b->luid2
                ]) ?? 0;
    
                return $sortOrderA <=> $sortOrderB;
            });
        }
    
        $courses = [];
        $children_data = [];
    
        // Separate courses from child LUs, and recurse for each child LU
        foreach ($children_links as $child) {
            if ($child->isluid2course === 'true') {
                $courses[] = $child->luid2;
            } else if ($DB->record_exists('local_training_architecture_lu_to_lu', [
                'luid1' => $child->luid2,
                'trainingid' => $trainingId
            ])) {
                // Recursive call to get child LU data
                $child_data = $this->get_levels_data($child->luid2, $trainingId, $depth + 1);
                if ($child_data !== null) {
                    $children_data[] = $child_data;
                }
            }
        }
    
        // If there are no child LUs but courses exist, prepare HTML summary with indentation
        if (empty($children_data) && !empty($courses)) {
            $margin_style_semester = "margin-left: {$margin_left}px;";
            $margin_style_courses = "margin-left: {$margin_left_courses}px;";
    
            // Generate HTML for courses and wrap it in container
            $courses_html = $this->display_courses($courses);
            $courses_html = $renderer->render_courses_container($courses_html);
    
            // Render summary block for the current LU with course info and styles
            $summary_html = $renderer->render_summary(
                $level_name,
                $description,
                $level_id,
                $this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses),
                $this->display_context == 'course'
                    ? 'blocktrainingarchitecture-course-context'
                    : 'blocktrainingarchitecture-dashboard-context',
                $margin_style_semester,
                $margin_style_courses,
                $courses_html
            );
    
            // Return structured data with summary and no children
            return [
                'level_name' => $level_name,
                'description_modal' => '',
                'class' => '',
                'margin_left' => $margin_left,
                'courses_html' => '',
                'children' => [],
                'no_header' => true,
                'summary' => $summary_html,
            ];
        }
    
        // If there are courses, generate HTML for them
        $courses_html = '';
        if (!empty($courses)) {
            $courses_html = $this->display_courses($courses);
            $courses_html = $renderer->render_courses_container($courses_html);
        }
    
        // Return all gathered data including children, courses, and description modal if applicable
        return [
            'level_name' => $level_name,
            'description_modal' => $description && $this->display_context != 'course'
                ? $renderer->render_description_modal($description, $level_id, 'Lu')
                : '',
            'class' => $this->display_context == 'course'
                ? 'blocktrainingarchitecture-course-context'
                : 'blocktrainingarchitecture-first-level',
            'margin_left' => $margin_left,
            'courses_html' => $courses_html,
            'children' => $children_data,
            'no_header' => false,
            'summary' => false,
        ];
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
    protected function generate_summary($margin_style_1, $margin_style_2, $courses, $level_name) {
        global $DB;
    
        $renderer = $this->page->get_renderer('block_training_architecture');
    
        // Fetch the LU description and ID using its fullname.
        $description = $DB->get_field('local_training_architecture_lu', 'description', ['fullname' => $level_name]);
        $id = $DB->get_field('local_training_architecture_lu', 'id', ['fullname' => $level_name]);
    
        // Determine if the section should be expanded (only in course context, and course is in the list).
        $openDetails = $this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses);
    
        // Set CSS class based on whether we're in a course or dashboard context.
        $class = $this->display_context == 'course' ? 'blocktrainingarchitecture-course-context' : 'blocktrainingarchitecture-dashboard-context';
    
        // Generate the HTML for the list of associated courses.
        $courses_html = $this->display_courses($courses);
    
        // Wrap courses in a container if we're not in course context.
        if ($this->display_context != 'course') {
            $courses_html = $renderer->render_courses_container($courses_html);
        }
    
        // Render the final summary block with all components.
        $this->content->text .= $renderer->render_summary(
            $level_name,
            $description,
            $id,
            $openDetails,
            $class,
            trim(str_replace(['style="', '"'], '', $margin_style_1)), // Clean up inline style
            trim(str_replace(['style="', '"'], '', $margin_style_2)),
            $courses_html
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
        $section_header_class = $this->display_context == 'course' ? 'blocktrainingarchitecture-course-section-header-block blocktrainingarchitecture-course-context' : 'blocktrainingarchitecture-course-section-header-block blocktrainingarchitecture-first-level';

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

    /**
     * Recursively prepares the architecture structure (levels and courses) for display.
     *
     * @param array $level_data Nested array of LU structure.
     * @param string $granularityLevel Level depth (e.g., 1 = LU > Course, 2 = Block > LU > Course).
     * @param int|null $lu_id Parent LU ID (used in recursion).
     * @param int $depth Current recursion level (used for indentation/margin).
     * @return array Prepared data structure to be rendered by the template.
     */
    protected function prepare_levels_recursive($level_data, $granularityLevel, $lu_id = null, $depth = 0) {
        global $DB;

        $levels = [];
        $margin_left = ($depth + 1) * 20;

        foreach ($level_data as $key => $value) {
            // Skip non-LU entries (they are indexed under '0')
            if ($key !== '0' && is_array($value)) {
                $luId = $key;
                $level_name = $this->get_level_name($luId);

                // Optional description modal (only in dashboard view)
                $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $luId]);
                $description_modal = ($description && $this->display_context !== 'course')
                    ? $this->page->get_renderer('block_training_architecture')->render_description_modal($description, $luId, 'Lu')
                    : '';

                // Recursive call to process children LUs
                $children = $this->prepare_levels_recursive($value, $granularityLevel, $luId, $depth + 1);

                // Extract course IDs from the structure
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

                // Build HTML for each course (image + name), depending on the context
                $courses_html = '';
                foreach ($courses as $course_id) {
                    $course_name = $DB->get_field('course', 'shortname', ['id' => $course_id]);
                    $course_url = $course_name ? "{$GLOBALS['CFG']->wwwroot}/course/view.php?id=$course_id" : '#';

                    if ($this->display_context == 'course') {
                        $courses_html .= $this->page->get_renderer('block_training_architecture')
                            ->render_course_course_context($course_name, $course_url, $GLOBALS['OUTPUT']);
                    } else {
                        $image_url = $this->get_course_image_url($course_id);
                        $courses_html .= $this->page->get_renderer('block_training_architecture')
                            ->render_course_dashboard_context($course_name, $course_url, $image_url, $course_id);
                    }
                }

                // Assemble all level info for rendering
                $levels[] = [
                    'level_name' => $level_name,
                    'description_modal' => $description_modal,
                    'class' => $this->display_context == 'course' ? 'blocktrainingarchitecture-course-context blocktrainingarchitecture-first-level-margin' : 'blocktrainingarchitecture-first-level',
                    'margin_left' => $margin_left,
                    'is_first_level' => ($depth == 0 && $granularityLevel == '2'),
                    'children' => $this->page->get_renderer('block_training_architecture')
                        ->render_levels_by_semester(['levels' => $children]),
                    'has_courses' => !empty($courses),
                    'courses_html' => $courses_html,
                    'margin_style_1' => $margin_left - 20,
                    'margin_style_2' => $margin_left,
                    'summary_class' => $this->display_context == 'course' ? 'blocktrainingarchitecture-course-context' : 'blocktrainingarchitecture-dashboard-context',
                    'open' => ($this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses)),
                    'is_course_context' => $this->display_context == 'course'
                ];
            }
        }

        return $levels;
    }


    /**
     * Recursively retrieves courses included in the architecture for a given level.
     *
     * This method builds a flat list of all course IDs that are directly or indirectly
     * associated with a given architecture level. It navigates the hierarchy using recursion,
     * avoiding repeated DB calls by caching the full link structure the first time it's needed.
     *
     * @param int $level_id The ID of the starting level (LU).
     * @param array $courses An array passed by reference to accumulate course IDs found.
     * @return array The array of course IDs included in the architecture for the given level.
     */
    protected function get_courses_in_architecture($level_id, &$courses) {
        global $DB;

        // Cache all LU-to-LU relationships to avoid redundant DB queries on recursion
        static $all_links = null;

        if ($all_links === null) {
            // Load all tree relationships from the database once
            $records = $DB->get_records('local_training_architecture_lu_to_lu');
            $all_links = [];
            foreach ($records as $rec) {
                $all_links[$rec->luid1][] = $rec;
            }
        }

        // If the current level has children, iterate through them
        if (!empty($all_links[$level_id])) {
            foreach ($all_links[$level_id] as $child) {
                $has_children = !empty($all_links[$child->luid2]);

                // If the child is not a course and has children, recurse deeper
                if ($has_children && $child->isluid2course === 'false') {
                    $this->get_courses_in_architecture($child->luid2, $courses);
                } else {
                    // Otherwise, it's a course ID — add it to the result
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
    
        if ($this->display_context != 'course') {
            $this->content->text .= $renderer->render_courses_not_in_architecture();
        }
    
        $courses_html = $this->display_courses($courses_not_in_architecture);
        $this->content->text .= $courses_html;
    
        if ($this->display_context != 'course') {
            $this->content->text .= $renderer->render_double_div_close();
        }
    }

    /**
     * Displays a list of courses either in course context or dashboard context.
     *
     * This method builds and returns the HTML for displaying course links. It adjusts
     * the rendering depending on the display context (within a course or from the dashboard).
     *
     * @param array $courses An array of course IDs to display.
     * @return string HTML output for the courses.
     */
    protected function display_courses($courses) {
        global $DB, $CFG;

        // Get the renderer for this plugin
        $renderer = $this->page->get_renderer('block_training_architecture');
        $output = '';

        // If the course list is empty, return an empty string early
        if (empty($courses)) {
            return $output;
        }

        // Fetch course shortnames in a single query for better performance
        list($in_sql, $params) = $DB->get_in_or_equal($courses);
        $course_records = $DB->get_records_select('course', "id $in_sql", $params, '', 'id, shortname');

        // Loop through each course ID to build output
        foreach ($courses as $course_id) {
            // Skip if the course doesn't exist in the fetched records
            if (!isset($course_records[$course_id])) {
                continue;
            }

            // Retrieve course shortname and URL
            $course_name = $course_records[$course_id]->shortname;
            $course_url = "$CFG->wwwroot/course/view.php?id=$course_id";

            // Render differently depending on whether we are in a course page or the dashboard
            if ($this->display_context == 'course') {
                // Render course in 'course context' (simple format)
                $output .= $renderer->render_course_course_context($course_name, $course_url);
            } else {
                // Render course in 'dashboard context' (includes image and ID)
                $imageUrl = $this->get_course_image_url($course_id);
                $output .= $renderer->render_course_dashboard_context($course_name, $course_url, $imageUrl, $course_id);
            }
        }

        // Return the built HTML
        return $output;
    }
    

    /**
     * Displays the path of a course within a training architecture.
     *
     * - Fetches the hierarchy from Learning Units (LUs) to course.
     * - Determines if a semester display is needed.
     * - Builds one or two paths depending on whether the training uses semesters.
     * - Supports both single-level (LU > course) and two-level (Block > LU > course) structures.
     *
     * @param int $trainingId The ID of the training.
     * @param int $courseId The ID of the course.
     */
    protected function display_path($trainingId, $courseId) {
        global $DB;
        $renderer = $this->page->get_renderer('block_training_architecture');

        // Fetch basic display metadata
        $numberOfLevels = (int) $this->get_number_of_level($trainingId);
        $isSemester = (int) $DB->get_field('local_training_architecture_training', 'issemester', ['id' => $trainingId]);
        $course_name = $DB->get_field('course', 'shortname', ['id' => $courseId]);
        $semester = $DB->get_field('local_training_architecture_training_links', 'semester', [
            'trainingid' => $trainingId,
            'courseid' => $courseId
        ]);

        // Fetch LU-to-course mappings (LUid2 is the course)
        $records = $DB->get_records('local_training_architecture_lu_to_lu', [
            'trainingid' => $trainingId,
            'luid2' => $courseId,
            'isluid2course' => 'true'
        ]);

        // Gather LU and (if applicable) block IDs for later name resolution
        $lu_ids = [];
        foreach ($records as $record) {
            $lu_ids[] = $record->luid1;

            // In two-level structure, fetch the parent block of the LU
            if ($numberOfLevels > 1) {
                $parent = $DB->get_record('local_training_architecture_lu_to_lu', [
                    'trainingid' => $trainingId,
                    'luid2' => $record->luid1,
                    'isluid2course' => 'false'
                ]);
                if ($parent) {
                    $lu_ids[] = $parent->luid1;
                    $record->parent_luid1 = $parent->luid1; // Cache for later
                }
            }
        }

        // Retrieve all LU/block shortnames in a single efficient query
        $lu_ids = array_unique($lu_ids);
        list($in_sql, $params) = $DB->get_in_or_equal($lu_ids);
        $lu_names = $DB->get_records_select_menu('local_training_architecture_lu', "id $in_sql", $params, '', 'id, shortname');

        // Build display paths
        $paths = [];

        foreach ($records as $record) {
            $module_name = $lu_names[$record->luid1] ?? '';

            // Handle 1-level structure: LU > Course
            if ($numberOfLevels === 1) {
                if ($isSemester && $semester) {
                    // Semester view + LU > Course
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
                    // No semester
                    $paths[] = [
                        'id' => '',
                        'steps' => [
                            ['cssClass' => '', 'name' => $module_name],
                            ['cssClass' => 'path-1', 'name' => $course_name]
                        ]
                    ];
                }

            // Handle 2-level structure: Block > LU > Course
            } else {
                $block_id = $record->parent_luid1 ?? null;
                $block_name = $block_id && isset($lu_names[$block_id]) ? $lu_names[$block_id] : '';

                if ($isSemester && $semester) {
                    // Semester view + Block > LU > Course
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
                    // No semester
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

        // Render all built paths via template
        $this->content->text .= $renderer->render_path_display(['paths' => $paths]);
    }


    /**
     * Retrieves the course ID from a given course URL.
     * Parses the URL to extract query parameters and retrieve the course ID.
     * @param string $courseUrl The URL of the course.
     * @return int The ID of the course extracted from the URL, or 0 if not found.
     */
    protected function getCourseUrlId($courseUrl) {
        $urlParts = parse_url($courseUrl);

        // Extract the query string from the URL parts, or an empty string if not present
        $query = $urlParts['query'] ?? '';
        $parameters = [];
        parse_str($query, $parameters);

        // Retrieve the course ID from the parameters, or set to 0 if not found
        $courseUrlId = isset($parameters['id']) ? $parameters['id'] : 0;

        return $courseUrlId;
    }

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