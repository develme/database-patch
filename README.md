# database-patch

Setup patch directory and table

```
    php artisan patch:install
```

Check patch status

```
    php artisan patch:status
```

Create patch to insert data into new table

```
    php artisan make:patch --insert="example_table"
```

Create patch to update existing table

```
    php artisan make:patch --table="example_table"
```

Run patches

```
    php artisan patch
```

Undo all patches

```
    php artisan patch:reset
```

Undo then rerun all patches

```
    php artisan patch:refresh
```