<?php

require_once dirname(__DIR__, 2) . '/hum_conn_no_login.php';

function provisionCasUser(string $username): array
{
    $connection = hum_conn_no_login();
    $provider = 'humboldt-cas';
    $email = strtolower($username) . '@example.com';
    $firstName = $username;
    $lastName = 'User';

    $statement = oci_parse($connection, '
        SELECT "USER_ID", "IS_ACTIVE", "IS_ADMIN"
        FROM "APP_USER"
        WHERE "CAS_PROVIDER" = :provider
          AND "CAS_USERNAME" = :username
    ');
    oci_bind_by_name($statement, ':provider', $provider);
    oci_bind_by_name($statement, ':username', $username);
    if (!oci_execute($statement)) {
        oci_free_statement($statement);
        oci_close($connection);
        throw new RuntimeException('Unable to look up the CAS account.');
    }
    $user = oci_fetch_assoc($statement);
    oci_free_statement($statement);

    if ($user === false) {
        $statement = oci_parse($connection, '
            INSERT INTO "APP_USER" (
                "EMAIL", "FIRST_NAME", "LAST_NAME", "PWD_HASH",
                "CAS_PROVIDER", "CAS_USERNAME"
            ) VALUES (
                :email, :first_name, :last_name, NULL,
                :provider, :username
            )
            RETURNING "USER_ID" INTO :user_id
        ');
        $userId = 0;
        oci_bind_by_name($statement, ':email', $email);
        oci_bind_by_name($statement, ':first_name', $firstName);
        oci_bind_by_name($statement, ':last_name', $lastName);
        oci_bind_by_name($statement, ':provider', $provider);
        oci_bind_by_name($statement, ':username', $username);
        oci_bind_by_name($statement, ':user_id', $userId);
        if (!oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
            oci_rollback($connection);
            oci_free_statement($statement);
            oci_close($connection);
            throw new RuntimeException('Unable to create the CAS account.');
        }
        oci_commit($connection);
        oci_free_statement($statement);
        $user = ['USER_ID' => $userId, 'IS_ACTIVE' => 'Y', 'IS_ADMIN' => 'N'];
    }

    oci_close($connection);
    if ($user['IS_ACTIVE'] !== 'Y') {
        throw new RuntimeException('This account is inactive.');
    }

    return [
        'user_id' => (int) $user['USER_ID'],
        'is_admin' => $user['IS_ADMIN'] === 'Y'
    ];
}
