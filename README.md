# cs-458-project-bbae
Senior Capstone Project

https://nrs-projects.humboldt.edu/~bk110/humglot.php

This link should redirect to whichever of our various nrs versions has the most recently modified "footer.php"

To make this work on your own host, you have to run table-create.sql, then the two populate scripts (all three located in /data/). It should then allow you to sign in and whatnot.

## Humboldt CAS SSO

The CAS callback service URL is:

```text
https://humglot.blakeculbertson.com/cas/callback.php
```

Run `nrs/data/cas-migration.sql` against the application schema before enabling CAS. Copy the CAS settings from `.env.example` into the deployment environment. Use the `cas-dev.humboldt.edu` URLs for testing, then switch the login, logout, and validation URLs together to `cas.humboldt.edu` for production. The application validates tickets server-side and fails closed when CAS configuration is missing or invalid.

Local username/password sign-in remains available while CAS is being tested. Set
`LOCAL_AUTH_ENABLED=false` to hide local sign-in after CAS is fully operational.
