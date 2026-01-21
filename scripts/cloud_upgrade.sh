set -e
export IMAGE=$1
docker system prune -af
docker compose pull
read -p "Press Enter to update Saturn Platform to $IMAGE..." </dev/tty
while ! (docker exec saturn sh -c "php artisan tinker --execute='isAnyDeploymentInprogress()'" && docker compose up --remove-orphans --force-recreate -d --wait && echo $IMAGE > last_version); do sleep 1; done