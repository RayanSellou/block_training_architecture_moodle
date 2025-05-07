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
    
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
    
        $courses = enrol_get_my_courses();
        if (($this->page->user_is_editing() || is_siteadmin()) && empty($courses)) {
            $this->content->text = get_string('no_courses', 'block_training_architecture');
            return false;
        }
    
        $userCourseIds = array_keys($courses);
        $userCohorts = cohort_get_user_cohorts($USER->id);
        $cohortIds = array_map(fn($c) => $c->id, $userCohorts);
    
        $cohortToTrainingRecords = $DB->get_records_list('local_training_architecture_cohort_to_training', 'cohortid', $cohortIds);
        $trainingIds = array_unique(array_map(fn($r) => $r->trainingid, $cohortToTrainingRecords));
    
        $trainings = $DB->get_records_list('local_training_architecture_training', 'id', $trainingIds);
        $courseNotInArchitecture = $DB->get_records_list('local_training_architecture_courses_not_architecture', 'trainingid', $trainingIds);
        $links = $DB->get_records_list('local_training_architecture_training_links', 'trainingid', $trainingIds);
        $luToLu = $DB->get_records_list('local_training_architecture_lu_to_lu', 'trainingid', $trainingIds);
        $sortOrders = $DB->get_records_list('local_training_architecture_order', 'trainingid', $trainingIds);
    
        $courseContextId = optional_param('id', 0, PARAM_INT);
        $courseNotInArchIds = array_column($courseNotInArchitecture, 'courseid');
        $hasCourses = !empty($userCourseIds);
        $contextIsCourse = $this->display_context === 'course';
        $courseName = $contextIsCourse ? $DB->get_field('course', 'shortname', ['id' => $courseContextId]) : '';
    
        // Structure data for mustache
        $data = [
            'has_courses' => $hasCourses,
            'courses_not_in_architecture' => array_intersect($userCourseIds, $courseNotInArchIds),
            'context_course' => $contextIsCourse,
            'course_name' => $courseName,
            'trainings' => [],
            'all_courses_url' => $CFG->wwwroot . "/course/index.php"
        ];
    
        foreach ($cohortToTrainingRecords as $record) {
            $training = $trainings[$record->trainingid] ?? null;
            if (!$training) continue;
    
            $cohortName = $userCohorts[$record->cohortid]->name ?? '';
            $trainingLinks = array_filter($links, fn($l) => $l->trainingid == $training->id);
            $trainingLuToLu = array_filter($luToLu, fn($lu) => $lu->trainingid == $training->id);
            $trainingSortOrders = array_filter($sortOrders, fn($s) => $s->trainingid == $training->id);
    
            $coursesBySemester = [];
            foreach ($trainingLinks as $link) {
                if (!$link->courseid || !$link->semester) continue;
                $coursesBySemester[$link->semester][] = $link->courseid;
            }
    
            ksort($coursesBySemester);
            $nonSemesterLevels = []; // rendered separately
            $semesterLevels = [];    // rendered separately
    
            // Use helper functions (already in class) to render levels
            foreach ($coursesBySemester as $semesterId => $courses_semester) {
                $levels = $this->get_levels_semester($courses_semester, $semesterId, $training->id);
                $ordered = $this->orderLevelsSemester($levels, $training->id);
                $semesterLevels[] = $this->render_levels_by_semester($ordered); // returns HTML
            }
    
            $rootLevels = $this->get_root_levels($training, $DB);
            uasort($rootLevels, function($a, $b) use ($training, $DB) {
                $aOrder = $DB->get_field('local_training_architecture_order', 'sortorder', ['trainingid' => $training->id, 'luid' => $a->luid1]);
                $bOrder = $DB->get_field('local_training_architecture_order', 'sortorder', ['trainingid' => $training->id, 'luid' => $b->luid1]);
                return $aOrder - $bOrder;
            });
    
            foreach ($rootLevels as $level) {
                $nonSemesterLevels[] = $this->render_level($level->luid1, $training->id); // returns HTML
            }
    
            $data['trainings'][] = [
                'training_id' => $training->id,
                'training_name' => $this->display_context === 'course' ? $training->shortname : $training->fullname,
                'cohort_name' => $cohortName,
                'training_class' => $this->display_context === 'course' ? 'training-title-elements-course' : 'training-title-elements',
                'header_tag' => $this->display_context === 'course' ? 'h5' : 'h4',
                'span_class' => $this->display_context === 'course' ? 'course-context' : 'dashboard-context',
                'is_semester' => (bool)$training->issemester,
                'semesters' => $semesterLevels,
                'non_semester_levels' => $nonSemesterLevels,
                'training_description' => $training->description,
                'training_description_modal' => $this->render_description_modal($training->description, $training->id)
            ];
        }
    
        $renderer = $this->page->get_renderer('block_training_architecture');
        $this->content->text = $renderer->render_from_template('block_training_architecture/content_template', $data);
        $this->content->footer = '';
    
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

    // Fetch all sort orders for the levels, modules, and blocks in one query
    $allSortOrders = $this->get_all_sort_orders($trainingId);

    if ($numberOfLevels == "1") {
        foreach ($semesterLevels as $luId => $modules) {
            // Retrieve the sort order for the LU from the pre-fetched data
            $sortOrder = isset($allSortOrders[$luId]) ? $allSortOrders[$luId] : 0;
            $sortedSemesterLevels[$sortOrder] = [$luId => $modules];
        }
        ksort($sortedSemesterLevels);
        $semesterLevels = array_merge([], ...$sortedSemesterLevels);
    }
    else {
        foreach ($semesterLevels as $levelId => $modulesAndCourses) {
            $sortedModulesAndCourses = [];

            foreach ($modulesAndCourses as $moduleId => $courses) {
                // Retrieve the sort order for the module from the pre-fetched data
                $sortOrder = isset($allSortOrders[$moduleId]) ? $allSortOrders[$moduleId] : 0;
                $sortedModulesAndCourses[$sortOrder][$moduleId] = $courses;
            }

            ksort($sortedModulesAndCourses);
            $semesterLevels[$levelId] = array_merge([], ...$sortedModulesAndCourses);
        }

        // Sorting the blocks by their sort order
        $sortedBlocks = [];
        foreach ($semesterLevels as $blockId => $modules) {
            $sortOrder = isset($allSortOrders[$blockId]) ? $allSortOrders[$blockId] : 0;
            $sortedBlocks[$sortOrder][$blockId] = $modules;
        }

        ksort($sortedBlocks);
        $semesterLevels = array_merge([], ...$sortedBlocks);
    }

    return $semesterLevels;
}

