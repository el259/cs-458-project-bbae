// graph.js - Renders classroom statistics charts for teachers using Chart.js
function renderClassroomStats(stats) {
    if (!stats || !stats.quizStats || stats.quizStats.length === 0) {
        return;
    }

    const titles = stats.quizStats.map(q => q.title);
    const avgScores = stats.quizStats.map(q => q.avgScore);
    const maxScores = stats.quizStats.map(q => q.maxScore);
    const numAttempted = stats.quizStats.map(q => q.numAttempted);
    const numStudents = stats.numStudents || 0;
    const completionRates = numStudents > 0
        ? numAttempted.map(n => Math.round((n / numStudents) * 100))
        : numAttempted.map(() => 0);

    // Average scores bar chart
    const scoresCtx = document.getElementById('quizScoresChart');
    if (scoresCtx) {
        new Chart(scoresCtx, {
            type: 'bar',
            data: {
                labels: titles,
                datasets: [
                    {
                        label: 'Average Score',
                        data: avgScores,
                        backgroundColor: '#0A73E3',
                        borderColor: '#0A73E3',
                        borderWidth: 1
                    },
                    {
                        label: 'Max Possible',
                        data: maxScores,
                        backgroundColor: 'rgba(40, 168, 71, 0.3)',
                        borderColor: '#28A847',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Average Scores per Quiz'
                    },
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Points'
                        }
                    }
                }
            }
        });
    }

    // Completion rate chart
    const completionCtx = document.getElementById('completionChart');
    if (completionCtx) {
        new Chart(completionCtx, {
            type: 'bar',
            data: {
                labels: titles,
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: completionRates,
                    backgroundColor: '#FFC107',
                    borderColor: '#FFC107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Quiz Completion Rate'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percent of Students'
                        }
                    }
                }
            }
        });
    }
}
