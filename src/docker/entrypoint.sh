#!/bin/sh
echo "Creating new config..."
if [ -z "$DB_HOST" ]; then
echo "Variable \$DB_HOST is required.";
exit 1;
fi
if [ -z "$MYSQL_USER" ]; then
echo "Variable \$MYSQL_USER is required.";
exit 1;
fi
if [ -z "$MYSQL_PASSWORD" ]; then
echo "Variable \$MYSQL_PASSWORD is required.";
exit 1;
fi
if [ -z "$MYSQL_DATABASE" ]; then
echo "Variable \$MYSQL_DATABASE is required.";
exit 1;
fi
if [ -z "$YII_ENV" ]; then
echo "Variable \$YII_ENV is required.";
exit 1;
fi

ATTEMPTS=0
while [ $ATTEMPTS -lt 10 ]; do
  # First run migrations.
  /project/protected/yiic migrate/up --interactive=0
  if [ $? -eq 0 ]; then
    echo "Migrations done";
    break;
  fi
  echo "Failed to run migrations, retrying in 10s.";
  sleep 10;
  let ATTEMPTS=ATTEMPTS+1
done

if [ $ATTEMPTS -gt 9 ]; then
  echo "Migrations failed.."
  exit 1;
fi
exec php-fpm7 "$@"