/**
 * Retrieves all sort orders for the levels, modules, and blocks in a single query.
 * @param int $trainingId The ID of the training.
 * @return array The array of sort orders keyed by ID.
 */
protected function get_all_sort_orders($trainingId) {
    global $DB;
    $sql = 'SELECT luid, sortorder FROM {local_training_architecture_order} WHERE trainingid = ?';
    $records = $DB->get_records_sql($sql, [$trainingId]);

    $sortOrders = [];
    foreach ($records as $record) {
        $sortOrders[$record->luid] = $record->sortorder;
    }

    return $sortOrders;
}

/**
 * Displays the levels recursively along with their courses.
 * @param int $level_id The ID of the current level.
 * @param int $trainingId The ID of the training.
 * @param int $depth The depth of the current level in the hierarchy (default: 0).
 */
protected function display_levels($level_id, $trainingId, $depth = 0) {
    global $DB, $OUTPUT;

    $courses = [];
    $margin_left = $depth * 20; // 20px offset per level

    // Get the description of the LU
    $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $level_id]);

    // Get the children of the current level
    $children = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $level_id, 'trainingid' => $trainingId]);

    $level_name = $this->get_level_name($level_id);

    // Prepare data for the template
    $data = [
        'level_name' => $level_name,
        'description' => $description,
        'margin_left' => $margin_left,
        'depth' => $depth,
        'children' => [],
        'courses' => [],
    ];

    // Process children and sort them
    if ($children) {
        $all_false = true;

        foreach ($children as $child) {
            if ($child->isluid2course !== 'false') {
                $all_false = false;
                break;
            }
        }

        // Sort children if all are not courses
        if ($all_false) {
            $sortOrders = $this->get_sort_orders_for_children($trainingId, $children);
            usort($children, function($a, $b) use ($sortOrders) {
                return $sortOrders[$a->luid2] - $sortOrders[$b->luid2];
            });
        }

        // Collect courses and children
        foreach ($children as $child) {
            if ($child->isluid2course === 'true') {
                $data['courses'][] = $child->luid2;
            } else {
                if ($DB->record_exists('local_training_architecture_lu_to_lu', ['luid1' => $child->luid2, 'trainingid' => $trainingId])) {
                    // Recursively call display_levels for nested levels
                    $this->display_levels($child->luid2, $trainingId, $depth + 1);
                }
            }
        }
    }

    // Load Mustache template for level display
    $template = $this->output->render_from_template('local_training_architecture/level_display', $data);

    // Append the rendered template to the content
    $this->content->text .= $template;
}

