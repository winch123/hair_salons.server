
composer install

sudo chown 33 storage/logs
sudo chown 33 storage/framework/cache
sudo chown 33 storage/framework/sessions
sudo chown 33 storage/framework/views

>> настроить все БД.

cp .env.example .env

# ./artisan config:cache
./artisan config:clear
./artisan tinker
        DB::connection('mysql3')->getPdo();

./artisan db:seed
./artisan migrate:install
./artisan migrate


./artisan passport:install
./artisan key:generate


cd ./public/img
ln -s ../../storage/app/public/ public
