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
 * Training Architecture block renderer
 *
 * @copyright 2025 IFRASS
 * @author    2025 Rayan Sellou 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   training_architecture
 */


defined('MOODLE_INTERNAL') || die();

class block_training_architecture_renderer extends plugin_renderer_base {
    
    public function render_description_modal($description, $id, $type) {
        global $CFG;
    
        $data = [
            'id' => $id,
            'type' => $type,
            'description' => $description,
            'image_url' => $CFG->wwwroot . "/blocks/training_architecture/images/description.png",
            'modal_title' => get_string('descriptionmodaltitle', 'block_training_architecture'),
        ];
    
        return $this->render_from_template('block_training_architecture/description_modal', $data);
    }

    public function render_course_dashboard_context($course_name, $course_url, $imageUrl, $course_id) {
        global $DB;
    
        $course = $DB->get_record('course', ['id' => $course_id]);
    
        $formattedSummary = format_text($course->summary, $course->summaryformat, ['noclean' => false]);
        $cleanSummary = htmlspecialchars(strip_tags($formattedSummary), ENT_QUOTES, 'UTF-8');
    
        $data = [
            'course_name' => $course_name,
            'course_url' => $course_url,
            'image_url' => $imageUrl,
            'course_summary' => $cleanSummary
        ];
    
        return $this->render_from_template('block_training_architecture/course_dashboard_context', $data);
    }

    public function render_course_course_context($course_name, $course_url) {
        global $OUTPUT;
    
        $course_icon = $OUTPUT->pix_icon('i/course', get_string('course'));
    
        $courseId = optional_param('id', 0, PARAM_INT); // Current course ID in the URL
        $url_parts = parse_url($course_url);
        parse_str($url_parts['query'], $params);
        $courseUrlId = $params['id'] ?? 0;
    
        $isCurrentCourse = ($courseId == $courseUrlId);
        $actual_course_icon = $isCurrentCourse
            ? $OUTPUT->pix_icon('t/online', get_string('actualcourse', 'block_training_architecture'), 'moodle', ['class' => 'green'])
            : '';
    
        $template_data = [
            'course_name' => $course_name,
            'course_url' => $course_url,
            'course_icon' => $course_icon,
            'is_current_course' => $isCurrentCourse,
            'actual_course_icon' => $actual_course_icon
        ];
    
        return $this->render_from_template('block_training_architecture/course_course_context', $template_data);
    }

    public function render_double_div_close() {
        return $this->render_from_template('block_training_architecture/double_div_close', []);
    }

    public function render_courses_not_in_architecture() { 
        return $this->render_from_template('block_training_architecture/courses_not_in_architecture', []);
    }
    
    public function render_path_display(array $data) {
        return $this->render_from_template('block_training_architecture/path_display', $data);
    }

    

    public function render_levels_by_semester($data) {
        return $this->render_from_template('block_training_architecture/levels_by_semester', $data);
    }

    public function render_semester_header($semesterId, $display_context) {
        $section_header_class = $display_context == 'course' ? 
            'blocktrainingarchitecture-course-section-header-block blocktrainingarchitecture-course-context' : 
            'blocktrainingarchitecture-course-section-header-block blocktrainingarchitecture-first-level';
    
        return $this->render_from_template('block_training_architecture/semester_header', [
            'section_header_class' => $section_header_class,
            'semester_id' => $semesterId
        ]);
    }

    public function render_summary($level_name, $description, $id, $openDetails, $class, $margin_style_semester, $margin_style_courses, $courses_html) {
        return $this->render_from_template('block_training_architecture/summary', [
            'level_name' => $level_name,
            'description_modal' => $description ? $this->render_description_modal($description, $id, 'Lu') : '',
            'open' => $openDetails,
            'class' => $class,
            'margin_style_semester' => $margin_style_semester,
            'margin_style_courses' => $margin_style_courses,
            'courses_html' => $courses_html
        ]);
    }

    public function render_courses_container($courses_html) {
        return $this->render_from_template('block_training_architecture/courses_container', [
            'courses_html' => $courses_html
        ]);
    }

    public function render_course_path($courseName) {
        return $this->render_from_template('block_training_architecture/course_path', [
            'coursename' => format_string($courseName)
        ]);
    }

    public function render_footer($url) {
        return $this->render_from_template('block_training_architecture/footer', ['url' => $url]);
    }

    public function render_training_no_courses() {
        return $this->render_from_template('block_training_architecture/no_training_courses', []);
    }

    public function render_div_close() {
        return $this->render_from_template('block_training_architecture/div_close', []);
    }

    public function render_double_hr() {
        return $this->render_from_template('block_training_architecture/double_hr', []);
    }

    public function render_levels_recursive($data) {
        return $this->render_from_template('block_training_architecture/level_recursive', $data);
    }

    public function render_semester_toggle($trainingid) {
        return $this->render_from_template('block_training_architecture/semester_toggle', [
            'id' => $trainingid
        ]);
    }

    public function render_semester_levels_wrapper($data) {
        return $this->render_from_template('block_training_architecture/semester_levels_wrapper', $data);
    }
    

}