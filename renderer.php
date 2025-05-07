<?php
defined('MOODLE_INTERNAL') || die();

class block_training_architecture_renderer extends plugin_renderer_base {
    
    public function render_course_card($context) {
        return $this->render_from_template('block_training_architecture/course_card', $context);
    }

    public function render_training_title(string $div_class, string $header_tag, string $training_name, string $cohort_name): string {
        $context = [
            'div_class' => $div_class,
            'header_tag' => $header_tag,
            'training_name' => $training_name,
            'cohort_name' => $cohort_name,
        ];
        return $this->render_from_template('block_training_architecture/training_title', $context);
    }

    public function render_switch_semester(string $training_id, string $span_class): string {
        return $this->render_from_template('block_training_architecture/switch_semester', [
            'training_id' => $training_id,
            'span_class' => $span_class
        ]);
    }

    public function render_no_course(): string {
        return $this->render_from_template('block_training_architecture/no_course', []);
    }

    public function render_semester_levels(string $training_id, string $content): string {
        return $this->render_from_template('block_training_architecture/semester_levels', [
            'training_id' => $training_id,
            'content' => $content
        ]);
    }
    
    public function render_no_semester_levels(string $training_id, string $content): string {
        return $this->render_from_template('block_training_architecture/no_semester_levels', [
            'training_id' => $training_id,
            'content' => $content
        ]);
    }

    public function render_display_levels(array $data): string {
        return $this->render_from_template('block_training_architecture/display_levels', $data);
    }

    public function render_path_courses_not_in_architecture(array $data): string {
        return $this->render_from_template('block_training_architecture/path_courses_not_in_architecture', $data);
    }
    
    public function render_generate_summary(array $data): string {
        return $this->render_from_template('block_training_architecture/generate_summary', $data);
    }

    public function render_get_levels_semester(array $data): string {
        return $this->render_from_template('block_training_architecture/get_levels_semester', $data);
    }

    public function render_display_level_by_semester(array $data): string {
        $data['wwwroot'] = $this->page->url->get_scheme() . '://' . $this->page->url->get_host(); 
        return $this->render_from_template('block_training_architecture/display_level_by_semester', $data);
    }

    public function render_courses_not_in_architecture_container(array $data): string {
        return $this->render_from_template('block_training_architecture/courses_not_in_architecture_container', $data);
    }

    public function render_path_with_semester($context) {
        return $this->render_from_template('block_training_architecture/path_with_semester', $context);
    }

    public function render_path_no_semester($context) {
        return $this->render_from_template('block_training_architecture/path_no_semester', $context);
    }

    // public function render_course_context($context) {
    //     return $this->render_from_template('block_training_architecture/course_context', $context);
    // }

    // public function render_description_modal(array $context): string {
    //     return $this->render_from_template('block_training_architecture/add_description_modal', $context);
    // }