/**
 * Retrieves sort orders for children of a given training.
 * @param int $trainingId The ID of the training.
 * @param array $children The children records.
 * @return array The sorted orders keyed by LU ID.
 */
protected function get_sort_orders_for_children($trainingId, $children) {
    global $DB;

    // Create an array of LUs that need sorting
    $luid2List = array_map(function($child) { return $child->luid2; }, $children);

    // Query all sort orders in one go
    $sortOrders = $DB->get_records_list('local_training_architecture_order', 'luid', $luid2List, 'sortorder', 'luid, sortorder');

    // Map the sort orders by LU ID
    $sortedOrders = [];
    foreach ($sortOrders as $order) {
        $sortedOrders[$order->luid] = $order->sortorder;
    }

    return $sortedOrders;
}

/**
 * Retrieves the name of a level based on its ID.
 * @param int $level_id The ID of the level.
 * @return string The fullname or shortname of the level, depending on the actual display context.
 */
protected function get_level_name($level_id) {
    global $DB;

    // Préparer la colonne à récupérer en fonction du contexte
    $column = $this->display_context == 'course' ? 'shortname' : 'fullname';

    // Utiliser la méthode get_field directement avec les paramètres
    return $DB->get_field('local_training_architecture_lu', $column, ['id' => $level_id]);
}

/**
 * Retrieves the granularity level of a training.
 * @param int $training_id The ID of the training.
 * @return int The granularity level of the training.
 */
