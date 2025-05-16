<?php
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

    /**
     * Render the path display
     * 
     * @param array $data The template data
     * @return string HTML output
     */
    public function render_path_display(array $data) {
        return $this->render_from_template('block_training_architecture/path_display', $data);
    }

    public function render_courses_not_in_architecture($courses_html, $show_wrapper) {
        $data = [
            'courses_html' => $courses_html,
            'show_wrapper' => $show_wrapper
        ];
    
        return $this->render_from_template('block_training_architecture/courses_not_in_architecture', $data);
    }

    public function render_levels_by_semester($data) {
        return $this->render_from_template('block_training_architecture/levels_by_semester', $data);
    }

    public function render_semester_header($semesterId, $display_context) {
        $section_header_class = $display_context == 'course' ? 
            'course-section-header-block course-context' : 
            'course-section-header-block first-level';
    
        return $this->render_from_template('block_training_architecture/semester_header', [
            'section_header_class' => $section_header_class,
            'semester_id' => $semesterId
        ]);
    }

    public function render_summary($level_name, $description, $id, $openDetails, $class, $margin_style_semester, $margin_style_courses, $content_courses_html) {
        return $this->render_from_template('block_training_architecture/summary', [
            'level_name' => $level_name,
            'description_modal' => $description ? $this->render_description_modal($description, $id, 'Lu') : '',
            'open' => $openDetails,
            'class' => $class,
            'margin_style_semester' => $margin_style_semester,
            'margin_style_courses' => $margin_style_courses,
            'content_courses_html' => $content_courses_html
        ]);
    }

    public function render_courses_container($courses_html) {
        return $this->render_from_template('block_training_architecture/courses_container', [
            'courses_html' => $courses_html
        ]);
    }

    public function render_section_header($level_name, $description, $id, $class) {
        return $this->render_from_template('block_training_architecture/section_header', [
            'level_name' => $level_name,
            'description_modal' => $description ? $this->render_description_modal($description, $id, 'Lu') : '',
            'class' => $class
        ]);
    }

    public function render_training_header($training, $cohortname, $context) {
        $templatecontext = [
            'containerclass' => $context == 'course' ? 'training-title-elements-course' : 'training-title-elements',
            'trainingname' => $context == 'course' ? $training->shortname : $training->fullname,
            'cohortname' => $cohortname,
            'headertag' => $context == 'course' ? 'h5' : 'h4',
            'contextclass' => $context == 'course' ? 'course-context' : 'dashboard-context',
            'issemester' => $training->issemester == 1,
            'id' => $training->id,
            'trainingdescription' => !empty($training->description),
            'descriptionmodal' => $this->render_description_modal($training->description, $training->id, 'Training')
        ];
    
        return $this->render_from_template('block_training_architecture/training', $templatecontext);
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

    public function render_semester_levels_semester_open($trainingid) {
        return $this->render_from_template('block_training_architecture/semester_levels_semester_open', [
            'trainingid' => $trainingid
        ]);
    }
    
    public function render_semester_levels_open($trainingid) {
        return $this->render_from_template('block_training_architecture/semester_levels_open', [
            'trainingid' => $trainingid
        ]);
    }
    

}