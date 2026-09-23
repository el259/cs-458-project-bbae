-- Make sure there is a user to own the quizzes.
MERGE INTO "APP_USER" target
USING (
    SELECT
        'quiz-import@example.com' AS email,
        'Quiz' AS first_name,
        'Import' AS last_name,
        'quiz-import-password-hash' AS pwd_hash
    FROM dual
) source
ON (target."EMAIL" = source.email)
WHEN NOT MATCHED THEN
    INSERT (
        "EMAIL",
        "FIRST_NAME",
        "LAST_NAME",
        "PWD_HASH"
    )
    VALUES (
        source.email,
        source.first_name,
        source.last_name,
        source.pwd_hash
    );

-- data.json
INSERT INTO "QUIZ" (
    "CREATOR_ID",
    "TITLE",
    "DESCRIPTION",
    "QUIZ_JSON"
)
SELECT
    u."USER_ID",
    'Data Quiz',
    'Imported from data.json',
    q'~{
    "questions": [
        "What is the best letter?",
        "What is the square root of pi?"
    ],
    "answers": [
        [
            "a",
            "b",
            "c",
            "d"
        ],
        [
            "3.14",
            "apple",
            "1.77",
            "0"
        ]
    ],
    "correct": [
        "a",
        "1.77"
    ]
}~'
FROM "APP_USER" u
WHERE u."EMAIL" = 'quiz-import@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM "QUIZ" existing_quiz
      WHERE existing_quiz."TITLE" = 'Data Quiz'
        AND existing_quiz."CREATOR_ID" = u."USER_ID"
  );

-- Hola.json
INSERT INTO "QUIZ" (
    "CREATOR_ID",
    "TITLE",
    "DESCRIPTION",
    "QUIZ_JSON"
)
SELECT
    u."USER_ID",
    'Hola Quiz',
    'Imported from Hola.json',
    q'~{
    "questions": [
        "\u00bfC\u00f3mo te llamas?"
    ],
    "answers": [
        [
            "Me llamo\u2026",
            "Te llamas\u2026",
            "Nombre",
            "Apellido"
        ]
    ],
    "correct": [
        "Me llamo\u2026"
    ]
}~'
FROM "APP_USER" u
WHERE u."EMAIL" = 'quiz-import@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM "QUIZ" existing_quiz
      WHERE existing_quiz."TITLE" = 'Hola Quiz'
        AND existing_quiz."CREATOR_ID" = u."USER_ID"
  );

-- quiz_0.json
INSERT INTO "QUIZ" (
    "CREATOR_ID",
    "TITLE",
    "DESCRIPTION",
    "QUIZ_JSON"
)
SELECT
    u."USER_ID",
    'Quiz 0',
    'Imported from quiz_0.json',
    q'~{
    "questions": [
        "What is the best letter?",
        "What is pi?"
    ],
    "answers": [
        [
            "a",
            "b",
            "c",
            "d"
        ],
        [
            "3.14",
            "apple",
            "1.77",
            "0"
        ]
    ],
    "correct": [
        "a",
        "3.14"
    ]
}~'
FROM "APP_USER" u
WHERE u."EMAIL" = 'quiz-import@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM "QUIZ" existing_quiz
      WHERE existing_quiz."TITLE" = 'Quiz 0'
        AND existing_quiz."CREATOR_ID" = u."USER_ID"
  );

-- test_quiz.json
INSERT INTO "QUIZ" (
    "CREATOR_ID",
    "TITLE",
    "DESCRIPTION",
    "QUIZ_JSON"
)
SELECT
    u."USER_ID",
    'Test Quiz',
    'Imported from test_quiz.json',
    q'~{
    "questions": [
        "What&#039;s the best burger?"
    ],
    "answers": [
        [
            "super",
            "grande",
            "bacon",
            "cheddar"
        ]
    ],
    "correct": [
        "bacon"
    ]
}~'
FROM "APP_USER" u
WHERE u."EMAIL" = 'quiz-import@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM "QUIZ" existing_quiz
      WHERE existing_quiz."TITLE" = 'Test Quiz'
        AND existing_quiz."CREATOR_ID" = u."USER_ID"
  );

-- test_quiz_2.json
INSERT INTO "QUIZ" (
    "CREATOR_ID",
    "TITLE",
    "DESCRIPTION",
    "QUIZ_JSON"
)
SELECT
    u."USER_ID",
    'Test Quiz 2',
    'Imported from test_quiz_2.json',
    q'~{
    "questions": [
        "What&#039;s the worst number?"
    ],
    "answers": [
        [
            "One",
            "Two",
            "Three",
            "Four"
        ]
    ],
    "correct": [
        "One"
    ]
}~'
FROM "APP_USER" u
WHERE u."EMAIL" = 'quiz-import@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM "QUIZ" existing_quiz
      WHERE existing_quiz."TITLE" = 'Test Quiz 2'
        AND existing_quiz."CREATOR_ID" = u."USER_ID"
  );

COMMIT;