    public function render_level($level_id, $training_id) {
        global $DB;

        // Récupérer les données nécessaires au niveau
        $level_name = $this->get_level_name($level_id);
        $description = $DB->get_field('local_training_architecture_lu', 'description', ['id' => $level_id]);
        $children = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $level_id, 'trainingid' => $training_id]);

        // Structurer les données pour le template
        $data = [
            'level_name' => $level_name,
            'description' => $description,
            'children' => $children,
        ];

        // Retourne le rendu Mustache
        return $this->render_from_template('block_training_architecture/level_display', $data);
    }

    /**
     * Generates a summary for a level with courses and displays them in a collapsible section.
     * @param string $margin_style_1 The CSS margin style for the summary element.
     * @param string $margin_style_2 The CSS margin style for the courses container.
     * @param array $courses The array of course IDs.
     * @param string $level_name The name of the level.
     */
    public function render_level_summary($margin_style_1, $margin_style_2, $courses, $level_name) {
        global $DB;

        // Récupération des informations de la LU (id et description) en une seule requête
        $lu_info = $DB->get_record('local_training_architecture_lu', ['fullname' => $level_name], 'id, description');
        
        // Déterminer si les détails doivent être initialement ouverts
        $openDetails = $this->page->context->get_context_level() == CONTEXT_COURSE && in_array(optional_param('id', 0, PARAM_INT), $courses);

        // Préparer les données pour le template Mustache
        $template_data = [
            'level_name' => $level_name,
            'margin_style_1' => $margin_style_1,
            'margin_style_2' => $margin_style_2,
            'open_details' => $openDetails,
            'description' => $lu_info->description,
            'courses' => $courses,
            'display_context' => $this->page->context->get_context_level() == CONTEXT_COURSE
        ];

        // Affichage avec le template Mustache
        return $this->render_from_template('block_training_architecture/level_summary', $template_data);
    }








    // public function render_description_modal(string $description, int $id, string $type): string {
    //     global $CFG;
    
    //     $modalId = 'descriptionModal' . $type . $id;
    //     $labelId = 'descriptionModalLabel' . $type . $id;
    //     $buttonId = 'modal-btn-' . $type . '-' . $id;
    
    //     $template_data = [
    //         'modal_id' => $modalId,
    //         'label_id' => $labelId,
    //         'button_id' => $buttonId,
    //         'image_url' => $CFG->wwwroot . "/blocks/training_architecture/images/description.png",
    //         'description' => $description,
    //         'modal_title' => get_string('descriptionModalTitle', 'block_training_architecture'),
    //     ];
    
    //     return $this->render_from_template('block_training_architecture/mod_description_modal', $template_data);
    // }

    // public function render_course_dashboard_context($course_name, $course_url, $imageUrl, $course_id, $summary) {
    //     $data = [
    //         'course_name' => $course_name,
    //         'course_url' => $course_url,
    //         'image_url' => $imageUrl,
    //         'summary' => $summary,
    //     ];

    //     return $this->render_from_template('block_training_architecture/course_dashboard_context', $data);
    // }

    public function render_course_context($template_data) {
        return $this->render_from_template('block_training_architecture/course_context', $template_data);
    }

    public function render_training_path($path_data) {
        return $this->render_from_template('block_training_architecture/training_path', ['path_data' => $path_data]);
    }

    public function render_courses_list($courses_info, $display_context) {
        return $this->render_from_template('block_training_architecture/courses_list', ['courses' => $courses_info, 'context' => $display_context]);
    }

    public function render_levels_by_semester($levels_for_template) {
        $template = $this->output->render_from_template('block_training_architecture/levels_by_semester', [
            'levels_for_template' => $levels_for_template
        ]);
    
        return $template;
    }

    public function render_summary_section(array $data): string {
        return $this->render_from_template('block_training_architecture/summary_section', $data);
    }

    public function render_level_header($level_name, $description, $level_id) {
        $class = $this->display_context == 'course' ? 'course-context' : 'first-level';
    
        $description_modal = '';
        if ($this->display_context != 'course' && !empty($description)) {
            $description_modal = $this->render_description_modal($description, $level_id, 'Lu');
        }
    
        $data = [
            'level_name' => $level_name,
            'class' => $class,
            'show_modal' => !empty($description_modal),
            'description_modal' => $description_modal,
        ];
    
        $this->content->text .= $this->render_from_template('block_training_architecture/level_header', $data);
    }

    public function render_training_block($training, $cohort, $sortedRootLevels, $coursesInArchitecture, $currentCourseId) {
        $data = [
            'training_name' => $training->fullname,
            'cohort_name' => $cohort->name,
            'courses_in_architecture' => array_map(function($courseId) {
                return [
                    'course_name' => get_course($courseId)->fullname,
                    'course_url' => new moodle_url('/course/view.php', ['id' => $courseId])
                ];
            }, $coursesInArchitecture),
            'current_course_id' => $currentCourseId,
            'root_levels' => $this->prepare_root_levels($sortedRootLevels),
            'has_semesters' => ($training->issemester == 1),
            'description' => $training->description,
            'training_id' => $training->id,
            'display_context' => $this->page->context->contextlevel === CONTEXT_COURSE ? 'course' : 'dashboard',
        ];

        return $this->render_from_template('block_training_architecture/training_block', $data);
    }

    public function prepare_root_levels($sortedRootLevels) {
        global $DB;
    
        return array_map(function($rootLevel) use ($DB) {
            $lu = $DB->get_record('local_training_architecture_lu_to_lu', ['id' => $rootLevel->luid1]);
    
            $levelName = $lu ? $lu->name : 'Niveau inconnu';
            $levelId = $rootLevel->luid1;
    
            // Récupérer les sous-niveaux (semestres, UE, etc.)
            $childLinks = $DB->get_records('local_training_architecture_lu_to_lu', ['luid1' => $levelId]);
    
            $children = array_map(function($link) use ($DB) {
                $childLu = $DB->get_record('local_training_architecture_lu', ['id' => $link->luid2]);
                $courseId = $childLu->courseid ?? null;
    
                return [
                    'name' => $childLu ? $childLu->name : 'Sans nom',
                    'course_id' => $courseId,
                    'course_name' => $courseId ? get_course($courseId)->fullname : null,
                    'course_url' => $courseId ? (new moodle_url('/course/view.php', ['id' => $courseId]))->out() : null,
                ];
            }, $childLinks);
    
            return [
                'level_name' => $levelName,
                'level_id' => $levelId,
                'children' => $children
            ];
        }, $sortedRootLevels);
    }

    public function render_courses_not_in_architecture($coursesNotInArchitecture) {
        if (empty($coursesNotInArchitecture) || !is_array($coursesNotInArchitecture)) {
            return ''; // ou un message vide si nécessaire
        }
    
        $data = [
            'courses' => array_map(function($courseId) {
                $course = get_course($courseId);
                return [
                    'course_name' => $course->fullname,
                    'course_url' => (new moodle_url('/course/view.php', ['id' => $courseId]))->out()
                ];
            }, $coursesNotInArchitecture)
        ];
    
        return $this->render_from_template('block_training_architecture/courses_not_in_architecture', $data);
    }
    








    public function render_description_modal($description, $id, $type) {
        global $CFG;
    
        $data = [
            'id' => $id,
            'type' => $type,
            'description' => $description,
            'image_url' => $CFG->wwwroot . "/blocks/training_architecture/images/description.png",
            'modal_title' => get_string('descriptionModalTitle', 'block_training_architecture'),
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
            ? $OUTPUT->pix_icon('t/online', get_string('actualCourse', 'block_training_architecture'), 'moodle', ['class' => 'green'])
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





}