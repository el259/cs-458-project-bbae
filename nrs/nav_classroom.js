// document.addEventListener('DOMContentLoaded', function() {
//     const headerClassroomButton = document.querySelector('.classroom-button');
//     const classroomClose = document.querySelector('.close-classroom-popup');

//     if(headerClassroomButton) {
//         headerClassroomButton.addEventListener('click', function() {
//             // Toggle active on the classroom button itself
//             if(this.classList.contains("active")) {
//                 // Close things
//                 this.classList.remove('active');
//                 document.querySelector('.classroom-popup').classList.remove('active');
//                 // Re-enable scrolling
//                 document.body.style.overflow = '';
//             }else {
//                 // Open things
//                 this.classList.add('active');
//                 document.querySelector('.classroom-popup').classList.add('active');
//                 // Disable scrolling
//                 document.body.style.overflow = 'hidden';
//             }
//         });
//     }
//     if(classroomClose) {
//         classroomClose.addEventListener('click', function() {
//             headerClassroomButton.classList.remove('active');
//             document.querySelector('.classroom-popup').classList.remove('active');
//             document.body.style.overflow = '';
//         });
//     }
// });

const headerClassroomButton = document.querySelector('.classroom-button');
const classroomClose = document.querySelector('.close-classroom-popup');
const classroomPopup = document.querySelector('.classroom-popup');

function toggleClassroomPopup() {
    if(headerClassroomButton.classList.contains('active')) {
        headerClassroomButton.classList.remove('active');
        classroomPopup.classList.remove('active');
        document.body.style.overflow = '';
    } else {
        headerClassroomButton.classList.add('active');
        classroomPopup.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeClassroomPopup() {
    headerClassroomButton.classList.remove('active');
    classroomPopup.classList.remove('active');
    document.body.style.overflow = '';
}