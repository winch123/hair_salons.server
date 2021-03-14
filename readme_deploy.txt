
composer install

sudo chown 33 storage/logs
sudo chown 33 storage/framework/cache
sudo chown 33 storage/framework/sessions
sudo chown 33 storage/framework/views

>> настроить все БД.

./artisan config:cache
./artisan migrate:install
./artisan migrate


./artisan passport:install

cd ./public/img
ln -s /var/www/html/haircut_salons.server/storage/app/public/ public
