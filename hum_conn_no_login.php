
<?php
/*-----
    function: hum_conn_no_login: void -> connection
    purpose: expects nothing,
        
        has the side-effect of trying to connect to
        Humboldt's Oracle student database using the current
        no-end-user-login approach (logging in based on where
        the PHP file resides),
            
        and, if successful, returns the resulting connection object,

        but, if NOT successful, ENDS the document and exits the
        current PHP document...! (yes, it can do that...!)

    original by: Peter Johnson, Troy Butolph (I think)
    adapted by:  Sharon Tuttle
    last modified: 2026-04-01
-----*/

function hum_conn_no_login()
{
    /*===
        using the user directory context information
        to extract the owner-of-this-PHP's username
    ===*/

    $os_username = substr($_SERVER["CONTEXT_PREFIX"], 2);

    /*===
        get this user's Oracle account name, which ends in _php
    ===*/

    $ora_php_username = "{$os_username}_php";

    /*===
        to ask to use blah_php's password to log in as blah,
        it needs to be in the form BLAH_PHP[BLAH]
    ===*/

    $conn_username = "{$ora_php_username}[{$os_username}]";

    /*===
        how to get the needed password
    ===*/

    $ora_php_password =
        trim(file_get_contents("/home/{$os_username}/.oraauth"));

    /*===
        we are using a connection string of null
        to rely on admin-set-up environmental variables,

        an encoding of 'AL32UTF8' to "[specify] the character 
        set of the database to prevent any need for negotiation", 

        and a session mode whose value is the 
        PHP constant OCI_DEFAULT
     ===*/

    $connectn = oci_connect(username: $conn_username,
                            password: $ora_php_password,
                            connection_string: null,
                            encoding: 'AL32UTF8',
                            session_mode: OCI_DEFAULT);

    /* complain and exit if connection failed */
    
    if (! $connectn)
    {
        ?>
        <p> Could not log into Oracle, sorry. </p>

    <footer>
    <hr />
    <p>
        Validate by pasting .xhtml copy's URL into<br />
	<a href="https://validator.w3.org/nu">
            https://validator.w3.org/nu
        </a>
	or  
        <a href="https://html5.validator.nu/">
            https://html5.validator.nu/
        </a>
    </p>
    </footer>
</body>
</html>
        <?php

        /*===
	    and in this connection-failed case,
	    now EXIT this PHP document HERE and NOW,
	    reasonable when you hit an error such that there
	    is NO POINT in going any further
        ===*/

        exit;
    }

    /*===
        HOWEVER -- IF reach here, connection succeeded!
    ===*/

    return $connectn;   
}

?>