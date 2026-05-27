<?php

// Copy this file to includes/config.local.php on each environment
// and adjust the credentials. config.local.php is gitignored so it
// will never be pushed to GitHub.

putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=your_database_name');
putenv('DB_USER=your_database_user');
putenv('DB_PASS=your_database_password');
