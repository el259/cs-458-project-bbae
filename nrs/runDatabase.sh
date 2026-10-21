#!/bin/bash

cd "$(dirname "$0")/data" || exit 1

sqlplus / <<EOF
@test-table-create.sql
@test-populate.sql
@textbooks-create.sql
exit
EOF

cd .. || exit 1