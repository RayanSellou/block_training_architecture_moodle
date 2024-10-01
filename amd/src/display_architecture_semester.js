// Select all checkboxes on the page and iterate over each one
document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {

    // Get the ID of the checkbox
    var switchId = checkbox.id;

    // Generate the IDs of the architecture elements by replacing 'switch' with other segments of the ID
    var semester_levels_semester_temp = switchId.replace('switch', 'semester-levels-semester'); 
    var semester_levels_temp = switchId.replace('switch', 'semester-levels');

    // Select the elements representing the architecture by semester and level
    var semester_levels_semester = document.getElementById(semester_levels_semester_temp); 
    var semester_levels_ = document.getElementById(semester_levels_temp); 

    // Generate the IDs of the training path elements by replacing 'switch' with other segments of the ID
    var path_training_semester_temp = switchId.replace('switch', 'path-training-semester'); 
    var path_training_temp = switchId.replace('switch', 'path-training');

    // Select the elements representing the training path by semester and general
    var path_training_semester = document.getElementById(path_training_semester_temp); 
    var path_training = document.getElementById(path_training_temp); 

    // Hide or display elements based on the checkbox state
    if (semester_levels_semester && semester_levels_) {
        semester_levels_semester.style.display = checkbox.checked ? 'block' : 'none';
        semester_levels_.style.display = checkbox.checked ? 'none' : 'block';
    }

    if (path_training_semester && path_training) {
        path_training_semester.style.display = checkbox.checked ? 'block' : 'none';
        path_training.style.display = checkbox.checked ? 'none' : 'block';
    }
    
    // Add an event listener to handle changes in the checkbox state
    checkbox.addEventListener('change', function() {
        if (semester_levels_semester && semester_levels_) {
            semester_levels_semester.style.display = this.checked ? 'block' : 'none';
            semester_levels_.style.display = this.checked ? 'none' : 'block';
        }

        if (path_training_semester && path_training) {
            path_training_semester.style.display = this.checked ? 'block' : 'none';
            path_training.style.display = this.checked ? 'none' : 'block';
        }
    });
});
