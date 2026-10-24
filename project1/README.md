# Aquarium Tank REST API

Daniel Than · ASE 230 · Project 1

Plain PHP and MySQL API for aquarium tanks, water readings, and plants. No framework, Composer dependencies, or build step is required for the API.

Repository: [DANTHAND005/ase230](https://github.com/DANTHAND005/ase230). Project: [project1/](https://github.com/DANTHAND005/ase230/tree/main/project1).

## Requirements

PHP 8.1+ with PDO/pdo_mysql, MySQL, Bash, and cURL. NGINX and PHP-FPM are required for deployment. Verified with PHP 8.5.4, Oracle MySQL 8.4.11, and NGINX 1.28.3 in Ubuntu on WSL.

## Database setup

Run commands from this project1 directory in Ubuntu/WSL:

```bash
sudo service mysql start
sudo mysql < code/schema.sql
sudo mysql
```

In MySQL, create an application account with your own password:

```sql
CREATE USER IF NOT EXISTS 'aquarium_project1'@'127.0.0.1'
  IDENTIFIED BY 'replace-this-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON aquarium_project1.*
  TO 'aquarium_project1'@'127.0.0.1';
EXIT;
```

If that account already exists, use its existing password or deliberately change it with ALTER USER. The schema creates tanks, readings, and plants in aquarium_project1 without seed records. Importing it again preserves data and existing table definitions. It is not a migration or reset script.

## Start, test, and stop

From this project1 directory:

```bash
export DB_HOST=127.0.0.1
export DB_USER=aquarium_project1
export DB_PASSWORD='replace-this-password'
export API_TOKEN='replace-with-your-own-long-token'
php -S localhost:8080 -t code code/router.php
```

Open http://localhost:8080/test_client.html. Always access it over HTTP, not file://. Stop the development server with Ctrl+C. DB_PORT defaults to 3306 and can be exported if MySQL uses a different port. The root path has no API and returns 404; use /tanks to check the service.

In a second Bash terminal, from this project1 directory:

```bash
export API_TOKEN='replace-with-your-own-long-token'
bash code/test_curl.sh
```

The cURL suite uses PHP CLI to check JSON responses. It creates its own tank, uses returned IDs, checks all 10 endpoints and their response data, then removes the tank. It checks missing/wrong tokens, invalid JSON/fields/IDs, missing records, and incorrect methods. Failure exits nonzero; cleanup is attempted even on failure.

The HTML client has all 10 endpoints in its dropdown. Create a tank first; use its returned ID in later paths and bodies. For each protected route, test an empty token, a wrong token, and the configured token: expect 401, 401, and success. Delete the tank last. Results display method, path, HTTP status, server header, and JSON.

On Windows/XAMPP, start MySQL in XAMPP and import code/schema.sql in phpMyAdmin. Create the same account with the SQL above. Run ./start.ps1 in PowerShell from this directory; it asks for the password and token and starts the server. It uses PHP on PATH or C:\Xampp\php\php.exe. Bash tests can run in WSL with PHP installed there.

## Endpoints

| Method | Path | JSON body | Bearer token |
|---|---|---|---|
| GET | /tanks | none | no |
| GET | /tanks/{id} | none | no |
| POST | /tanks | name, gallons | yes |
| PUT | /tanks/{id} | name, gallons | no |
| DELETE | /tanks/{id} | none | yes |
| GET | /tanks/{id}/readings | none | no |
| POST | /readings | tank_id, ph, temperature_c | yes |
| DELETE | /readings/{id} | none | no |
| GET | /tanks/{id}/plants | none | no |
| POST | /plants | tank_id, name | no |

Send JSON objects with numbers for numeric fields. PUT requires both fields. Tank sizes use US gallons; temperatures use Celsius. Name accepts 1–100 bytes after trimming. Gallons accepts 0.01–999999.99, pH 0–14, and temperature 0–50. These are input limits, not aquarium health recommendations. MySQL decimals may appear as JSON strings. Timestamps use the database server's timezone. Deleting a tank cascades to its readings and plants.

The three protected routes use Authorization: Bearer <token>. The configured API_TOKEN is compared with hash_equals(); there is no login endpoint. The seven public operations follow the aquarium plan. This is a classroom single-token API, not a private multi-user service. Use HTTPS for use beyond localhost and keep real credentials out of the submission.

HTTP responses: 200 success, 201 created, 400 invalid input, 401 missing/invalid token, 404 missing route/record, 405 unsupported method (with Allow header), 500 database/configuration failure. Prepared statements bind input values. Database error details go to server logs.

## NGINX deployment

Install NGINX, MySQL, PHP-FPM and php-mysql if missing. The verified PHP version is 8.5; adjust the version in the following paths/commands if needed. Port 8088 is used to coexist with the computer's existing port 80 site.

From this project1 directory:

```bash
sudo mkdir -p /var/www/aquarium-project1/code
sudo cp code/* /var/www/aquarium-project1/code/
sudo chmod 755 /var/www/aquarium-project1/code
sudo chmod 644 /var/www/aquarium-project1/code/*
```

Create /etc/php/8.5/fpm/pool.d/aquarium-project1.conf using sudo, with the following content and your own password/token:

```ini
[aquarium-project1]
user = www-data
group = www-data
listen = /run/php/aquarium-project1.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
env[DB_HOST] = 127.0.0.1
env[DB_USER] = aquarium_project1
env[DB_PASSWORD] = replace-this-password
env[API_TOKEN] = replace-with-your-own-long-token
```

Then run:

```bash
sudo chmod 600 /etc/php/8.5/fpm/pool.d/aquarium-project1.conf
sudo cp nginx.conf /etc/nginx/sites-available/aquarium-project1
sudo ln -s /etc/nginx/sites-available/aquarium-project1 \
  /etc/nginx/sites-enabled/aquarium-project1
sudo php-fpm8.5 -t
sudo nginx -t
sudo service php8.5-fpm restart
sudo service nginx reload
export API_TOKEN='replace-with-your-own-long-token'
BASE_URL=http://localhost:8088 bash code/test_curl.sh
```

Create the symlink once; skip that command if it already exists. PHP-FPM pool variables are separate from the development server's exported variables. The nginx.conf routes API requests to index.php and serves only the test page statically. SQL and other source files are not exposed as downloads.

Open http://127.0.0.1:8088/test_client.html in Windows, or http://localhost:8088/test_client.html in WSL. WSL must be running. The response includes Server: nginx. The saved screenshot records this actual deployment. To disable just this project site, remove /etc/nginx/sites-enabled/aquarium-project1 and reload NGINX. For 502 errors, check PHP-FPM/socket paths; for 500, check MySQL credentials and /var/log/nginx/error.log.

## Files and presentations

index.php reads the method and URL, selects an allowed route, checks the token where required, and runs the corresponding SQL. body() reads JSON; name_field() and number_field() reject invalid input. query() prepares and executes SQL with bound values. tank_exists() returns 404 if the parent tank is missing. respond() sets the status, sends JSON, and stops execution. db.php opens the connection; auth.php checks the Authorization header; router.php lets PHP serve the test page while routing API paths to index.php.

- code/: PHP, schema.sql, cURL tests, and browser test client.
- presentation/api_tutorial.md and .pdf: 24-slide API tutorial, including all 10 endpoints and example requests/responses.
- presentation/nginx_deployment.md and .pdf: 13-slide deployment tutorial.
- presentation/nginx_screenshot.png: real running NGINX application.
- presentation/verification.txt: recorded testing results.
- plan/hw2_plan.md: aquarium endpoint plan reconstructed from the M4 plan, with actual completion dates.
- project1_rubric.md: technical self-assessment and personal review checklist.
- nginx.conf: deployed site configuration; start.ps1: optional Windows launcher.

Both PDFs were exported from their Marp Markdown sources. To re-export with Marp CLI installed, run marp presentation/api_tutorial.md --pdf and marp presentation/nginx_deployment.md --pdf --allow-local-files. Marp for VS Code can also export both decks.

## Verification

All 10 endpoints passed cURL tests on PHP's development server and NGINX with Oracle MySQL. The browser client also passed all endpoints through NGINX. Full results are in presentation/verification.txt.

## Sources

Requirements: project1_overview.md and project1_rubric.md. Aquarium domain and endpoints: HM4/plan.md. Concept references from HW3 exercises:

- 2_Connect_PHP_with_MySQL/Q3. CRUD_operation_using_PDO.md, questions 3.1–3.16.
- 6_RestAPI/Q2. Building REST API Server with PHP and JSON.md, questions 2.3–2.22.
- 6_RestAPI/Q3. Connecting REST API Server to MySQL with PDO.md, questions 3.4–3.12.
- 6_RestAPI/Q4. Testing REST API Server.md, questions 4.4 and 4.11–4.26.
- 7_Security/Q3. Bearer Token Authentication.md, questions 3.3, 3.7–3.12.
- 5_Webserver_using_NGINX/Q2. Setting up NGINX for PHP.md, questions 2.5–2.11.

These are exercise references, not copied lecture slides. No lecture slide text or example student/course implementation was copied into this project.

Official references consulted:

- [PHP PDO::prepare](https://www.php.net/manual/en/pdo.prepare.php).
- [PHP hash_equals](https://www.php.net/manual/en/function.hash-equals.php).
- [PHP-FPM configuration](https://www.php.net/manual/en/install.fpm.configuration.php).
- [NGINX FastCGI module](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html).
- [MySQL 8.4 foreign keys](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html).
- [Marp CLI](https://github.com/marp-team/marp-cli).