protected function get_number_of_level($training_id) {
    global $DB;

    // On récupère directement le niveau de granularité avec une requête SQL optimisée.
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

    // Récupération des informations de la LU (id et description) en une seule requête
    $lu_info = $DB->get_record('local_training_architecture_lu', ['fullname' => $level_name], 'id, description');
    
    // Déterminer si les détails doivent être initialement ouverts
    $openDetails = $this->display_context == 'course' && in_array(optional_param('id', 0, PARAM_INT), $courses);

    // Préparer les données pour le template Mustache
    $template_data = [
        'level_name' => $level_name,
        'margin_style_1' => $margin_style_1,
        'margin_style_2' => $margin_style_2,
        'open_details' => $openDetails,
        'description' => $lu_info->description,
        'courses' => $courses,
        'display_context' => $this->display_context
    ];

    // Affichage avec le template Mustache
    $this->content->text .= $this->render_from_template('block_training_architecture/level_summary', $template_data);
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

    // Get the section header class based on the display context
    $section_header_class = $this->display_context == 'course' ? 'course-section-header-block course-context' : 'course-section-header-block first-level';

    // Prepare data for Mustache template
    $semester_header = [
        'section_header_class' => $section_header_class,
        'semester_name' => get_string('semester', 'block_training_architecture') . $semesterId
    ];

    // Fetch hierarchy for all courses in the semester
    $get_course_hierarchy = function ($initial_course_id, $parent_id, &$course_hierarchy, $isCourse) use (&$get_course_hierarchy, $trainingId, $DB) {
        $levels = $this->get_number_of_level($trainingId);

        $parents = $isCourse 
            ? $DB->get_records('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'true', 'luid2' => $initial_course_id])
            : $DB->get_records('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'isluid2course' => 'false', 'luid2' => $parent_id]);

        foreach ($parents as $parent) {
            // If the parent has a parent, recursively get its hierarchy
            if ($DB->record_exists('local_training_architecture_lu_to_lu', ['luid2' => $parent->luid1, 'isluid2course' => 'false', 'trainingid' => $trainingId])) {
                if (!isset($course_hierarchy[$initial_course_id][$parent->luid1])) {
                    $course_hierarchy[$initial_course_id][$parent->luid1] = [];
                }
                $get_course_hierarchy($initial_course_id, $parent->luid1, $course_hierarchy, false);
            } else {
                // Handle hierarchy for the parent course
                if (isset($course_hierarchy[$initial_course_id][$parent->luid2])) {
                    $course_hierarchy[$initial_course_id][$parent->luid2][] = $parent->luid1;
                } else {
                    if (isset($course_hierarchy[$initial_course_id][$parent->luid1])) {
                        $course_hierarchy[$initial_course_id][$parent->luid1][] = $parent->luid1;
                    } else {
                        if ($levels == '1') {
                            $course_hierarchy[$initial_course_id] = [$parent->luid1];
                        } else {
                            $course_hierarchy[$initial_course_id][$parent->luid2] = [$parent->luid1];
                        }
                    }
                }
            }
        }
    };

    // Get hierarchy for each course in the semester
    foreach ($courses_semester as $initial_course_id) {
        $get_course_hierarchy($initial_course_id, $initial_course_id, $course_hierarchy, true);
    }

    $semesterLevels = $this->invert_array($course_hierarchy, $levels);

    // Prepare data to pass to Mustache template
    $template_data = [
        'semester_header' => $semester_header,
        'semester_levels' => $semesterLevels,
    ];

    // Render the output with the Mustache template
    return $this->render_from_template('block_training_architecture/semester_levels', $template_data);
}

/**
 * Displays levels grouped by semester and their respective courses.
 * @param array $level_data The data of levels and their courses.
 * @param string $granularityLevel The granularity level of the architecture.
 * @param int|null $lu_id The ID of the LU (Learning Unit) if available.
 * @param int $depth The depth of recursion.
 */
protected function display_levels_by_semester($level_data, $granularityLevel, $lu_id = null, $depth = 0) {
    global $DB;

    $courses = [];
    $level_info = [];
    $luId = $lu_id;
    $margin_left = ($depth + 1) * 20; // 20px offset per level

    // Get the descriptions of the LUs in advance to avoid multiple queries
    $lu_descriptions = [];
    foreach ($level_data as $key => $value) {
        if ($key != '0' && is_array($value)) {
            $lu_descriptions[$key] = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $key]);
        }
    }

    // Prepare level data for Mustache template
    foreach ($level_data as $key => $value) {
        if ($key != '0' && is_array($value)) {
            $luId = $key;
            $level_name = $this->get_level_name($luId);
            $description = $lu_descriptions[$luId] ?? null;

            // Store level information for Mustache rendering
            $level_info[] = [
                'lu_id' => $luId,
                'level_name' => $level_name,
                'description' => $description,
                'depth' => $depth,
                'granularity_level' => $granularityLevel,
                'margin_left' => $margin_left
            ];

            // Recursively display child levels
            $this->display_levels_by_semester($value, $granularityLevel, $luId, $depth + 1);
        }
    }

    // Extract courses from the level data
    foreach ($level_data as $child_data) {
        if ($granularityLevel == '2') {
            if (!is_array($child_data)) {
                $courses[] = $child_data;
            }
        } else {
            foreach ($child_data as $element) {
                if (!is_array($element)) {
                    $courses[] = $element;
                }
            }
        }
    }

    // If there are courses, generate the summary
    if (!empty($courses)) {
        $level_name = $this->get_level_name($luId);
        $margin_left_courses = $margin_left;
        $margin_style_courses = "style=\"margin-left: {$margin_left_courses}px;\"";

        // Prepare data to pass to the Mustache template
        $template_data = [
            'level_info' => $level_info,
            'courses' => $courses,
            'level_name' => $level_name,
            'margin_style_courses' => $margin_style_courses
        ];

        // Render the output with Mustache template
        return $this->render_from_template('block_training_architecture/levels_by_semester', $template_data);
    }
}

/**
 * Recursively retrieves courses included in the architecture for a given level.
 * @param int $level_id The ID of the level in the architecture.
 * @param array $courses An array to store the course IDs found in the architecture.
 * @return array The array of course IDs included in the architecture for the given level.
 */
