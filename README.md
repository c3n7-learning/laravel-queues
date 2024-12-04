# Laravel Queues in Action

### 1. Configuring Jobs

-   `php artisan queue:work` can be horizontally scaled, by running multiple `queue:work`.
-   To prioritize queues, specify as follows: The `payments` queue will be given higher priority

```shell
php artisan queue:work --queue=payments,default
```
