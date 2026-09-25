<?php
session_start();


// Make sure the user is logged in and is a teacher
if (!isset($_SESSION['account']) || empty($_SESSION['teacher']))
{
header("Location: index.php");
exit();
}



// Start a new classroom
if (!isset($_SESSION['classroom']))
{
    $_SESSION['classroom'] = [
'name' => '',
'passcode' => '',
'students' => []
    ];
}



$error = '';

// Save classroom name
if (isset($_POST['setClassroomName']))
{
    $classroomName = trim($_POST['classroomName'] ?? '');


if ($classroomName === '')
    {
        $error = 'Please enter a classroom name.';
    }
else
    {
        $_SESSION['classroom']['name'] = $classroomName;



header("Location: create_classroom.php");
exit();
    }
}



// Save or update passcode
if (isset($_POST['setPasscode']))
{
    $passcode = trim($_POST['passcode'] ?? '');



    $_SESSION['classroom']['passcode'] = $passcode;



header("Location: create_classroom.php");
exit();
}



// Add a student
if (isset($_POST['addStudent']))
{
    $student = trim($_POST['student'] ?? '');



if ($student === '')
    {
        $error = 'Please enter a student name.';
    }
else
    {
        $alreadyAdded = false;



foreach ($_SESSION['classroom']['students'] as $existingStudent)
        {
            if (strcasecmp($existingStudent, $student) === 0)
            {
                $alreadyAdded = true;
                break;
            }
        }



if ($alreadyAdded)
        {
            $error = 'That student is already in this classroom.';
        }
else
        {
            $_SESSION['classroom']['students'][] = $student;



header("Location: create_classroom.php");
exit();
        }
    }
}



// Remove a student
if (isset($_POST['removeStudent']))
{
    $removeIndex = intval($_POST['removeIndex'] ?? -1);



if (isset($_SESSION['classroom']['students'][$removeIndex]))
    {
        array_splice($_SESSION['classroom']['students'], $removeIndex, 1);
    }



header("Location: create_classroom.php");
exit();
}



// Create the classroom
if (isset($_POST['createClassroom']))
{
    $classroomName = $_SESSION['classroom']['name'];
    $passcode = trim($_SESSION['classroom']['passcode'] ?? '');
    $students = $_SESSION['classroom']['students'];



if ($classroomName != '' && ($passcode !== '' || count($students) > 0))
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '_', str_replace(' ', '_', $classroomName));
        $slug = trim($slug, '_');



if ($slug === '')
        {
            $error = 'Classroom name must include letters or numbers.';
        }
else
        {
            $classroomDir = __DIR__ . '/classrooms';



if (!is_dir($classroomDir))
            {
mkdir($classroomDir, 0755, true);
            }



            $filepath = $classroomDir . '/' . $slug . '.json';



if (file_exists($filepath))
        {
            $error = 'Classroom already exists. Try again.';
        }
else
        {
            $jsonString = json_encode($_SESSION['classroom'], JSON_PRETTY_PRINT);



if (file_put_contents($filepath, $jsonString))
            {
unset($_SESSION['classroom']);



header("Location: teacher.php");
exit();
            }
else
            {
                $error = 'Error creating classroom. Try again.';
            }
        }
        }
    }
else
    {
        $error = 'You must give the classroom a name and add at least one student or a passcode.';
    }
}



// Cancel classroom creation
if (isset($_POST['cancelClassroom']))
    {
unset($_SESSION['classroom']);
header("Location: teacher.php");
exit();
    }
?>


<!DOCTYPE html>
<html lang="en">


<!--
    Ben Kanter, Blake Culbertson, Andrew Gallimore, Enrique Lopez
    Last Modified: 9/25/26
    CREATE_CLASSROOM.PHP
-->



<head>
<title>HumGlot</title>
<meta charset="utf-8" />
<link rel="stylesheet" href="global.css" />
</head>


<body>
<?php require __DIR__ . '/header.php'; ?>


<h1>Create a Classroom</h1>



<?php
if ($error !== '')
    {
?>


<p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>



<?php
    }
?>


<!-- Go back to teacher page -->
<form method="post" action="create_classroom.php">
<input type="submit" name="cancelClassroom" value="Cancel" />
</form>



<?php
if ($_SESSION['classroom']['name'] == '')
{
?>



<!-- STEP 1: Name the classroom -->



<h2>1. Name Your Classroom</h2>
<form method="post" action="create_classroom.php">
<label for="classroomName">Classroom Name</label>
<input
type="text"
name="classroomName"
id="classroomName"
required="required"
/>


<input
type="submit"
name="setClassroomName"
value="Continue"
/>


</form>


<?php
}
else
{
?>


<h2><?= htmlspecialchars($_SESSION['classroom']['name'], ENT_QUOTES, 'UTF-8') ?></h2>


<!-- STEP 2: Optional passcode for students to join -->



<h2>Student Passcode</h2>
<p>Students can join this classroom with a passcode. Leave blank if you only want to add students yourself.</p>



<form method="post" action="create_classroom.php">
<label for="passcode">Passcode</label>
<input
type="text"
name="passcode"
id="passcode"
value="<?= htmlspecialchars($_SESSION['classroom']['passcode'], ENT_QUOTES, 'UTF-8') ?>"
/>


<input
type="submit"
name="setPasscode"
value="Save Passcode"
/>
</form>



<?php
if ($_SESSION['classroom']['passcode'] !== '')
    {
?>
<p>Current passcode: <strong><?= htmlspecialchars($_SESSION['classroom']['passcode'], ENT_QUOTES, 'UTF-8') ?></strong></p>
<?php
    }
?>



<!-- Display students that have already been added -->
<?php
if (count($_SESSION['classroom']['students']) > 0)
    {
?>



<h3>Students</h3>



<?php
foreach ($_SESSION['classroom']['students'] as $index => $student)
        {
?>



<div>
<p>
<strong><?= ($index + 1) ?>. </strong>
<?= htmlspecialchars($student, ENT_QUOTES, 'UTF-8') ?>
</p>



<form method="post" action="create_classroom.php">
<input type="hidden" name="removeIndex" value="<?= $index ?>" />
<input type="submit" name="removeStudent" value="Remove" />
</form>
</div>



<?php
        }
    }
?>



<!-- STEP 3: Add another student -->



<h2>Add a Student</h2>



<form method="post" action="create_classroom.php">



<div>
<label for="student"> Student Name </label>



<input
type="text"
name="student"
id="student"
required="required"
/>
</div>



<div>
<input
type="submit"
name="addStudent"
value="Add Student"
/>
</div>



</form>



<?php
// Only show Create button after a passcode or at least one student exists
if ($_SESSION['classroom']['passcode'] !== '' || count($_SESSION['classroom']['students']) > 0)
    {
?>



<form method="post" action="create_classroom.php">



<input
type="submit"
name="createClassroom"
value="Create Classroom"
/>



</form>



<?php
        }
    }
?>


<?php require __DIR__ . '/footer.php'; ?>


</body>
</html>