protected function get_courses_in_architecture($level_id, &$courses) {
    global $DB;

    // Get all children of the current level (no need to check for exists multiple times)
    $children = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $level_id]);

    if ($children) {
        foreach ($children as $child) {

            // If the child is a level (isluid2course is false), recursively get courses from the child level
            if ($child->isluid2course === 'false') {
                $this->get_courses_in_architecture($child->luid2, $courses);
            } else {
                // If it's a course, add it to the courses array
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
    // Pass data to the Mustache template for rendering
    $data = new stdClass();
    $data->courses = $courses_not_in_architecture;
    $data->display_context = $this->display_context;

    // Render the template with the data
    $this->content->text .= $this->render_from_template('local_training_architecture/courses_not_in_architecture', $data);
}

/**
 * Displays a list of courses either in course context or dashboard context.
 * @param array $courses An array of course IDs.
 */
protected function display_courses($courses) {
    global $DB, $CFG, $OUTPUT;

    // Prepare the data to be passed to the Mustache template
    $data = new stdClass();
    $data->courses = [];

    // Récupérer les noms des cours dans une seule requête
    $course_ids = array_map(function($course) { return $course; }, $courses); // Extraire les IDs des cours
    $course_names = $DB->get_records_list('course', 'id', $course_ids, '', 'id, shortname');

    // Récupérer les URL d'image des cours en une seule requête
    $imageUrls = [];
    foreach ($courses as $course_id) {
        $imageUrls[$course_id] = $this->get_course_image_url($course_id);
    }


    foreach ($courses as $course_id) {
        $course_name = isset($course_names[$course_id]) ? $course_names[$course_id]->shortname : '';
        $course_url = $course_name ? "$CFG->wwwroot/course/view.php?id=$course_id" : '#';
        $imageUrl = isset($imageUrls[$course_id]) ? $imageUrls[$course_id] : '';

        // Add course data to the array to be used in the template
        $data->courses[] = (object)[
            'course_name' => $course_name,
            'course_url' => $course_url,
            'image_url' => $imageUrl,
            'course_id' => $course_id
        ];
    }

    // Render the appropriate template based on the display context
    if ($this->display_context == 'course') {
        $this->content->text .= $this->render_from_template('local_training_architecture/courses_course_context', $data);
    } else {
        $this->content->text .= $this->render_from_template('local_training_architecture/courses_dashboard_context', $data);
    }
}

/**
 * Displays the path of a course within a training architecture.
 * @param int $trainingId The ID of the training.
 * @param int $courseId The ID of the course.
 */
protected function display_path($trainingId, $courseId) {
    global $DB;

    // Get the number of levels of the training
    $numberOfLevels = $this->get_number_of_level($trainingId);

    // Check if the training is organized by semesters
    $isSemester = $DB->get_field('local_training_architecture_training', 'issemester', ['id' => $trainingId]);

    // Get the short name of the course
    $course_name = $DB->get_field('course', 'shortname', ['id' => $courseId]);

    // Retrieve records for the given training and course
    $records = $DB->get_records('local_training_architecture_lu_to_lu', 
        ['trainingid' => $trainingId, 'luid2' => $courseId, 'isluid2course' => 'true']);

    // Initialize the path data
    $path_data = [];
    $path_data['isSemester'] = $isSemester;
    $path_data['trainingId'] = $trainingId;
    $path_data['courseName'] = $course_name;

    // Process paths for each record based on the training structure (1-level or multi-level)
    if ($numberOfLevels == "1") {
        $path_data['paths'] = $this->generate_single_level_paths($records, $trainingId, $courseId, $isSemester);
    } else {
        $path_data['paths'] = $this->generate_multi_level_paths($records, $trainingId, $courseId, $isSemester);
    }

    // Render the Mustache template with the prepared data
    $this->content->text .= $this->render_from_template('local_training_architecture/course_path', $path_data);
}

/**
 * Generates paths for trainings with a single level.
 * @param array $records The records retrieved for the course.
 * @param int $trainingId The ID of the training.
 * @param int $courseId The ID of the course.
 * @param bool $isSemester Whether the training is semester-based.
 * @return array The generated paths.
 */
private function generate_single_level_paths($records, $trainingId, $courseId, $isSemester) {
    global $DB;

    $paths = [];

    foreach ($records as $record) {
        $module_name = $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $record->luid1]);

        // Path for semester-based trainings
        if ($isSemester) {
            $semester = $DB->get_field('local_training_architecture_training_links', 'semester', ['trainingid' => $trainingId, 'courseid' => $courseId]);
            $paths[] = [
                'module_name' => $module_name,
                'course_name' => $course_name,
                'semester' => $semester
            ];
        } else {
            // Path for non-semester-based trainings
            $paths[] = [
                'module_name' => $module_name,
                'course_name' => $course_name
            ];
        }
    }

    return $paths;
}

/**
 * Generates paths for trainings with multiple levels.
 * @param array $records The records retrieved for the course.
 * @param int $trainingId The ID of the training.
 * @param int $courseId The ID of the course.
 * @param bool $isSemester Whether the training is semester-based.
 * @return array The generated paths.
 */
