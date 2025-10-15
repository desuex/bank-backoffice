## Banking BO Demo

First, run the docker compose:
```shell
cd docker
docker-compose up -d
```

Run the tests:
```shell
php artisan test
```

Run the migrations and seeders:
```shell

php artisan migrate
php artisan bank:demo --quiet-emails  
php artisan bank:token user+1@example.test
```
Use provided credentials to act as user.

Then run the app with Herd or with artisan command:

```shell
php artisan serve
```



