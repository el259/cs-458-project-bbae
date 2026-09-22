CREATE TABLE "app_user" (
	"user_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"email" VARCHAR2(255) NOT NULL UNIQUE,
	"first_name" VARCHAR2(100) NOT NULL,
	"last_name" VARCHAR2(100) NOT NULL,
	"pwd_hash" VARCHAR2(255) NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"is_active" CHAR(1) NOT NULL DEFAULT 'Y',
	"is_admin" CHAR(1) NOT NULL DEFAULT 'N',
	PRIMARY KEY("user_id")
);


CREATE TABLE "classroom" (
	"classroom_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"classroom_name" VARCHAR2(200) NOT NULL,
	"description" VARCHAR2(2000),
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"is_active" CHAR(1) NOT NULL DEFAULT 'Y',
	PRIMARY KEY("classroom_id")
);


CREATE TABLE "classroom_membership" (
	"membership_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"classroom_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"role" VARCHAR2(20) NOT NULL DEFAULT 'student',
	"joined_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY("membership_id"),
	CONSTRAINT "classroom_membership_unique_0" UNIQUE ("classroom_id", "user_id")
);


CREATE TABLE "classroom_assignment" (
	"classroom_assignment_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"classroom_id" INTEGER NOT NULL,
	"quiz_id" INTEGER NOT NULL,
	"points_possible" NUMBER(8,2) NOT NULL DEFAULT 100,
	"attempts_allowed" NUMBER NOT NULL DEFAULT 1,
	"available_at" TIMESTAMP,
	"due_at" TIMESTAMP,
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY("classroom_assignment_id"),
	CONSTRAINT "classroom_quiz_unique_0" UNIQUE ("classroom_id", "quiz_id")
);


CREATE TABLE "quiz" (
	"quiz_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"creator_id" INTEGER NOT NULL,
	"title" VARCHAR2(200) NOT NULL,
	"description" VARCHAR2(2000),
	"quiz_json" CLOB NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY("quiz_id")
);


CREATE TABLE "quiz_attempt" (
	"attempt_id" INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY,
	"classroom_assignment_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"attempt_number" NUMBER NOT NULL,
	"started_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"submitted_at" TIMESTAMP,
	"score" NUMBER(8,2),
	"response_json" CLOB NOT NULL,
	PRIMARY KEY("attempt_id")
);


ALTER TABLE "classroom_membership"
ADD CONSTRAINT "fk_membership_classroom" FOREIGN KEY ("classroom_id") REFERENCES "classroom" ("classroom_id")
ON DELETE CASCADE;
ALTER TABLE "classroom_membership"
ADD CONSTRAINT "fk_membership_user" FOREIGN KEY ("user_id") REFERENCES "app_user" ("user_id");
ALTER TABLE "classroom_assignment"
ADD CONSTRAINT "fk_ca_classroom" FOREIGN KEY ("classroom_id") REFERENCES "classroom" ("classroom_id")
ON DELETE CASCADE;
ALTER TABLE "classroom_assignment"
ADD CONSTRAINT "fk_classroom_assignment_quiz_id_quiz" FOREIGN KEY ("quiz_id") REFERENCES "quiz" ("quiz_id");
ALTER TABLE "quiz"
ADD CONSTRAINT "fk_assignment_creator" FOREIGN KEY ("creator_id") REFERENCES "app_user" ("user_id");
ALTER TABLE "quiz_attempt"
ADD CONSTRAINT "fk_quiz_attempt_classroom_assignment_id_classroom_assignment" FOREIGN KEY ("classroom_assignment_id") REFERENCES "classroom_assignment" ("classroom_assignment_id");
ALTER TABLE "quiz_attempt"
ADD CONSTRAINT "fk_quiz_attempt_user_id_app_user" FOREIGN KEY ("user_id") REFERENCES "app_user" ("user_id");