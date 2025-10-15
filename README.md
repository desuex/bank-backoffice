## Banking BO Demo

First, run the docker compose:
```shell
cd docker
docker-compose up -d
```
Run the migrations and seeders:
```shell

php artisan migrate
php artisan bank:demo --users=10 --currencies=EUR,USD --accounts-per-user=2 --balance=2000
```
Run the tests:
```shell
php artisan test
```

Then run the app with Herd or with artisan command:

```shell
php artisan serve
```



