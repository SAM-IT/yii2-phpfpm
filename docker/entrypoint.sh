#!/bin/sh
chown nobody:nobody /runtime
su nobody -s /bin/touch /runtime/testfile && rm /runtime/testfile;
if [ $? -ne 0 ]; then
  echo Runtime directory is not writable;
  exit 1
fi

grep 'tmpfs /runtime' /proc/mounts;
if [ $? -ne 0 ]; then
  echo "/runtime should be a tmpfs";
fi

su nobody -s /bin/touch /runtime/env.json
jq -n env > /runtime/env.json
if [ $? -ne 0 ]; then
  echo "failed to store env in /runtime/env.json";
  exit 1
fi

exec php-fpm --force-stderr --fpm-config /php-fpm.conf