private function generate_multi_level_paths($records, $trainingId, $courseId, $isSemester) {
    global $DB;

    $paths = [];

    foreach ($records as $record) {
        $module_name = $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $record->luid1]);
        $block_id = $DB->get_record('local_training_architecture_lu_to_lu', ['trainingid' => $trainingId, 'luid2' => $record->luid1, 'isluid2course' => 'false']);
        $block_name = $DB->get_field('local_training_architecture_lu', 'shortname', ['id' => $block_id->luid1]);

        // Path for semester-based trainings
        if ($isSemester) {
            $semester = $DB->get_field('local_training_architecture_training_links', 'semester', ['trainingid' => $trainingId, 'courseid' => $courseId]);
            $paths[] = [
                'block_name' => $block_name,
                'module_name' => $module_name,
                'course_name' => $course_name,
                'semester' => $semester
            ];
        } else {
            // Path for non-semester-based trainings
            $paths[] = [
                'block_name' => $block_name,
                'module_name' => $module_name,
                'course_name' => $course_name
            ];
        }
    }

    return $paths;
}

/**
 * Prepares the data to be passed to the Mustache template for displaying a course in course context.
 * @param string $course_name The name of the course.
 * @param string $course_url The URL of the course.
 * @param object $OUTPUT The Moodle output object.
 * @return void
 */
protected function generate_course_course_context_html($course_name, $course_url, $OUTPUT) {
    $courseId = optional_param('id', 0, PARAM_INT);
    $courseUrlId = $this->getCourseUrlId($course_url);

    // Check if the current course is the one being viewed
    $actualCourseIcon = ($courseId == $courseUrlId) ? 't/online' : 'i/course';

    // Prepare data for Mustache template
    $data = new stdClass();
    $data->course_name = $course_name;
    $data->course_url = $course_url;
    $data->icon = $OUTPUT->pix_icon($actualCourseIcon, get_string('course'));

    // Render the template with the prepared data
    $this->content->text .= $this->render_from_template('local_training_architecture/course_context', $data);
}

/**
 * Prepares the data to be passed to the Mustache template for displaying a course in the dashboard context.
 * @param string $course_name The name of the course.
 * @param string $course_url The URL of the course.
 * @param string $imageUrl The URL of the course image.
 * @param int $course_id The ID of the course.
 * @return void
 */
protected function generate_course_dashboard_context_html($course_name, $course_url, $imageUrl, $course_id) {
    global $DB;

    // Get the course record in one query
    $course = $DB->get_record('course', ['id' => $course_id], 'summary, summaryformat');
    
    // If the course record is not found, return early
    if (!$course) {
        return;
    }

    // Format the summary for the course
    $formattedSummary = format_text($course->summary, $course->summaryformat, ['noclean' => false]);

    // Prepare the data to pass to the Mustache template
    $data = new stdClass();
    $data->course_name = $course_name;
    $data->course_url = $course_url;
    $data->image_url = $imageUrl;
    $data->formatted_summary = htmlspecialchars(strip_tags($formattedSummary), ENT_QUOTES, 'UTF-8');

    // Render the Mustache template with the prepared data
    $this->content->text .= $this->render_from_template('local_training_architecture/course_dashboard_context', $data);
}

/**
 * Retrieves the course ID from a given course URL.
 * Parses the URL to extract query parameters and retrieve the course ID.
 * @param string $courseUrl The URL of the course.
 * @return int The ID of the course extracted from the URL, or 0 if not found.
 */
protected function getCourseUrlId($courseUrl) {
    // Parse the URL to retrieve its components
    $urlParts = parse_url($courseUrl);

    // If the URL is not valid or the query part is missing, return 0
    if (!isset($urlParts['query'])) {
        return 0;
    }

    // Parse the query string into an associative array of parameters
    parse_str($urlParts['query'], $parameters);

    // Return the 'id' parameter, or 0 if it's not found
    return isset($parameters['id']) ? (int)$parameters['id'] : 0;
}

/**
 * Prepares and renders the modal for course descriptions.
 * @param string $description The description to display in the modal.
 * @param int $id The unique identifier for the modal.
 * @param string $type The type of modal (e.g., course, module, etc.).
 */
protected function addDescriptionModal($description, $id, $type) {
    global $CFG;

    // Prepare data for the modal
    $modalData = new stdClass();
    $modalData->modal_id = 'descriptionModal' . $type . $id;
    $modalData->label_id = 'descriptionModalLabel' . $type . $id;
    $modalData->button_id = 'modal-btn-' . $type . '-' . $id;
    $modalData->image_url = $CFG->wwwroot . "/blocks/training_architecture/images/description.png";
    $modalData->description = $description;
    $modalData->modal_title = get_string('descriptionModalTitle', 'block_training_architecture');

    // Render the modal using Mustache template
    $this->content->text .= $this->render_from_template('local_training_architecture/description_modal', $modalData);
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