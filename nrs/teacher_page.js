document.addEventListener('DOMContentLoaded', function() {
    const classroomButton = document.querySelector('.classroom-button');
    if(classroomButton) {
        classroomButton.addEventListener('click', function() {
            // Toggle active on the classroom button itself
            if(this.classList.contains("active")) {
                // Close things
                this.classList.remove('active');
                document.querySelector('.classroom-popup').classList.remove('active');
                // Re-enable scrolling
                document.body.style.overflow = '';
            }else {
                // Open things
                this.classList.add('active');
                document.querySelector('.classroom-popup').classList.add('active');
                // Disable scrolling
                document.body.style.overflow = 'hidden';
            }
        });
    }
});